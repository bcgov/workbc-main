import os
import re
import json
import asyncio
import traceback
from functools import partial
import psycopg2
from psycopg2.extras import RealDictCursor

import redis
import chromadb
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from opensearchpy import OpenSearch
from openai import OpenAI
import uvicorn

# NOC code → career title/URL lookup, populated at startup
NOC_TITLE_MAP = {}

# ---------------------------------------------------------------------------
# 1. CONFIGURATION
# ---------------------------------------------------------------------------
REDIS_HOST        = os.getenv("REDIS_HOST2", "localhost")
REDIS_PORT        = int(os.getenv("REDIS_PORT", 6379))
MISTRAL_HOST      = os.getenv("MISTRAL_SERVICE_SERVICE_HOST", "mistral-service")
MISTRAL_PORT      = os.getenv("MISTRAL_SERVICE_SERVICE_PORT", "80")
CHROMA_HOST       = os.getenv("CHROMA_SERVICE_HOST", "chroma-service")
CHROMA_PORT       = os.getenv("CHROMA_SERVICE_PORT", "8000")
OPENSEARCH_SERVER = os.getenv("ConnectionStrings__ElasticSearchServer", "localhost")
OPENSEARCH_USER   = os.getenv("IndexSettings__ElasticUser", "")
OPENSEARCH_PASS   = os.getenv("IndexSettings__ElasticPassword", "")
WORKBC_BASE_URL   = os.getenv("WORKBC_BASE_URL", "https://www.workbc.ca").rstrip("/")

# PostgreSQL connection for Career Trek videos
POSTGRES_HOST     = os.getenv("POSTGRES_HOST")
POSTGRES_PORT     = int(os.getenv("POSTGRES_PORT", "5432"))
POSTGRES_DB       = os.getenv("POSTGRES_DB")
POSTGRES_USER     = os.getenv("POSTGRES_USER")
POSTGRES_PASSWORD = os.getenv("POSTGRES_PASSWORD")


MAX_TOKENS = 800
PAGE_SIZE  = 5

# ---------------------------------------------------------------------------
# 2. LOCATION AND EMPLOYER MAPS
# ---------------------------------------------------------------------------

BC_VARIANTS = {"BC", "BRITISH COLUMBIA", "B.C."}

OTHER_CANADIAN_PROVINCES = {
    "ONTARIO", "ON", "ALBERTA", "AB", "QUEBEC", "QC", "QUÉBEC",
    "MANITOBA", "MB", "SASKATCHEWAN", "SK", "NOVA SCOTIA", "NS",
    "NEW BRUNSWICK", "NB", "NEWFOUNDLAND", "NL", "NEWFOUNDLAND AND LABRADOR",
    "PRINCE EDWARD ISLAND", "PEI", "NORTHWEST TERRITORIES", "NT",
    "NUNAVUT", "NU", "YUKON", "YT",
}

US_STATES = {
    "ALABAMA", "AL", "ALASKA", "AK", "ARIZONA", "AZ", "ARKANSAS", "AR",
    "CALIFORNIA", "CA", "COLORADO", "CO", "CONNECTICUT", "CT",
    "DELAWARE", "DE", "FLORIDA", "FL", "GEORGIA", "GA", "HAWAII", "HI",
    "IDAHO", "ID", "ILLINOIS", "IL", "INDIANA", "IN", "IOWA", "IA",
    "KANSAS", "KS", "KENTUCKY", "KY", "LOUISIANA", "LA", "MAINE", "ME",
    "MARYLAND", "MD", "MASSACHUSETTS", "MA", "MICHIGAN", "MI",
    "MINNESOTA", "MN", "MISSISSIPPI", "MS", "MISSOURI", "MO",
    "MONTANA", "MT", "NEBRASKA", "NE", "NEVADA", "NV",
    "NEW HAMPSHIRE", "NH", "NEW JERSEY", "NJ", "NEW MEXICO", "NM",
    "NEW YORK", "NY", "NORTH CAROLINA", "NC", "NORTH DAKOTA", "ND",
    "OHIO", "OH", "OKLAHOMA", "OK", "OREGON", "OR", "PENNSYLVANIA", "PA",
    "RHODE ISLAND", "RI", "SOUTH CAROLINA", "SC", "SOUTH DAKOTA", "SD",
    "TENNESSEE", "TN", "TEXAS", "TX", "UTAH", "UT", "VERMONT", "VT",
    "VIRGINIA", "VA", "WASHINGTON", "WA", "WEST VIRGINIA", "WV",
    "WISCONSIN", "WI", "WYOMING", "WY", "UNITED STATES", "USA", "US",
}

OTHER_COUNTRIES = {
    "AUSTRALIA", "INDIA", "CHINA", "JAPAN", "GERMANY", "FRANCE",
    "UNITED KINGDOM", "UK", "ENGLAND", "SCOTLAND", "WALES",
    "IRELAND", "ITALY", "SPAIN", "MEXICO", "BRAZIL", "ARGENTINA",
    "SOUTH AFRICA", "NIGERIA", "KENYA", "EGYPT", "SAUDI ARABIA",
    "UAE", "SINGAPORE", "SOUTH KOREA", "KOREA", "TAIWAN", "THAILAND",
    "VIETNAM", "PHILIPPINES", "INDONESIA", "MALAYSIA", "NEW ZEALAND",
    "CANADA",
}

NON_BC_CITIES = {
    # Canadian cities outside BC
    "TORONTO", "MONTREAL", "CALGARY", "EDMONTON", "OTTAWA",
    "WINNIPEG", "HALIFAX", "SASKATOON", "REGINA", "HAMILTON",
    "LONDON", "KITCHENER", "WATERLOO", "WINDSOR", "QUEBEC CITY",
    "MISSISSAUGA", "BRAMPTON", "MARKHAM", "VAUGHAN", "OSHAWA",
    "GATINEAU", "LAVAL", "LONGUEUIL", "SHERBROOKE", "TROIS-RIVIÈRES",
    "BARRIE", "GUELPH", "KINGSTON", "ST. CATHARINES", "OAKVILLE",
    "BURLINGTON", "RICHMOND HILL", "SUDBURY", "THUNDER BAY",
    "RED DEER", "LETHBRIDGE", "MEDICINE HAT", "FORT MCMURRAY",
    "GRANDE PRAIRIE", "SHERWOOD PARK", "ST. JOHN'S", "MONCTON",
    "FREDERICTON", "CHARLOTTETOWN", "WHITEHORSE", "YELLOWKNIFE",

    # Major US cities
    "SEATTLE", "PORTLAND", "SAN FRANCISCO", "LOS ANGELES",
    "NEW YORK CITY", "CHICAGO", "BOSTON", "DENVER", "MIAMI",
    "DALLAS", "HOUSTON", "PHOENIX", "ATLANTA", "PHILADELPHIA",
    "SAN DIEGO", "MINNEAPOLIS", "DETROIT", "WASHINGTON DC",
}

OUT_OF_SCOPE_LOCATIONS = OTHER_CANADIAN_PROVINCES | US_STATES | OTHER_COUNTRIES | NON_BC_CITIES

DATE_SORT_KEYWORDS = {"latest", "recent", "newest", "new", "today", "this week"}

CITY_PROVINCE_SUFFIXES = [
    ", BC", ", B.C.", ", British Columbia", ", AB", ", Alberta",
    ", ON", ", Ontario", ", QC", ", Quebec", ", Québec",
    ", MB", ", Manitoba", ", SK", ", Saskatchewan",
    ", NS", ", Nova Scotia", ", NB", ", New Brunswick",
    ", NL", ", Newfoundland", ", PEI", ", Prince Edward Island", ", Canada",
]

# Common synonyms and abbreviations → canonical search terms
# These get expanded BEFORE the rewriter / ChromaDB search
CAREER_SYNONYMS = {
    # Nursing abbreviations
    "rn":                  "registered nurse",
    "lpn":                 "licensed practical nurse",
    "np":                  "nurse practitioner",
    "rpn":                 "registered psychiatric nurse",

    # Medical
    "doctor":              "general practitioner physician",
    "doctors":             "general practitioner physician",
    "physician":           "general practitioner",
    "surgeon":             "specialists surgery",
    "surgeons":            "specialists surgery",
    "gp":                  "general practitioner",
    "md":                  "physician",
    "vet":                 "veterinarian",
    "vets":                "veterinarian",

    # Personal services
    "hairdresser":         "hairstylist",
    "hairdressers":        "hairstylist",
    "stylist":             "hairstylist",
    "esthetician":         "estheticians",

    # Tech abbreviations
    "dev":                 "software developer",
    "developer":           "software developer",
    "swe":                 "software engineer",
    "sde":                 "software developer",
    "ux designer":         "web designer",
    "ui designer":         "web designer",
    "data analyst":        "database analyst",
    "qa":                  "software tester",

    # Trades
    "hvac":                "heating refrigeration air conditioning mechanic",
    "electrician apprentice": "electrician",
    "plumber apprentice":  "plumber",

    # Education
    "prof":                "university professor",
    "teacher":             "elementary school teacher",
    "professor":           "university professor",
    "tutor":               "private tutor",

    # Office/admin
    "secretary":           "administrative assistant",
    "admin":               "administrative officer",
    "ea":                  "executive assistant",

    # Sales/service
    "cashier":             "cashiers",
    "barista":             "food and beverage server",
    "waiter":              "food and beverage server",
    "waitress":            "food and beverage server",
    "server":              "food and beverage server",

    # Creative
    "writer":              "author",
    "designer":            "graphic designer",
    "photographer":        "photographers",

    # Public safety
    "cop":                 "police officer",
    "cops":                "police officer",
}



# NOC 2021 major groups for category filtering
# First 2 digits of NOC code identify the broad occupational category
NOC_CATEGORY_PREFIXES = {
    "trades": {
        "prefixes": ["72", "73", "75"],
        "label": "trades",
        "description": "Construction, electrical, mechanical, and skilled trades"
    },
    "health": {
        "prefixes": ["31", "32", "33"],
        "label": "healthcare",
        "description": "Nursing, medical, allied health, and care occupations"
    },
    "tech": {
        "prefixes": ["21", "22"],
        "label": "technology and science",
        "description": "Software, engineering, scientific occupations"
    },
    "education": {
        "prefixes": ["41", "42", "43"],
        "label": "education and social services",
        "description": "Teachers, social workers, education assistants"
    },
    "business": {
        "prefixes": ["11", "12", "13", "14"],
        "label": "business and administration",
        "description": "Finance, HR, administration, business services"
    },
    "sales_service": {
        "prefixes": ["62", "63", "64", "65"],
        "label": "sales and service",
        "description": "Retail, food service, customer service, hospitality"
    },
    "labour": {
        "prefixes": ["95"],
        "label": "manufacturing and labour",
        "description": "Processing, manufacturing, general labour"
    },
}


CATEGORY_PATTERNS = {
    "trades": ["trade", "trades", "trade job", "trades job", "construction job",
               "skilled trade", "skilled trades"],
    "health": ["health", "healthcare", "health care", "medical", "nursing job",
               "health job"],
    "tech": ["tech", "technology", "software", "it job", "tech job",
             "engineering job", "computer"],
    "education": ["education job", "teaching job", "social work"],
    "business": ["business job", "administrative", "office job", "finance job"],
    "sales_service": ["retail", "hospitality", "service job", "food service"],
    "labour": ["labour", "labor", "manufacturing", "warehouse"],
}

# Career-to-comparison map — only careers with well-known peers get a "compare" suggestion
# Keys are lowercase keywords found in the career title
COMPARISON_SUGGESTIONS = {
    # Healthcare — Nursing
    "registered nurse":          "Compare registered nurse and licensed practical nurse",
    "registered psychiatric":    "Compare registered nurse and licensed practical nurse",
    "licensed practical nurse":  "Compare registered nurse and licensed practical nurse",
    "nurse practitioner":        "Compare nurse practitioner and registered nurse",
    "nurse aide":                "Compare nurse aide and licensed practical nurse",
    "nursing coordinator":       "Compare nursing coordinator and registered nurse",

    # Healthcare — Pharmacy
    "pharmacist":                "Compare pharmacist and pharmacy technician",
    "pharmacy technician":       "Compare pharmacist and pharmacy technician",
    "pharmacy assistant":        "Compare pharmacy technician and pharmacy assistant",

    # Healthcare — Dental
    "dental hygienist":          "Compare dental hygienist and dental assistant",
    "dental assistant":          "Compare dental hygienist and dental assistant",
    "denturist":                 "Compare denturist and dental technologist",
    "dental technologist":       "Compare dental hygienist and dental technologist",

    # Healthcare — Therapy
    "physiotherapist":           "Compare physiotherapist and occupational therapist",
    "occupational therapist":    "Compare physiotherapist and occupational therapist",
    "massage therapist":         "Compare massage therapist and physiotherapist",
    "chiropractor":              "Compare chiropractor and physiotherapist",
    "kinesiologist":             "Compare kinesiologist and physiotherapist",

    # Healthcare — Vision
    "optometrist":               "Compare optometrist and optician",
    "optician":                  "Compare optometrist and optician",

    # Healthcare — Other
    "general practitioner":      "Compare general practitioner and nurse practitioner",
    "family physician":          "Compare family physician and nurse practitioner",
    "specialists surgery":       "Compare surgeon and general practitioner",
    "specialists clinical":      "Compare specialist physician and general practitioner",
    "veterinarian":              "Compare veterinarian and animal health technologist",
    "animal health":             "Compare veterinarian and animal health technologist",
    "midwives":                  "Compare midwife and registered nurse",
    "audiologist":               "Compare audiologist and speech language pathologist",
    "speech language":           "Compare audiologist and speech language pathologist",
    "respiratory therapist":     "Compare respiratory therapist and registered nurse",
    "medical radiation":         "Compare medical radiation technologist and medical sonographer",
    "medical sonographer":       "Compare medical sonographer and medical radiation technologist",
    "paramedical":               "Compare paramedic and firefighter",
    "dietitian":                 "Compare dietitian and nutritionist",

    # Trades — Electrical
    "electrician":               "Compare electrician and industrial electrician",
    "industrial electrician":    "Compare electrician and industrial electrician",
    "power system electrician":  "Compare power system electrician and industrial electrician",
    "electrical mechanic":       "Compare electrical mechanic and electrician",
    "electrical power line":     "Compare electrical power line worker and electrician",

    # Trades — Plumbing/Piping
    "plumber":                   "Compare plumber and steamfitter",
    "steamfitter":               "Compare plumber and steamfitter",
    "pipefitter":                "Compare plumber and steamfitter",
    "gas fitter":                "Compare gas fitter and plumber",

    # Trades — Carpentry/Construction
    "carpenter":                 "Compare carpenter and cabinetmaker",
    "cabinetmaker":              "Compare carpenter and cabinetmaker",
    "bricklayer":                "Compare bricklayer and carpenter",
    "roofer":                    "Compare roofer and carpenter",
    "tilesetter":                "Compare tilesetter and carpenter",
    "drywall":                   "Compare drywall installer and carpenter",
    "plasterer":                 "Compare plasterer and drywall installer",
    "concrete finisher":         "Compare concrete finisher and bricklayer",
    "floor covering":            "Compare floor covering installer and carpenter",
    "ironworker":                "Compare ironworker and welder",
    "construction trades helper": "Compare construction trades helper and carpenter",
    "glazier":                   "Compare glazier and carpenter",
    "insulator":                 "Compare insulator and roofer",
    "sheet metal worker":        "Compare sheet metal worker and ironworker",

    # Trades — Mechanical
    "welder":                    "Compare welder and steamfitter",
    "machinist":                 "Compare machinist and tool and die maker",
    "tool and die":              "Compare tool and die maker and machinist",
    "millwright":                "Compare construction millwright and industrial mechanic",
    "industrial mechanic":       "Compare construction millwright and industrial mechanic",
    "automotive service":        "Compare automotive service technician and heavy duty equipment mechanic",
    "heavy duty equipment":      "Compare heavy duty equipment mechanic and automotive service technician",
    "auto body":                 "Compare auto body technician and automotive service technician",
    "heating refrigeration":     "Compare HVAC mechanic and electrician",
    "boilermaker":               "Compare boilermaker and welder",
    "elevator":                  "Compare elevator constructor and electrician",
    "aircraft mechanic":         "Compare aircraft mechanic and aircraft assembler",
    "appliance servicer":        "Compare appliance servicer and HVAC mechanic",
    "crane operator":            "Compare crane operator and heavy equipment operator",
    "heavy equipment operator":  "Compare heavy equipment operator and crane operator",

    # Public Safety
    "firefighter":               "Compare firefighter and paramedic",
    "police officer":            "Compare police officer and firefighter",
    "commissioned police":       "Compare police officer and commissioned police officer",
    "correctional service":      "Compare correctional service officer and police officer",
    "sheriff":                   "Compare sheriff and correctional service officer",
    "bailiff":                   "Compare sheriff and bailiff",
    "border services":           "Compare border services officer and police officer",
    "security guard":            "Compare security guard and police officer",
    "fire chief":                "Compare fire chief and firefighter",
    "police investigator":       "Compare police investigator and police officer",

    # Education
    "elementary school":         "Compare elementary and secondary school teacher",
    "kindergarten":              "Compare elementary and secondary school teacher",
    "secondary school teacher":  "Compare elementary and secondary school teacher",
    "teacher assistant":         "Compare teacher and teacher assistant",
    "early childhood educator":  "Compare early childhood educator and elementary school teacher",
    "college and other vocational": "Compare college instructor and university professor",
    "university professor":      "Compare university professor and college instructor",
    "school principal":          "Compare school principal and teacher",
    "educational counsellor":    "Compare educational counsellor and career development practitioner",
    "career development":        "Compare career development practitioner and educational counsellor",

    # Tech
    "software engineer":         "Compare software engineer and software developer",
    "software developer":        "Compare software developer and web developer",
    "web developer":             "Compare web developer and web designer",
    "web designer":              "Compare web designer and graphic designer",
    "computer systems developer": "Compare computer systems developer and software developer",
    "data scientist":            "Compare data scientist and database analyst",
    "database analyst":          "Compare database analyst and data scientist",
    "cybersecurity":             "Compare cybersecurity specialist and information systems specialist",
    "information systems specialist": "Compare information systems specialist and database analyst",
    "computer engineer":         "Compare computer engineer and software engineer",
    "user support technician":   "Compare user support technician and computer network technician",
    "computer network":          "Compare computer network technician and user support technician",
    "business system specialist": "Compare business system specialist and information systems specialist",

    # Finance/Business
    "financial auditor":         "Compare financial auditor and accounting technician",
    "accountant":                "Compare accountant and bookkeeper",
    "accounting technician":     "Compare accountant and bookkeeper",
    "bookkeeper":                "Compare accountant and bookkeeper",
    "financial advisor":         "Compare financial advisor and financial investment analyst",
    "financial and investment":  "Compare financial advisor and financial investment analyst",
    "securities agent":          "Compare securities agent and financial advisor",
    "insurance agent":           "Compare insurance agent and insurance adjuster",
    "insurance adjuster":        "Compare insurance agent and insurance adjuster",
    "insurance underwriter":     "Compare insurance underwriter and insurance agent",
    "real estate agent":         "Compare real estate agent and insurance agent",
    "economist":                 "Compare economist and financial analyst",
    "human resources professional": "Compare human resources professional and human resources manager",

    # Engineering
    "civil engineer":            "Compare civil engineer and civil engineering technologist",
    "mechanical engineer":       "Compare mechanical engineer and mechanical engineering technologist",
    "electrical and electronics engineer": "Compare electrical engineer and electrical engineering technologist",
    "aerospace engineer":        "Compare aerospace engineer and mechanical engineer",
    "chemical engineer":         "Compare chemical engineer and petroleum engineer",
    "petroleum engineer":        "Compare petroleum engineer and chemical engineer",
    "mining engineer":           "Compare mining engineer and geological engineer",
    "geological engineer":       "Compare geological engineer and mining engineer",
    "metallurgical":             "Compare metallurgical engineer and chemical engineer",
    "industrial and manufacturing engineer": "Compare industrial engineer and mechanical engineer",
    "engineering inspector":     "Compare engineering inspector and construction inspector",
    "construction inspector":    "Compare construction inspector and engineering inspector",

    # Social Services
    "social worker":             "Compare social worker and community service worker",
    "social and community":      "Compare social worker and community service worker",
    "psychologist":              "Compare psychologist and therapist",
    "therapists counselling":    "Compare therapist and psychologist",
    "probation and parole":      "Compare probation officer and correctional service officer",

    # Food Service
    "chef":                      "Compare chef and cook",
    "cook":                      "Compare chef and cook",
    "baker":                     "Compare baker and chef",
    "restaurant and food service manager": "Compare restaurant manager and food service supervisor",
    "food service supervisor":   "Compare restaurant manager and food service supervisor",
    "butcher":                   "Compare butcher and meat cutter",
    "meat cutter":               "Compare butcher and meat cutter",
    "bartender":                 "Compare bartender and food and beverage server",
    "food and beverage server":  "Compare food server and bartender",

    # Creative/Media
    "graphic designer":          "Compare graphic designer and web designer",
    "interior designer":         "Compare interior designer and graphic designer",
    "industrial designer":       "Compare industrial designer and graphic designer",
    "photographer":              "Compare photographer and film camera operator",
    "film and video camera":     "Compare film camera operator and photographer",
    "author":                    "Compare author and journalist",
    "journalist":                "Compare journalist and editor",
    "editor":                    "Compare editor and journalist",
    "technical writer":          "Compare technical writer and author",
    "translator":                "Compare translator and editor",
    "broadcast technician":      "Compare broadcast technician and audio video technician",
    "audio and video":           "Compare audio video technician and broadcast technician",
    "musicians and singers":     "Compare musician and announcer",
    "announcers":                "Compare announcer and broadcast technician",

    # Transportation
    "transport truck driver":    "Compare transport truck driver and bus driver",
    "bus driver":                "Compare bus driver and taxi driver",
    "taxi":                      "Compare taxi driver and bus driver",
    "delivery service driver":   "Compare delivery driver and transport truck driver",
    "air pilot":                 "Compare air pilot and air traffic controller",
    "air traffic controller":    "Compare air traffic controller and air pilot",
    "deck officer":              "Compare deck officer and engineer officer water transport",
    "railway and yard locomotive": "Compare locomotive engineer and railway conductor",
    "railway conductor":         "Compare railway conductor and locomotive engineer",
    "pursers and flight":        "Compare flight attendant and travel counsellor",
    "airline ticket":            "Compare airline ticket agent and travel counsellor",
    "travel counsellor":         "Compare travel counsellor and airline ticket agent",
    "tour and travel":           "Compare tour guide and travel counsellor",

    # Sales/Customer Service
    "retail salesperson":        "Compare retail salesperson and retail sales supervisor",
    "retail sales supervisor":   "Compare retail sales supervisor and retail manager",
    "retail and wholesale trade manager": "Compare retail manager and retail sales supervisor",
    "cashier":                   "Compare cashier and retail salesperson",
    "technical sales":           "Compare technical sales specialist and retail salesperson",

    # Office/Admin
    "administrative officer":    "Compare administrative officer and executive assistant",
    "executive assistant":       "Compare executive assistant and administrative assistant",
    "medical administrative":    "Compare medical administrative assistant and administrative assistant",
    "legal administrative":      "Compare legal administrative assistant and paralegal",
    "data entry clerk":          "Compare data entry clerk and administrative assistant",
    "receptionist":              "Compare receptionist and administrative assistant",

    # Personal Services
    "hairstylist":               "Compare hairstylist and esthetician",
    "esthetician":                "Compare esthetician and hairstylist",
    "funeral director":          "Compare funeral director and embalmer",

    # Other notable
    "lawyer":                    "Compare lawyer and paralegal",
    "paralegal":                 "Compare lawyer and paralegal",
    "judge":                     "Compare judge and lawyer",
    "architect":                 "Compare architect and architectural technologist",
    "landscape architect":       "Compare landscape architect and architect",
    "urban and land use":        "Compare urban planner and architect",
    "biologist":                 "Compare biologist and chemist",
    "chemist":                   "Compare chemist and biologist",
    "geoscientist":              "Compare geoscientist and geological engineer",
    "physicist":                 "Compare physicist and astronomer",
    "mathematician":             "Compare mathematician and statistician",
    "land surveyor":             "Compare land surveyor and land survey technologist",
    "librarian":                 "Compare librarian and library technician",
    "archivist":                 "Compare archivist and librarian",
}




# Abbreviations and casual names that do NOT appear as substrings
# in the actual EmployerName stored in OpenSearch.
EMPLOYER_ALIASES = {
    # Provincial government
    "bc provincial":            "Province of British Columbia",
    "bc government":            "Province of British Columbia",
    "provincial government":    "Province of British Columbia",
    "bc public service":        "Province of British Columbia",
    "province of bc":           "Province of British Columbia",
    # Health authorities
    "vch":                      "Vancouver Coastal Health",
    "phsa":                     "Provincial Health Services Authority",
    "interior health authority": "Interior Health",
    "island health authority":  "Island Health",
    "northern health authority": "Northern Health",
    # Education abbreviations
    "ubc":                      "University of British Columbia",
    "uvic":                     "University of Victoria",
    "tru":                      "Thompson Rivers University",
    "vcc":                      "Vancouver Community College",
    # School district abbreviations (SD + number)
    "sd5":  "School District #5",  "sd6":  "School District #6",
    "sd8":  "School District #8",  "sd22": "School District #22",
    "sd23": "School District #23", "sd27": "School District #27",
    "sd28": "School District #28", "sd33": "School District #33",
    "sd35": "School District #35", "sd36": "School District #36",
    "sd38": "School District #38", "sd39": "School District #39",
    "sd41": "School District #41", "sd42": "School District #42",
    "sd43": "School District #43", "sd44": "School District #44",
    "sd48": "School District #48", "sd57": "School District 57",
    "sd58": "School District #58", "sd60": "School District #60",
    "sd62": "School District #62", "sd63": "School District #63",
    "sd69": "School District #69", "sd71": "School District #71",
    "sd75": "School District #75", "sd79": "School District #79",
    "sd82": "School District #82", "sd83": "School District #83",
    "sd91": "School District #91", "sd92": "School District #92",
    "sd93": "School District #93",
    # Crown corporations
    "bc ferries":               "BC Ferries",
    "bc hydro":                 "BC Hydro",
    # Common abbreviations
    "spca":                     "BC SPCA",
    "bc spca":                  "BC SPCA",
}

# City name normalization for duplicate/abbreviated entries
CITY_ALIASES = {
    "fort st john":   "Fort St. John",
    "north van":      "North Vancouver",
    "west van":       "West Vancouver",
    "poco":           "Port Coquitlam",
    "new west":       "New Westminster",
    "pitt meadow":    "Pitt Meadows",
}

FOLLOW_UP_WORDS = {
    "what about", "how about", "how does", "how do", "compare",
    "difference", "versus", "vs", "same", "similar", "also",
    "and what", "tell me more", "more about", "elaborate",
}

# Keywords that trigger the full-text match fallback when wildcard returns 0
FALLBACK_KEYWORDS = {
    "school", "district", "university", "college", "institute", "academy",
    "health", "authority", "provincial", "government", "ministry",
}

# Maps lowercase keywords (substring match) to NOC codes that must always appear
# in results for that query, even if ChromaDB doesn't rank them high enough.
CAREER_SEARCH_ALIASES: dict[str, list[str]] = {
    "business analyst": ["11201", "21221"],
}

app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ---------------------------------------------------------------------------
# 3. INITIALIZATION
# ---------------------------------------------------------------------------
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device="cpu")

vllm_client = OpenAI(
    base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1",
    api_key="none",
    timeout=120.0,
)

MODEL_NAME    = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"
chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection    = chroma_client.get_collection("career_content")
r             = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)

os_client = OpenSearch(
    hosts=[OPENSEARCH_SERVER],
    http_auth=(OPENSEARCH_USER, OPENSEARCH_PASS),
    use_ssl=OPENSEARCH_SERVER.startswith("https"),
    verify_certs=True,
)

# ---------------------------------------------------------------------------
# 4. REQUEST MODEL
# ---------------------------------------------------------------------------
class QueryRequest(BaseModel):
    prompt: str
    session_id: str

class ClearSessionRequest(BaseModel):    
    session_id: str

class FeedbackRequest(BaseModel):
    session_id: str
    message_id: str          # unique ID per bot response
    user_query: str          # the original user question
    bot_response: str        # the bot's answer
    rating: str              # "up" or "down"
    comment: str | None = None   # optional explanation
    intent: str | None = None    # what intent the bot classified
    timestamp: str | None = None # client-side timestamp

# ---------------------------------------------------------------------------
# 5. HELPERS
# ---------------------------------------------------------------------------

def expand_synonyms(query: str) -> str:
    """
    Expand common synonyms and abbreviations to canonical WorkBC terms.
    Operates on whole-word matches only to avoid false positives.
    """
    if not query:
        return query

    # Process longest matches first to handle multi-word synonyms
    sorted_synonyms = sorted(CAREER_SYNONYMS.items(), key=lambda x: -len(x[0]))

    result = query
    for synonym, canonical in sorted_synonyms:
        # Whole-word boundary match (case-insensitive)
        pattern = r'\b' + re.escape(synonym) + r'\b'
        if re.search(pattern, result, re.IGNORECASE):
            result = re.sub(pattern, canonical, result, flags=re.IGNORECASE)
            print(f"DEBUG: Expanded synonym '{synonym}' → '{canonical}'")

    return result

def format_video_section(noc: str) -> str:
    """Return a markdown video section for a given NOC, or empty string if none."""
    videos = VIDEO_MAP.get(str(noc), [])
    if not videos:
        return ""

    lines = ["\n\n🎥 **Watch a real BC professional at work:**\n"]
    for v in videos[:2]:  # Cap at 2 videos to keep response concise
        episode_label = f"Episode {v['episode_num']}: {v['episode_title']}"
        location_label = f"{v['location']}, {v['region']}" if v.get('location') else ""

        lines.append(
            f"- [{episode_label}]({v['youtube_link']})"
            + (f" — *{location_label}*" if location_label else "")
        )

        if v.get("description"):
            desc = v["description"][:200]
            if len(v["description"]) > 200:
                desc += "…"
            lines.append(f"  *{desc}*")

    return "\n".join(lines)

def extract_primary_noc_from_answer(answer: str) -> str | None:
    """Extract the primary NOC code mentioned in a career_info response."""
    matches = re.findall(r'NOC[:\s]+(\d{5})', answer, re.IGNORECASE)
    return matches[0] if matches else None


def build_noc_title_map():
    """Build NOC → title/url lookup from ChromaDB metadata at startup."""
    global NOC_TITLE_MAP
    try:
        all_data = collection.get(include=["metadatas"])
        for meta in all_data.get("metadatas", []):
            noc = meta.get("noc_code")
            title = meta.get("job_title")
            url = meta.get("url")
            if noc and title and noc not in NOC_TITLE_MAP:
                NOC_TITLE_MAP[str(noc)] = {
                    "title": title,
                    "url": url,
                }
        print(f"DEBUG: Built NOC title map with {len(NOC_TITLE_MAP)} entries")
    except Exception as e:
        print(f"WARN: Failed to build NOC map: {e}")

build_noc_title_map()

VIDEO_MAP = {}

def build_video_map():
    """Load Career Trek videos from PostgreSQL at startup or refresh."""
    global VIDEO_MAP

    if not POSTGRES_HOST:
        print("WARN: POSTGRES_HOST not configured — Career Trek videos disabled")
        return

    new_map = {}
    conn = None
    try:
        conn = psycopg2.connect(
            host=POSTGRES_HOST,
            port=POSTGRES_PORT,
            dbname=POSTGRES_DB,
            user=POSTGRES_USER,
            password=POSTGRES_PASSWORD,
            connect_timeout=5,
        )

        with conn.cursor(cursor_factory=RealDictCursor) as cur:
            cur.execute("""
                SELECT
                    episode_num,
                    episode_title,
                    noc_2021,
                    title_2021,
                    youtube_link,
                    location,
                    region,
                    description
                FROM career_trek
                WHERE noc_2021 IS NOT NULL
                  AND youtube_link IS NOT NULL
                ORDER BY episode_num DESC
            """)

            for row in cur.fetchall():
                noc = str(row["noc_2021"]).strip()
                if not noc:
                    continue

                video_entry = {
                    "episode_num":   str(row["episode_num"] or "").strip(),
                    "episode_title": (row["episode_title"] or "").strip(),
                    "title_2021":    (row["title_2021"] or "").strip(),
                    "youtube_link":  (row["youtube_link"] or "").strip(),
                    "location":      (row["location"] or "").strip(),
                    "region":        (row["region"] or "").strip(),
                    "description":   (row["description"] or "").strip(),
                }
                new_map.setdefault(noc, []).append(video_entry)

        # Atomic swap — only replace if successful
        VIDEO_MAP = new_map
        total_videos = sum(len(v) for v in VIDEO_MAP.values())
        print(f"DEBUG: Built video map from PostgreSQL — "
              f"{len(VIDEO_MAP)} NOC codes covered, {total_videos} total videos")

    except psycopg2.Error as e:
        print(f"WARN: PostgreSQL error loading videos: {e}")
    except Exception as e:
        print(f"WARN: Failed to load Career Trek videos: {e}")
    finally:
        if conn:
            conn.close()

# Call at startup
build_video_map()

def is_hiring_trends_query(normalized: str) -> bool:
    """
    Detect hiring trend queries via two strategies:
    1. Explicit phrase patterns (e.g. "what's hiring")
    2. Prefix + suffix pattern (e.g. "top trade jobs", "best tech careers")
    """
    if any(pattern in normalized for pattern in HIRING_TRENDS_PATTERNS):
        return True

    # Match "top|best|most ... jobs|careers|hiring|openings"
    starts_with_prefix = any(normalized.startswith(p + " ") for p in HIRING_PREFIX_WORDS)
    has_suffix = any(s in normalized for s in HIRING_SUFFIX_WORDS)
    if starts_with_prefix and has_suffix:
        return True

    return False

def search_jobs_by_city(params: dict, cities: list) -> tuple[dict, int]:
    """Returns job counts per city using OpenSearch aggregation."""
    must_clauses   = []
    filter_clauses = [
        {"range": {"ExpireDate": {"gte": "now"}}},
        {"terms": {"City.keyword": cities}},
    ]

    if params.get("salary_min"):
        filter_clauses.append({"range": {"Salary": {"gte": params["salary_min"]}}})

    if params.get("employment_type"):
        filter_clauses.append({"term": {"HoursOfWork.Description": params["employment_type"]}})

    if params.get("keywords"):
        generic = {"jobs", "job", "work", "position", "positions", "opening", "openings"}
        clean = " ".join(w for w in params["keywords"].split()
                        if w.lower() not in DATE_SORT_KEYWORDS).strip()
        if clean and clean.lower() not in generic:
            if len(clean.split()) > 1:
                must_clauses.append({
                    "multi_match": {
                        "query":  clean,
                        "fields": ["Title^3", "JobDescription"],
                        "type":   "phrase",
                        "slop":   2,
                    }
                })
            else:
                must_clauses.append({
                    "multi_match": {
                        "query":  clean,
                        "fields": ["Title^3", "JobDescription"],
                    }
                })

    if params.get("employer"):
        filter_clauses.append(
            {"wildcard": {"EmployerName.keyword": f"*{params['employer']}*"}}
        )

    os_query = {
        "query": {
            "bool": {
                "must":   must_clauses if must_clauses else [{"match_all": {}}],
                "filter": filter_clauses,
            }
        },
        "size": 0,
        "aggs": {
            "by_city": {
                "terms": {
                    "field": "City.keyword",
                    "size":  len(cities),
                    "order": {"_count": "desc"},
                }
            }
        },
    }

    print(f"DEBUG: Aggregation query: {json.dumps(os_query, indent=2)}")
    response = os_client.search(index="jobs_en", body=os_query)
    total    = response["hits"]["total"]["value"]
    buckets  = response["aggregations"]["by_city"]["buckets"]
    city_counts = {b["key"]: b["doc_count"] for b in buckets}
    print(f"DEBUG: Aggregation results: {city_counts}")

    # Fallback: if wildcard returned 0 and employer contains fallback keywords
    if total == 0 and params.get("employer"):
        employer_lower = params["employer"].lower()
        if any(kw in employer_lower for kw in FALLBACK_KEYWORDS):
            print(f"DEBUG: Aggregation wildcard returned 0 — retrying with full-text match")
            fallback_filter = [
                {"range": {"ExpireDate": {"gte": "now"}}},
                {"terms": {"City.keyword": cities}},
            ]

            if params.get("salary_min"):
                fallback_filter.append({"range": {"Salary": {"gte": params["salary_min"]}}})

            if params.get("employment_type"):
                fallback_filter.append({"term": {"HoursOfWork.Description": params["employment_type"]}})
                
            fallback_must = [{
                "match": {
                    "EmployerName": {
                        "query":    params["employer"],
                        "operator": "and",
                    }
                }
            }]
            if params.get("keywords"):
                clean = " ".join(w for w in params["keywords"].split()
                                if w.lower() not in DATE_SORT_KEYWORDS).strip()
                generic = {"jobs", "job", "work", "position", "positions", "opening", "openings"}
                if clean and clean.lower() not in generic:
                    if len(clean.split()) > 1:
                        fallback_must.append({
                            "multi_match": {
                                "query":  clean,
                                "fields": ["Title^3", "JobDescription"],
                                "type":   "phrase",
                                "slop":   2,
                            }
                        })
                    else:
                        fallback_must.append({
                            "multi_match": {
                                "query":  clean,
                                "fields": ["Title^3", "JobDescription"],
                            }
                        })

            fallback_query = {
                "query": {
                    "bool": {
                        "must":   fallback_must,
                        "filter": fallback_filter,
                    }
                },
                "size": 0,
                "aggs": {
                    "by_city": {
                        "terms": {
                            "field": "City.keyword",
                            "size":  len(cities),
                            "order": {"_count": "desc"},
                        }
                    }
                },
            }

            print(f"DEBUG: Aggregation fallback query: {json.dumps(fallback_query, indent=2)}")
            response = os_client.search(index="jobs_en", body=fallback_query)
            total    = response["hits"]["total"]["value"]
            buckets  = response["aggregations"]["by_city"]["buckets"]
            city_counts = {b["key"]: b["doc_count"] for b in buckets}
            print(f"DEBUG: Aggregation fallback results: {city_counts}")

    return city_counts, total
    


def format_city_bar_chart(city_counts: dict, total: int, keyword: str) -> str:
    """Render a text-based bar chart of job counts by city."""
    if not city_counts:
        return f"No **{keyword}** jobs found in those cities."

    max_count = max(city_counts.values())
    max_bar   = 20

    lines = [f"Found **{total}** {keyword} jobs across {len(city_counts)} cities:\n"]
    lines.append("```")
    for city, count in sorted(city_counts.items(), key=lambda x: -x[1]):
        bar_len = max(1, int((count / max_count) * max_bar)) if max_count > 0 else 0
        bar     = "█" * bar_len
        lines.append(f"{city:<22} {bar} {count}")
    lines.append("```")

    return "\n".join(lines)

HIRING_TRENDS_PATTERNS = [
    "what's hiring", "what is hiring", "whats hiring",
    "most in demand", "in demand jobs", "in demand careers",
    "hiring most", "hot jobs", "trending careers",
    "most openings", "which career has most",
    "top hiring", "most hired",
    "what careers are hiring", "what jobs are hiring",
]

HIRING_PREFIX_WORDS = ["top", "best", "most"]
HIRING_SUFFIX_WORDS = [" jobs", " careers", " hiring", " openings", " roles", " positions"]


def get_top_hiring_careers(limit: int = 10, category: str = None) -> tuple[list, int, str]:
    """
    Aggregate active job postings by NOC code, optionally filtered by category.

    Returns:
        results: [{noc, title, url, count}, ...] sorted by count desc
        total: total postings considered
        category_label: human-readable category name or None
    """
    filter_clauses = [{"range": {"ExpireDate": {"gte": "now"}}}]

    category_label = None
    if category and category in NOC_CATEGORY_PREFIXES:
        cat_info = NOC_CATEGORY_PREFIXES[category]
        category_label = cat_info["label"]

        # NOC stored as float — build numeric ranges per prefix
        # e.g. prefix "72" matches NOCs 72000.0 - 72999.0
        prefix_ranges = []
        for prefix in cat_info["prefixes"]:
            lower = float(prefix + "000")
            upper = float(prefix + "999")
            prefix_ranges.append({
                "range": {
                    "Noc2021": {"gte": lower, "lte": upper}
                }
            })

        filter_clauses.append({
            "bool": {"should": prefix_ranges, "minimum_should_match": 1}
        })

    os_query = {
        "size": 0,
        "track_total_hits": True,
        "query": {
            "bool": {"filter": filter_clauses}
        },
        "aggs": {
            "by_noc": {
                "terms": {
                    "field": "Noc2021",
                    "size":  limit,
                    "order": {"_count": "desc"},
                }
            }
        },
    }

    print(f"DEBUG: Top hiring query (category={category}): {json.dumps(os_query)}")
    response = os_client.search(index="jobs_en", body=os_query)
    total = response["hits"]["total"]["value"]
    buckets = response["aggregations"]["by_noc"]["buckets"]

    results = []
    for bucket in buckets:
        noc_str = str(int(bucket["key"]))
        career_info = NOC_TITLE_MAP.get(noc_str, {})
        results.append({
            "noc":   noc_str,
            "title": career_info.get("title", f"NOC {noc_str}"),
            "url":   career_info.get("url"),
            "count": bucket["doc_count"],
        })

    print(f"DEBUG: Top hiring results: {[(r['title'], r['count']) for r in results]}")
    return results, total, category_label


def format_top_hiring_chart(results: list, total: int, category_label: str = None) -> str:
    """Render top hiring careers as a markdown bar chart."""
    if not results:
        scope = f" in {category_label}" if category_label else ""
        return f"I couldn't find any active job postings{scope} right now."

    max_count = max(r["count"] for r in results)
    max_bar = 20

    if category_label:
        header = (f"**Top {len(results)} {category_label} careers hiring in BC** "
                  f"(out of {total:,} active postings):\n")
    else:
        header = (f"**Top {len(results)} careers hiring in BC right now** "
                  f"(out of {total:,} active postings):\n")

    lines = [header, "```"]
    for r in results:
        bar_len = max(1, int((r["count"] / max_count) * max_bar))
        bar = "█" * bar_len
        title_short = r["title"][:35] + "…" if len(r["title"]) > 36 else r["title"]
        lines.append(f"{title_short:<37} {bar} {r['count']}")
    lines.append("```")
    lines.append("\n*Counts reflect current open job postings on WorkBC. "
                 "Ask about any of these careers to learn more.*")
    return "\n".join(lines)

def detect_chunk_types(user_query: str) -> list[str]:
    """
    Determine which chunk types are needed based on the question.
    Returns a list of chunk_types to retrieve from ChromaDB.
    """
    q = user_query.lower()

    # Comparison queries — Overview has salary + summary
    if any(w in q for w in ["compare", "difference", "versus", "vs", "between"]):
        return ["Overview"]

    # Salary questions — salary is in Overview metadata
    if any(w in q for w in ["salary", "pay", "earn", "income", "wage", "how much"]):
        return ["Overview"]

    # Duty/responsibility questions
    if any(w in q for w in ["duties", "do", "does", "responsibilities", "role", "tasks", "day to day"]):
        return ["Duties"]

    # Education/requirement questions
    if any(w in q for w in ["education", "requirement", "qualification", "training",
                             "certification", "degree", "school", "become"]):
        return ["Education"]

    # General questions — Overview gives the best broad answer
    return ["Overview", "Duties"]

def strip_html(text: str) -> str:
    if not text:
        return ""
    text = re.sub(r'<[^>]+>', ' ', text)
    text = text.replace('&amp;', '&').replace('&lt;', '<').replace('&gt;', '>')
    text = text.replace('&nbsp;', ' ').replace('&#39;', "'").replace('&quot;', '"')
    return re.sub(r'\s+', ' ', text).strip()


def clean_city(city: str) -> str:
    if not city:
        return city
    for suffix in CITY_PROVINCE_SUFFIXES:
        if city.upper().endswith(suffix.upper()):
            city = city[:len(city) - len(suffix)].strip()
            break
    return city


def resolve_city(city: str) -> str:
    if not city:
        return city
    return CITY_ALIASES.get(city.lower().strip(), city)


def resolve_employer(employer: str) -> str:
    if not employer:
        return employer
    lookup = employer.lower().strip()
    if lookup in EMPLOYER_ALIASES:
        resolved = EMPLOYER_ALIASES[lookup]
        print(f"DEBUG: Employer alias resolved: '{employer}' -> '{resolved}'")
        return resolved
    # Handle "SD 36", "SD #36", "SD36" style inputs
    sd_match = re.match(r'^sd\s*#?\s*(\d+)$', lookup)
    if sd_match:
        sd_key = f"sd{sd_match.group(1)}"
        if sd_key in EMPLOYER_ALIASES:
            resolved = EMPLOYER_ALIASES[sd_key]
            print(f"DEBUG: School district alias resolved: '{employer}' -> '{resolved}'")
            return resolved
    return employer.title()


def fix_city_of_misclassification(params: dict) -> dict:
    city = params.get("city") or ""
    if city.lower().startswith("city of ") and not params.get("employer"):
        print(f"DEBUG: Moving '{city}' from city to employer field")
        params["employer"] = city
        params["city"]     = None
    return params


def parse_intent(raw: str) -> dict:
    cleaned = (
        raw.strip()
           .removeprefix("```json").removeprefix("```")
           .removesuffix("```").strip()
           .replace("\\_", "_")
    )
    parsed = json.loads(cleaned)
    parsed["intent"] = parsed.get("intent") or "career_info"
    return parsed


BAD_URL_FRAGMENTS = ["dev2.workbc.ca", "/#", "#/", "localhost"]


def build_job_url(src: dict) -> str:
    job_id     = src.get("JobId", "")
    workbc_url = f"{WORKBC_BASE_URL}/search-and-prepare-job/find-jobs#/job-details/{job_id}"
    apply_website = (src.get("ApplyWebsite") or "").strip()
    if apply_website and not any(bad in apply_website for bad in BAD_URL_FRAGMENTS):
        return apply_website
    external_url = (
        (src.get("ExternalSource") or {})
        .get("Source", [{}])[0]
        .get("Url", "").strip()
    )
    if external_url:
        return external_url
    return workbc_url


def is_out_of_scope(city: str) -> bool:
    return city.upper().strip() in OUT_OF_SCOPE_LOCATIONS


def needs_date_sort(params: dict) -> bool:
    keywords = (params.get("keywords") or "").lower()
    return any(kw in keywords for kw in DATE_SORT_KEYWORDS)


def is_follow_up_query(user_query: str) -> bool:
    q = user_query.lower().strip()
    if len(q.split()) <= 6 and any(w in q for w in FOLLOW_UP_WORDS):
        return True
    follow_up_starts = ["what about", "how about", "and what", "what is the", "how does", "how do"]
    if any(q.startswith(s) for s in follow_up_starts) and len(q.split()) <= 8:
        return True
    return False


def looks_like_question(text: str) -> bool:
    t = text.lower().strip()
    return "?" in t or t.startswith(("what", "how", "why", "when", "where", "can", "could", "would"))


def format_job_results(jobs: list, params: dict, total: int) -> str:
    employer = params.get("employer")
    city     = params.get("city")
    keywords = params.get("keywords")

    if city and is_out_of_scope(city):
        keyword_str = f"**{employer or keywords}**" if (employer or keywords) else "jobs"
        return (
            f"WorkBC's job bank only contains **British Columbia** postings — "
            f"I can't search for jobs in **{city}**.\n\n"
            f"Try asking for {keyword_str} in a BC city like "
            f"Vancouver, Surrey, Kelowna or Victoria, "
            f"or search without a location to see all BC postings."
        )
    if not jobs:
        if employer and city:
            return (
                f"I couldn't find any current **{employer}** postings in **{city}**. "
                f"Their listings may have expired or they may not be actively hiring right now.\n\n"
                f"Try asking: *{employer} jobs* to see all their BC postings."
            )
        elif employer:
            return (
                f"I couldn't find any current **{employer}** postings in WorkBC's job bank. "
                f"Their listings may have expired or they may not be actively hiring right now.\n\n"
                f"Check back later or visit [WorkBC Job Bank]"
                f"({WORKBC_BASE_URL}/search-and-prepare-job/find-jobs) directly."
            )
        else:
            return (
                "I searched WorkBC's job bank but couldn't find any current postings "
                "matching your request. Try broader keywords or a different location."
            )

    location_str = f" in **{city}**"  if city     else ""
    keyword_str  = f"**{employer}**"  if employer else \
                   f"**{keywords}**"  if keywords else "your search"
    return f"Found **{total}** job postings for {keyword_str}{location_str}:"


def _parse_os_hits(hits: list) -> list:
    jobs = []
    for hit in hits:
        src = hit["_source"]
        jobs.append({
            "job_id":          src.get("JobId"),
            "title":           src.get("Title"),
            "employer":        src.get("EmployerName"),
            "city":            (src.get("City")                                      or [None])[0],
            "salary":          src.get("SalarySummary", "Not specified"),
            "hours":           (src.get("HoursOfWork",        {}).get("Description") or [None])[0],
            "employment_type": (src.get("PeriodOfEmployment", {}).get("Description") or [None])[0],
            "noc_code":        src.get("Noc2021"),
            "industry":        src.get("Industry"),
            "url":             build_job_url(src),
            "description":     strip_html(src.get("JobDescription", ""))[:200],
        })
    return jobs


def _build_common_filters(params: dict) -> list:
    """Build filter clauses shared by both wildcard and fallback queries."""
    filter_clauses = [{"range": {"ExpireDate": {"gte": "now"}}}]
    city = params.get("city")
    if params.get("_multi_cities"):
        filter_clauses.append({"terms": {"City.keyword": params["_multi_cities"]}})
    else:
        city = params.get("city")
        if city and not is_out_of_scope(city) and city.upper() not in BC_VARIANTS:
            filter_clauses.append({"terms": {"City.keyword": [city]}})

    if params.get("employment_type"):
        filter_clauses.append({"term": {"HoursOfWork.Description": params["employment_type"]}})
    if params.get("salary_min"):
        filter_clauses.append({"range": {"Salary": {"gte": params["salary_min"]}}})
    return filter_clauses


def search_jobs(params: dict, size: int = PAGE_SIZE, from_offset: int = 0) -> tuple[list, int]:
    print(f"DEBUG search_jobs: keywords={params.get('keywords')} employer={params.get('employer')} multi_cities={params.get('_multi_cities')}")
    """
    Build and execute an OpenSearch query. Returns (jobs, total_count).

    Strategy:
    1. Try wildcard on EmployerName.keyword (handles exact substrings)
    2. If zero results and employer contains education/gov keywords,
       retry with full-text match (handles word-order mismatches like
       'Surrey school district' matching 'School District #36 (Surrey)')
    """
    must_clauses   = []
    filter_clauses = _build_common_filters(params)

    # Job title keywords — skip if employer specified
    if params.get("keywords"):
        generic = {"jobs", "job", "work", "position", "positions", "opening", "openings"}
        clean_keywords = " ".join(
            w for w in params["keywords"].split()
            if w.lower() not in DATE_SORT_KEYWORDS
        ).strip()
        if clean_keywords and clean_keywords.lower() not in generic:
            if len(clean_keywords.split()) > 1:
                must_clauses.append({
                    "multi_match": {
                        "query":  clean_keywords,
                        "fields": ["Title^3", "JobDescription"],
                        "type":   "phrase",
                        "slop":   2,
                    }
                })
            else:
                must_clauses.append({
                    "multi_match": {
                        "query":  clean_keywords,
                        "fields": ["Title^3", "JobDescription"],
                    }
                })
 

    # Employer wildcard filter
    if params.get("employer"):
        filter_clauses.append(
            {"wildcard": {"EmployerName.keyword": f"*{params['employer']}*"}}
        )

    os_query = {
        "query": {
            "bool": {
                "must":   must_clauses if must_clauses else [{"match_all": {}}],
                "filter": filter_clauses,
            }
        },
        "from": from_offset,
        "size": size,
    }

    if needs_date_sort(params):
        os_query["sort"] = [
            {"DatePosted": {"order": "desc"}},
            {"_score":     {"order": "desc"}},
        ]

    print(f"DEBUG: OpenSearch query (from={from_offset}): {json.dumps(os_query, indent=2)}")
    response = os_client.search(index="jobs_en", body=os_query)
    total    = response["hits"]["total"]["value"]
    print(f"DEBUG: OpenSearch returned {total} total matches")

    # --- Fallback: full-text match if wildcard returned 0 ---
    if total == 0 and params.get("employer"):
        employer_lower = params["employer"].lower()
        if any(kw in employer_lower for kw in FALLBACK_KEYWORDS):
            print(f"DEBUG: Wildcard returned 0 — retrying with full-text match for '{params['employer']}'")

            fallback_filter = _build_common_filters(params)
            fallback_must   = [{
                "match": {
                    "EmployerName": {
                        "query":    params["employer"],
                        "operator": "and",
                    }
                }
            }]

            fallback_query = {
                "query": {
                    "bool": {
                        "must":   fallback_must,
                        "filter": fallback_filter,
                    }
                },
                "from": from_offset,
                "size": size,
            }

            if needs_date_sort(params):
                fallback_query["sort"] = [
                    {"DatePosted": {"order": "desc"}},
                    {"_score":     {"order": "desc"}},
                ]

            print(f"DEBUG: Fallback query: {json.dumps(fallback_query, indent=2)}")
            response = os_client.search(index="jobs_en", body=fallback_query)
            total    = response["hits"]["total"]["value"]
            print(f"DEBUG: Fallback returned {total} total matches")

    jobs = _parse_os_hits(response["hits"]["hits"])
    return jobs, total


async def get_job_results(params: dict, from_offset: int = 0) -> tuple[list, int]:
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(
        None, partial(search_jobs, params, PAGE_SIZE, from_offset)
    )


async def handle_load_more(session_id: str) -> dict:
    stored_raw = r.get(f"job_search_params:{session_id}")
    if not stored_raw:
        return {
            "answer":     "I couldn't find your previous search. Please try your search again.",
            "jobs":       [],
            "session_id": session_id,
        }
    stored      = json.loads(stored_raw)
    params      = stored["params"]
    next_page   = stored["page"] + 1
    from_offset = (next_page - 1) * PAGE_SIZE
    jobs, total = await get_job_results(params, from_offset=from_offset)
    r.setex(
        f"job_search_params:{session_id}",
        3600,
        json.dumps({"params": params, "page": next_page}),
    )
    return {
        "answer":     "",
        "jobs":       jobs,
        "total":      total,
        "page":       next_page,
        "has_more":   (from_offset + PAGE_SIZE) < total,
        "session_id": session_id,
    }


async def get_career_answer(
    user_query: str,
    sanitized_history: list,
    system_rules: str,
) -> tuple[str, str]:
    is_comparison = any(w in user_query.lower() for w in
        ["compare", "difference", "versus", "vs", "between"])

    if is_comparison:
        # Comparisons are always self-contained — skip rewriter
        search_term = user_query
        print(f"DEBUG: Comparison query — skipping rewriter")
    else:
        # Always pass history to rewriter and let the LLM decide what to do with it
        history_for_rewriter = sanitized_history[-2:] if sanitized_history else []
        print(f"DEBUG: Passing history to rewriter (entries: {len(history_for_rewriter)})")

        rewrite_prompt = (
            f"Recent conversation history (oldest to newest):\n"
            f"{history_for_rewriter if history_for_rewriter else 'None'}\n\n"
            f"Current user query: {user_query}\n\n"
            "TASK: Identify the EXACT career or job titles the user wants information about right now.\n\n"
            "RULES:\n"
            "1. If the current query names specific careers, use ONLY those careers. "
            "Ignore the history — the user is asking a new question.\n"
            "2. If the current query uses pronouns ('those', 'they', 'it', 'each', 'them') "
            "or refers back ('what about salary', 'tell me more', 'and the education?') — "
            "resolve to the MOST RECENT career discussed in history. "
            "Do NOT include older careers from earlier in the conversation.\n"
            "3. ONLY include multiple careers if the user uses words like 'both', "
            "'these two', 'all of them', or explicitly compares them.\n"
            "4. If the current query has no relation to the history, output only what the "
            "current query asks about.\n\n"
            "Output ONLY the career titles, comma separated. No explanation. No preamble."
        )

        try:
            rewrite_res = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": rewrite_prompt}],
                temperature=0,
            )
            raw_content = rewrite_res.choices[0].message.content.strip()
            lines = [line.strip('- *123456789."\' ') for line in raw_content.split('\n')]
            filtered_lines = [
                l for l in lines
                if len(l) > 0
                and len(l) < 80
                and "Based on"       not in l
                and "Therefore"      not in l
                and "current query"  not in l.lower()
                and "if this"        not in l.lower()
                and "follow-up"      not in l.lower()
                and "job title"      not in l.lower()
                and "history"        not in l.lower()
            ]
            search_term = ", ".join(filtered_lines) if filtered_lines else user_query

            # Safety net: if rewriter returned a question instead of job titles,
            # fall back to the CURRENT user query (NOT history — that causes topic confusion)
            if looks_like_question(search_term):
                search_term = user_query
                print(f"DEBUG: Rewriter returned question — falling back to current query: {search_term}")

        except Exception as e:
            print(f"DEBUG: Rewriter failed, falling back to raw query: {e}")
            search_term = user_query

    print(f"DEBUG: Final Search Term for Chroma: {search_term}")

    expanded_term = expand_synonyms(search_term)
    if expanded_term != search_term:
        search_term = expanded_term
        print(f"DEBUG: After synonym expansion: {search_term}")

    
    loop        = asyncio.get_event_loop()
    q_emb_array = await loop.run_in_executor(
        None,
        partial(
            bi_encoder.encode,
            f"Represent this sentence for searching relevant passages: {search_term}",
            normalize_embeddings=True,
        )
    )
    q_emb = q_emb_array.tolist()
    chunk_types = detect_chunk_types(user_query)
    is_comparison = any(w in user_query.lower() for w in
        ["compare", "difference", "versus", "vs", "between"])
    n_chunks = 4 if is_comparison else 10
    print(f"DEBUG: Chunk types: {chunk_types} | n_results: {n_chunks}")

    if len(chunk_types) == 1:
        results = collection.query(
            query_embeddings=[q_emb],
            n_results=n_chunks,
            where={"chunk_type": {"$eq": chunk_types[0]}}
        )
    else:
        results = collection.query(
            query_embeddings=[q_emb],
            n_results=n_chunks,
            where={"chunk_type": {"$in": chunk_types}}
        )

    context_chunks = []
    context_chunks = []
    career_distances = {}  # Track best (lowest) distance per career for is_clear_match decision

    for i in range(len(results['documents'][0])):
        distance  = results['distances'][0][i]
        job_title = results['metadatas'][0][i].get('job_title')
        print(f"DEBUG: Chroma found '{job_title}' with distance {distance}")
        if distance > 0.5:
            print(f"DEBUG: Skipping '{job_title}' — distance too high: {distance}")
            continue

        # Track best distance per career
        if job_title not in career_distances or distance < career_distances[job_title]:
            career_distances[job_title] = distance
        meta       = results['metadatas'][0][i]
        salary_val = meta.get('salary', 'N/A')
        try:
            salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data missing"
        except (ValueError, TypeError):
            salary_str = f"**${salary_val}**"
        context_chunks.append(
            f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
            f"SALARY: {salary_str}\n"
            f"URL: {meta.get('url', '#')}\n"
            f"CONTENT: {results['documents'][0][i]}"
        )
        print(f'DEBUG: Appended chunk {i}, total now: {len(context_chunks)}')

    print(f"DEBUG: context_chunks length before truncation: {len(context_chunks)}")

    # Inject aliased NOCs that ChromaDB may have ranked too low
    search_lower = search_term.lower()
    already_nocs = {
        chunk.split("NOC: ")[1].split(")")[0].strip()
        for chunk in context_chunks
        if "NOC: " in chunk
    }
    for alias_key, alias_nocs in CAREER_SEARCH_ALIASES.items():
        if alias_key in search_lower:
            for noc in alias_nocs:
                if noc in already_nocs:
                    # Already present — move its chunk(s) to the front so LLM sees it first
                    noc_tag = f"(NOC: {noc})"
                    pinned   = [c for c in context_chunks if noc_tag in c]
                    rest     = [c for c in context_chunks if noc_tag not in c]
                    context_chunks[:] = pinned + rest
                    print(f"DEBUG: Alias-pinned NOC {noc} to front of context")
                    continue
                try:
                    alias_res = collection.get(
                        where={"$and": [
                            {"noc_code":   {"$eq": noc}},
                            {"chunk_type": {"$in": chunk_types}},
                        ]},
                        include=["documents", "metadatas"],
                    )
                    for doc, meta in zip(alias_res["documents"], alias_res["metadatas"]):
                        salary_val = meta.get("salary", "N/A")
                        try:
                            salary_str = f"**${float(salary_val):,.2f}**" if salary_val != "N/A" else "Data missing"
                        except (ValueError, TypeError):
                            salary_str = f"**${salary_val}**"
                        chunk = (
                            f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
                            f"SALARY: {salary_str}\n"
                            f"URL: {meta.get('url', '#')}\n"
                            f"CONTENT: {doc}"
                        )
                        context_chunks.insert(0, chunk)
                        job_title = meta.get("job_title")
                        if job_title and job_title not in career_distances:
                            career_distances[job_title] = 0.30  # treat as high-relevance
                        already_nocs.add(noc)
                        print(f"DEBUG: Alias-injected NOC {noc} ({meta.get('job_title')})")
                        break  # one overview chunk per aliased NOC is enough
                except Exception as e:
                    print(f"DEBUG: Alias fetch failed for NOC {noc}: {e}")

    # Sanity filter — remove ChromaDB matches that only share qualifier words with query
    # Example: query "professional boxer" should NOT match "Professional Marketing"
    QUALIFIER_WORDS = {"professional", "senior", "junior", "lead", "chief", "head", "assistant"}
    STOP_WORDS = {"and", "or", "the", "of", "in", "for", "a", "an", "to", "be", "is",
                  "do", "does", "how", "what", "who", "i", "me", "my", "you", "become",
                  "are", "was", "were", "want", "need", "should", "can", "could", "would"}

    query_lower = user_query.lower()
    query_words = set(re.findall(r'\b[a-z]+\b', query_lower))
    query_qualifiers = query_words & QUALIFIER_WORDS

    if query_qualifiers and len(context_chunks) > 1:
        filtered_chunks = []
        for chunk in context_chunks:
            first_line = chunk.split('\n')[0].lower()
            title_text = first_line.replace("job:", "").split("(noc:")[0].strip()
            title_words = set(re.findall(r'\b[a-z]+\b', title_text))

            # Find substantive overlap (excluding qualifiers and stop words)
            shared = (title_words & query_words) - query_qualifiers - STOP_WORDS

            if shared:
                # Has substantive word overlap — keep it
                filtered_chunks.append(chunk)
            elif not (title_words & query_words):
                # No word overlap at all — pure semantic match, keep it
                filtered_chunks.append(chunk)
            else:
                # Matched ONLY on qualifier word — almost certainly a false positive
                print(f"DEBUG: Filtered out '{title_text}' — only matched on qualifier word")

        if filtered_chunks:  # Only apply filter if it doesn't remove everything
            context_chunks = filtered_chunks
            print(f"DEBUG: After qualifier filter: {len(context_chunks)} chunks remain")

    seen_careers = {}
    priority_chunks = []
    secondary_chunks = []
    for chunk in context_chunks:
        job_line = chunk.split('\n')[0]
        if job_line not in seen_careers:
            seen_careers[job_line] = True
            priority_chunks.append(chunk)
        else:
            secondary_chunks.append(chunk)
    ordered_chunks = priority_chunks + secondary_chunks

    MAX_CONTEXT_CHARS = 3500
    truncated_chunks  = []
    total_chars       = 0
    for chunk in ordered_chunks:
        if truncated_chunks and total_chars + len(chunk) > MAX_CONTEXT_CHARS:
            break
        truncated_chunks.append(chunk)
        total_chars += len(chunk)
    top_context = "\n---\n".join(truncated_chunks) if truncated_chunks else "No WorkBC data found."

    print(f"DEBUG CONTEXT SENT TO LLM:\n{top_context[:600]}")

    # Always pass recent history to the answer LLM so it can resolve
    # references like "those two careers" naturally
    history_window = sanitized_history[-2:] if sanitized_history else []
    while history_window and history_window[0]["role"] != "user":
        history_window.pop(0)

    # Extract the exact career titles available in the context
    available_careers = []
    seen_titles = set()
    for chunk in truncated_chunks:
        first_line = chunk.split('\n')[0]  # "JOB: Title (NOC: XXXXX)"
        if first_line.startswith("JOB:") and first_line not in seen_titles:
            seen_titles.add(first_line)
            available_careers.append(first_line.replace("JOB: ", ""))
    careers_list = "\n- ".join(available_careers)

   # Determine response mode based on ChromaDB distance gap
    # If top match is significantly closer than second → clear single match
    # If matches are close together → ambiguous, list multiple
    remaining_distances = sorted([
        d for title, d in career_distances.items()
        if any(title in chunk for chunk in context_chunks)
    ])

    if len(remaining_distances) <= 1:
        is_clear_match = True
        print(f"DEBUG: Only {len(remaining_distances)} unique career — is_clear_match=True")
    else:
        gap = remaining_distances[1] - remaining_distances[0]
        is_clear_match = gap > 0.05
        print(f"DEBUG: Distance gap = {gap:.3f} (threshold=0.05) — is_clear_match={is_clear_match}")

    # Override: if search term words don't appear in any matched career title, it's not a true match
    if is_clear_match and available_careers:
        search_words = set(
            w.lower() for w in re.split(r'\W+', search_term) if len(w) > 2
        )
        matched_titles_text = " ".join(available_careers).lower()
        if search_words and not any(w in matched_titles_text for w in search_words):
            is_clear_match = False
            print(f"DEBUG: Title mismatch — search words {search_words} not in career titles → is_clear_match=False")  

    is_comparison_query = any(w in user_query.lower() for w in
        ["compare", "difference", "versus", "vs", "between"])
    
    if is_comparison_query:
        print(f"DEBUG: Comparison query detected — forcing table mode")
        mode_instruction = (
            "INSTRUCTIONS:\n"
            "The user wants to COMPARE careers. Respond with ONLY a markdown table — "
            "no text before, no text after.\n\n"
            "Table format:\n"
            "| NOC Code | Career Title | Key Difference | Annual Salary |\n"
            "|----------|--------------|----------------|---------------|\n"
            "| 31301    | Registered Nurses... | [difference] | $87,229.63 |\n"
            "| 32101    | Licensed Practical... | [difference] | $64,144.38 |\n\n"
            "CRITICAL:\n"
            "- Include ALL careers from the AVAILABLE CAREERS list\n"
            "- Use EXACT career titles from the data\n"
            "- Key Difference: 1-2 sentences explaining the main distinction\n"
            "- Start immediately with the table (first character = |)\n"
            "- NO preamble, NO closing remarks, NO links\n"
        ) 
    elif is_clear_match:
        # Single-career mode — describe the matching career normally
        mode_instruction = (
            "INSTRUCTIONS:\n"
            "The user is asking about a specific career that matches one in the list above. "
            "Describe that ONE career using the standard format:\n\n"
            "**[Career Title] (NOC: [code]) — Salary: $[amount]**\n"
            "- [duty 1]\n"
            "- [duty 2]\n"
            "- ... (6-8 bullets)\n"
            "[View Career Profile](url)\n\n"
            "CRITICAL: [Career Title] must be copied EXACTLY from the data — "
            "do NOT rename or rephrase it to match the user's wording. "
            "For example, if the data says 'Athletes', write 'Athletes' — never 'Professional Boxers' or any variant.\n"
            "Do NOT list multiple careers. Do NOT start with disclaimer phrases. "
            "Do NOT say 'WorkBC does not have a specific profile' — the career IS available.\n"
        )
    else:
        # Multi-career mode — list all related careers with disclaimer
        if len(available_careers) == 1:
            disclaimer_line = (
                f"WorkBC does not have a specific profile for {search_term}. "
                f"The closest related career is:"
            )
        else:
            disclaimer_line = (
                f"WorkBC does not have a specific profile for {search_term}. "
                f"Here are related careers in WorkBC data:"
            )
        career_count = len(available_careers)
        mode_instruction = (
            "INSTRUCTIONS:\n"
            "The user's query does not have an exact career match in the list above. "
            f"The AVAILABLE CAREERS list contains exactly {career_count} career(s). "
            f"Describe {'ONLY that ONE career' if career_count == 1 else 'ALL of them (up to 5)'}. "
            "Do NOT mention any career, NOC code, or job title not in the list.\n\n"
            "Your response MUST start with this exact line:\n"
            f"'{disclaimer_line}'\n\n"
            "Then for EACH career in the list provide:\n"
            "**[Career Title] (NOC: [code]) — Salary: $[amount]**\n"
            "- [duty bullet]\n"
            "- [duty bullet]\n"
            "- [duty bullet]\n"
            "[View Career Profile](url)\n\n"
            "CRITICAL: [Career Title] must be copied EXACTLY from the data — "
            "do NOT rename or rephrase it to match the user's wording.\n"
            "STOP after listing only the careers in the AVAILABLE CAREERS list. "
            "Do NOT add suggestions, related roles, or invented NOC codes.\n"
        )

    current_user_content = (
        f"WorkBC career data:\n{top_context}\n\n"
        f"AVAILABLE CAREERS — you may ONLY mention these:\n"
        f"- {careers_list}\n\n"
        f"{mode_instruction}\n"
        f"CRITICAL RULES:\n"
        f"- Use ONLY careers and NOC codes from the list above\n"
        f"- Do NOT invent NOC codes — they are always 5 digits\n"
        f"- Do NOT mention any career not in the list\n\n"
        f"FORBIDDEN PHRASES:\n"
        f"- 'Based on the WorkBC data'\n"
        f"- 'According to the data'\n"
        f"- 'You may choose either'\n"
        f"- Any closing remark or 'For more information' postamble\n\n"
        f"Question: {user_query}"
    )

    final_messages = [
        {"role": "user",      "content": system_rules},
        {"role": "assistant", "content": "Understood. I will follow these guidelines strictly."},
        *history_window,
        {"role": "user",      "content": current_user_content},
    ]

    try:
        tokens_for_request = 1000 if is_comparison else MAX_TOKENS

        completion    = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=final_messages,
            temperature=0.0,
            max_tokens=tokens_for_request,
            frequency_penalty=0.5,  # prevents repetition loops
            presence_penalty=0.3,   # encourages varied vocabulary
        )
        answer        = completion.choices[0].message.content
        finish_reason = completion.choices[0].finish_reason
        if finish_reason == "length":
            answer += (
                "\n\n---\n"
                "_Response was truncated due to length. "
                "Try asking a more specific question for complete information._"
            )

        # Validation: detect hallucinated NOC codes
        response_nocs = set(re.findall(r'NOC[:\s]+(\d+)', answer, re.IGNORECASE))
        context_nocs  = set(re.findall(r'NOC:\s*(\d+)', top_context))
        hallucinated  = response_nocs - context_nocs

        if hallucinated:
            print(f"WARNING: Hallucinated NOC codes detected: {hallucinated}")
            print(f"DEBUG: Response NOCs: {response_nocs} | Context NOCs: {context_nocs}")

            if available_careers:
                careers_bullets = "\n".join(f"- **{career}**" for career in available_careers[:3])
                answer = (
                    f"WorkBC does not have a specific profile for that career. "
                    f"The closest related careers in WorkBC's database are:\n\n"
                    f"{careers_bullets}\n\n"
                    f"Would you like to know more about any of these?"
                )
            else:
                answer = (
                    "I don't have that specific career in WorkBC records. "
                    "Try searching for a related career or browse [WorkBC career profiles]"
                    f"({WORKBC_BASE_URL}/career-profiles) directly."
                )

        # Career Trek videos if available for the primary career
        if not hallucinated:  # only append for valid responses
            primary_noc = extract_primary_noc_from_answer(answer)
            if primary_noc:
                video_section = format_video_section(primary_noc)
                if video_section:
                    answer += video_section
                    print(f"DEBUG: Appended Career Trek video for NOC {primary_noc}")

    except Exception as e:
        raise HTTPException(status_code=502, detail=f"LLM inference error: {str(e)}")

    return answer, search_term

def generate_suggestions(intent: str, user_query: str, answer: str = "",
                         params: dict = None, has_results: bool = True) -> list[str]:
    """
    Generate 2-3 contextual follow-up suggestions based on the conversation.
    Returns a list of suggestion strings to display as clickable buttons.
    """
    params = params or {}
    normalized = user_query.lower().strip()

    # Greeting / intro / out-of-scope responses
    if intent in ("greeting", "out_of_scope"):
        return [
            "What does a nurse do?",
            "Find software developer jobs in Vancouver",
            "Top trade jobs hiring in BC",
        ]

    # Career discovery quiz redirect
    if intent == "discovery":
        return [
            "What does a plumber do?",
            "Top healthcare jobs",
            "Find jobs in Vancouver",
        ]

    # Hiring trends / top careers chart
    if intent == "hiring_trends":
        # Detect category from the query so we suggest a different category next
        if any(w in normalized for w in ["trade", "trades"]):
            return [
                "What does an electrician do?",
                "Find plumber jobs in Vancouver",
                "Top healthcare jobs",
            ]
        if any(w in normalized for w in ["health", "healthcare", "medical", "nursing"]):
            return [
                "What does a nurse do?",
                "Find nursing jobs in Surrey",
                "Top trade jobs",
            ]
        if any(w in normalized for w in ["tech", "technology", "software", "it"]):
            return [
                "What does a data scientist do?",
                "Find software developer jobs",
                "Top business careers",
            ]
        return [
            "Top trade jobs",
            "Top healthcare jobs",
            "Find jobs in Vancouver",
        ]

    # Comparison query — check the user's actual question, not just intent
    is_comparison = any(w in normalized for w in
                        ["compare", "difference", "versus", "vs", "between"])
    if is_comparison:
        # Extract career names from response NOCs
        career_titles = re.findall(r'\|\s*\d{5}\s*\|\s*([^|]+?)\s*\|', answer)
        if career_titles and len(career_titles) >= 2:
            first_career = career_titles[0].strip()
            return [
                f"What education is needed for {first_career}?",
                f"Find {first_career.lower()} jobs",
                "Top hiring careers in BC",
            ]
        return [
            "What is salary for both?",
            "What education is needed?",
            "Find jobs in either",
        ]

    # Career info responses
    if intent == "career_info":
        # Extract primary career from response
        primary_noc = extract_primary_noc_from_answer(answer)
        career_title = ""
        if primary_noc and primary_noc in NOC_TITLE_MAP:
            career_title = NOC_TITLE_MAP[primary_noc].get("title", "")
            career_short = career_title.split(" And ")[0].split(",")[0].strip()
        else:
            career_short = "this career"

        # Check what the response ALREADY CONTAINS (not just what user asked)
        answer_lower = answer.lower()
        has_salary = bool(re.search(r'\$[\d,]+', answer))
        has_education = any(w in answer_lower for w in
                            ["education", "degree", "diploma", "certificate",
                             "training program", "bachelor", "qualification"])

        suggestions = []

        # Only suggest education if not already in the response
        if not has_education:
            suggestions.append("What education do I need?")

        # Only suggest salary if not already in the response
        if not has_salary:
            suggestions.append("What is the salary?")

        # Always offer job search
        if career_short and career_short != "this career":
            suggestions.append(f"Find {career_short.lower()} jobs")

        # Look up a known comparison for this career
        comparison_suggestion = None
        if career_title:
            career_lower = career_title.lower()
            for keyword, suggestion in COMPARISON_SUGGESTIONS.items():
                if keyword in career_lower:
                    comparison_suggestion = suggestion
                    break

        # If we have fewer than 3, add useful suggestions
        if len(suggestions) < 3:
            extra_options = []

            if comparison_suggestion:
                extra_options.append(comparison_suggestion)

            extra_options.append("Top hiring careers in BC")

            for opt in extra_options:
                if opt not in suggestions and len(suggestions) < 3:
                    suggestions.append(opt)

        return suggestions[:3]

        # Job search results
    if intent == "job_search":
        keywords = params.get("keywords", "")
        city = params.get("city", "")

        if not has_results:
            # Special case: out-of-scope city (Toronto, Seattle, etc.)
            # Suggest the same search in BC cities
            if city and is_out_of_scope(city):
                if keywords:
                    return [
                        f"Find {keywords} jobs in Vancouver",
                        f"Find {keywords} jobs in Surrey",
                        "Top hiring careers in BC",
                    ]
                return [
                    "Find jobs in Vancouver",
                    "Find jobs in Surrey",
                    "Top hiring careers in BC",
                ]

            # No results in a valid BC city — broaden the search
            if keywords and city:
                return [
                    f"Find {keywords} jobs in Vancouver",
                    f"What does a {keywords.split()[0]} do?",
                    "Top hiring careers in BC",
                ]
            if keywords:
                return [
                    f"What does a {keywords.split()[0]} do?",
                    "Top hiring careers in BC",
                    "Find jobs in Vancouver",
                ]
            return [
                "Find nursing jobs in Vancouver",
                "Top hiring careers in BC",
                "What does a nurse do?",
            ]

        # Has results — offer to drill down or pivot
        suggestions = []
        if keywords and not city:
            suggestions.append(f"{keywords} jobs in Vancouver")
        if keywords:
            suggestions.append(f"What does a {keywords.split()[0]} do?")
        suggestions.append("Top hiring careers in BC")

        return suggestions[:3]

    # Both intent (career info + jobs)
    if intent == "both":
        return [
            "What is the salary?",
            "What education is needed?",
            "Show more jobs",
        ]

    # Default fallback
    return [
        "What does a nurse do?",
        "Find jobs in Vancouver",
        "Top hiring careers",
    ]

# ---------------------------------------------------------------------------
# 6. MAIN ENDPOINT
# ---------------------------------------------------------------------------
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key  = f"chat_history:{session_id}"

        if user_query.strip() == "__load_more__":
            return await handle_load_more(session_id)

        # Handle greetings and help requests - no LLM call needed
        GREETING_PATTERNS = {"hello", "hi", "hey", "help", "hola", "yo"}

        # Phrases that indicate the user is asking about the bot itself
        BOT_INTRO_PATTERNS = [
            "what can you do", "what do you do", "what you do", "what can u do",
            "who are you", "who r u", "what are you", "what r u",
            "how do you work", "how does this work", "what is this",
            "tell me what you do", "what do you help with",
            "what can you help", "how can you help",
        ]

        # Meta question detection — handles natural phrasing variation
        # Triggers when query contains BOTH a question-starter AND a meta subject
        META_QUESTION_STARTERS = [
            "what is", "what's", "what does", "what do you mean by",
            "explain", "tell me about", "tell me what", "describe",
            "define", "meaning of", "what mean", "what means",
            "can you explain", "can you tell me about",
            "how do", "how does",
        ]

        META_SUBJECTS = {
            "career_profile": ["career profile", "career profiles"],
            "noc":            ["noc code", "noc codes", " noc ", " noc?", "what's noc", "what is noc", "explain noc", "tell me about noc", "describe noc"],
            "workbc":         ["workbc"],
            "data_source":    ["data come from", "data sources", "data do you use",
                               "where do you get", "where does this come from"],
            "accuracy":       ["how accurate", "how reliable", "is this accurate",
                               "can i trust", "how trustworthy"],
        }

        def detect_meta_subject(query: str) -> str | None:
            """Return the meta subject if query is a meta question, else None."""
            # Check if any meta subject is present (some subjects are self-identifying)
            for subject, keywords in META_SUBJECTS.items():
                if any(k in query for k in keywords):
                    # For terms that need a question context (career_profile, workbc)
                    # require a question starter to avoid false matches
                    if subject in ("career_profile", "workbc"):
                        if any(s in query for s in META_QUESTION_STARTERS):
                            return subject
                    else:
                        return subject
            return None

        normalized = user_query.lower().strip().rstrip("?!.,")
        is_greeting = (
            normalized in GREETING_PATTERNS or
            any(pattern in normalized for pattern in BOT_INTRO_PATTERNS)
        )

        if is_greeting:
            return {
                "answer": "I'm the WorkBC Career Advisor! I can help you: \n\n"
                          "* **Learn about career** - duties, salary, education requirements\n"
                          "* **Search for jobs** - by title, employer, or city\n"
                          "* **Compare careers** - side by side with salary data\n\n"
                          "Try asking: *\"What does a nurse do?\"* or *\"Find nursing jobs in Vancouver\"*",
                "career_answer": "",
                "jobs": [],
                "total": 0,
                "page": 1,
                "has_more": False,
                "session_id": session_id,
                "suggestions": generate_suggestions("greeting", user_query)
            }
        
        # Handle meta questions about how the system works
        meta_subject = detect_meta_subject(normalized)

        if meta_subject:
            if meta_subject == "career_profile":
                meta_answer = (
                    "A **career profile** is a detailed summary of an occupation that includes:\n\n"
                    "* **Duties and responsibilities** — what people in this role do day-to-day\n"
                    "* **Salary information** — typical earnings in British Columbia\n"
                    "* **Education and training** — what qualifications you need\n"
                    "* **NOC code** — the official 5-digit classification code\n\n"
                    "WorkBC has **511 career profiles** covering occupations across BC. "
                    "I use these profiles to answer your career questions.\n\n"
                    "Try asking about a specific career — for example: *\"What does a nurse do?\"* "
                    "or *\"Tell me about plumbers\"*."
                )
            elif meta_subject == "noc":
                meta_answer = (
                    "**NOC** stands for **National Occupational Classification** — "
                    "Canada's official system for organizing occupations.\n\n"
                    "Every career has a unique 5-digit NOC code. For example:\n\n"
                    "* Registered Nurses → NOC 31301\n"
                    "* Plumbers → NOC 72300\n"
                    "* Software Developers → NOC 21232\n\n"
                    "NOC codes help match careers across job postings, government statistics, "
                    "and immigration programs.\n\n"
                    "Try asking: *\"What does a firefighter do?\"* and I'll show you the NOC code."
                )
            elif meta_subject == "workbc":
                meta_answer = (
                    "**WorkBC** is the British Columbia government's career and employment service. "
                    "I use WorkBC's data to answer your questions, including:\n\n"
                    "* **511 career profiles** with duties, salaries, and education requirements\n"
                    "* **Live job postings** updated continuously across BC\n"
                    "* **Career Trek videos** featuring real BC professionals\n\n"
                    "Visit [workbc.ca](https://www.workbc.ca) for more resources."
                )
            elif meta_subject == "data_source":
                meta_answer = (
                    "All my information comes from **official WorkBC sources**:\n\n"
                    "* **Career profiles** — 511 occupations with duties, salaries, and education paths\n"
                    "* **Job postings** — live data from WorkBC's job bank\n"
                    "* **Career Trek videos** — real BC professionals describing their work\n\n"
                    "I don't use any external sources — every answer is grounded in WorkBC data."
                )
            elif meta_subject == "accuracy":
                meta_answer = (
                    "I work hard to give accurate information by:\n\n"
                    "* Using **only official WorkBC data** — no external sources\n"
                    "* **Validating every career code** against the source list\n"
                    "* **Catching invented data** before showing it to you\n"
                    "* Being **honest when I don't know** rather than guessing\n\n"
                    "If you ever see something that looks wrong, please let us know."
                )

            return {
                "answer":        meta_answer,
                "career_answer": "",
                "jobs":          [],
                "total":         0,
                "page":          1,
                "has_more":      False,
                "session_id":    session_id,
                "suggestions": generate_suggestions("greeting", user_query),
            }
        
        
        # Handle career discovery / exploration queries — redirect to quiz
        DISCOVERY_PATTERNS = [
            "what career should i", "what job should i", "which career should i",
            "which job should i", "what should i be", "what career is right",
            "what career fits", "what job fits", "help me choose a career",
            "help me find a career", "help me pick a career",
            "what career suits me", "what job suits me",
            "i don't know what career", "i dont know what career",
            "i don't know what to do", "i dont know what to do",
            "career advice", "career guidance", "career suggestions",
            "what are my options", "career options",
            "what kind of career", "what type of career",
        ]

        if any(pattern in normalized for pattern in DISCOVERY_PATTERNS):
            return {
                "answer": (
                    "Choosing the right career is a personal journey — I can help you "
                    "explore specific careers, but I'm not able to recommend a career path "
                    "based on your interests or skills.\n\n"
                    "**WorkBC has a free Career Discovery Quiz** that matches your interests, "
                    "skills, and values to careers that might be a good fit:\n\n"
                    "👉 [**Take the Career Discovery Quiz**](http://careerdiscoveryquizzes.workbc.ca/)\n\n"
                    "Once you have some career ideas, come back and ask me about them — "
                    "I can tell you about duties, salary, education requirements, and "
                    "show you current job openings."
                ),
                "career_answer": "",
                "jobs": [],
                "total": 0,
                "page": 1,
                "has_more": False,
                "session_id": session_id,
                "suggestions": generate_suggestions("discovery", user_query),
            }        

        # Handle "what is hiring" / "top careers" trend questions
        if is_hiring_trends_query(normalized):
            # Detect category from query (trades, health, tech, etc.)
            detected_category = None
            for cat_key, cat_patterns in CATEGORY_PATTERNS.items():
                if any(p in normalized for p in cat_patterns):
                    detected_category = cat_key
                    break

            loop = asyncio.get_event_loop()
            try:
                results, total, category_label = await loop.run_in_executor(
                    None, partial(get_top_hiring_careers, 10, detected_category)
                )
                answer = format_top_hiring_chart(results, total, category_label)
            except Exception as e:
                print(f"ERROR: Top hiring aggregation failed: {e}")
                answer = (
                    "I couldn't retrieve hiring trends right now. "
                    "Try asking for jobs in a specific field, or visit "
                    "[WorkBC's Labour Market Information]"
                    "(https://www.workbc.ca/labour-market-information)."
                )
            return {
                "answer":        answer,
                "career_answer": "",
                "jobs":          [],
                "total":         0,
                "page":          1,
                "has_more":      False,
                "session_id":    session_id,
                "suggestions": generate_suggestions("hiring_trends", user_query),
            }


        try:
            raw_history = r.get(redis_key)
            history     = json.loads(raw_history) if raw_history else []
        except (json.JSONDecodeError, redis.RedisError) as e:
            print(f"WARN: Redis/JSON error, starting fresh: {e}")
            history = []

        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        intent_prompt = (
            "Classify this query and return JSON only, no explanation, no markdown fences.\n\n"
            "IMPORTANT: This job bank only contains British Columbia, Canada postings. "
            "If the user specifies a location outside BC (another Canadian province, "
            "a US state, or a foreign country), still extract it as city so the system "
            "can return a helpful out-of-scope message.\n\n"
            "RULES:\n"
            "- 'job_search' = user wants to find/see/browse actual job postings or openings\n"
            "- 'career_info' = user wants to learn about a career (duties, salary, education)\n"
            "- 'both' = user wants career info AND job postings\n"
            "- 'out_of_scope' = greetings, bot questions, or anything NOT about BC careers/jobs\n\n"
            "OUT-OF-SCOPE EXAMPLES (return out_of_scope):\n"
            "Query: 'hi' -> out_of_scope\n"
            "Query: 'hello' -> out_of_scope\n"
            "Query: 'hi jennifer' -> out_of_scope\n"
            "Query: 'hey there' -> out_of_scope\n"
            "Query: 'good morning' -> out_of_scope\n"
            "Query: 'howdy' -> out_of_scope\n"
            "Query: 'who are you' -> out_of_scope\n"
            "Query: 'what can you do' -> out_of_scope\n"
            "Query: 'how does this work' -> out_of_scope\n"
            "Query: 'help' -> out_of_scope\n"
            "Query: 'thanks' -> out_of_scope\n"
            "Query: 'thank you' -> out_of_scope\n"
            "Query: 'tell me a joke' -> out_of_scope\n"
            "Query: 'what is the weather' -> out_of_scope\n"
            "Query: 'how do I cook pasta' -> out_of_scope\n"
            "Query: 'what is 2+2' -> out_of_scope\n"
            "Query: 'how are you' -> out_of_scope\n"
            "Query: 'are you a robot' -> out_of_scope\n"
            "Query: 'asdfgh' -> out_of_scope\n\n"
            "CAREER/JOB EXAMPLES:\n"
            "Query: 'find nursing jobs in Vancouver' -> job_search, keywords=nursing, city=Vancouver\n"
            "Query: 'Best Buy jobs in Surrey' -> job_search, employer=Best Buy, city=Surrey\n"
            "Query: 'McDonald's part time jobs' -> job_search, employer=McDonald's, employment_type=Part-time\n"
            "Query: 'Telus jobs in Burnaby' -> job_search, employer=Telus, city=Burnaby\n"
            "Query: 'jobs in Surrey' -> job_search, keywords=null, city=Surrey\n"
            "Query: 'City of Surrey jobs' -> job_search, employer=City of Surrey, city=null\n"
            "Query: 'City of Vancouver engineer jobs' -> job_search, employer=City of Vancouver, keywords=engineer, city=null\n"
            "Query: 'City of Kelowna part time' -> job_search, employer=City of Kelowna, employment_type=Part-time, city=null\n"
            "Query: 'City of Kelowna jobs in Kelowna' -> job_search, employer=City of Kelowna, city=Kelowna\n"
            "Query: 'jobs with the City of Burnaby' -> job_search, employer=City of Burnaby, city=null\n"
            "Query: 'looking for jobs with city of surrey' -> job_search, employer=City of Surrey, city=null\n"
            "Query: 'BC provincial jobs' -> job_search, employer=BC Provincial, city=null\n"
            "Query: 'VCH jobs' -> job_search, employer=VCH, city=null\n"
            "Query: 'Surrey school district jobs' -> job_search, employer=Surrey school district, city=null\n"
            "Query: 'SD36 jobs' -> job_search, employer=SD36, city=null\n"
            "Query: 'UBC jobs' -> job_search, employer=UBC, city=null\n"
            "Query: 'Island Health jobs in Nanaimo' -> job_search, employer=Island Health, city=Nanaimo\n"
            "Query: 'latest business analyst jobs in Ontario' -> job_search, keywords=latest business analyst, city=Ontario\n"
            "Query: 'show me accounting jobs' -> job_search, keywords=accounting\n"
            "Query: 'any openings for teachers?' -> job_search, keywords=teacher\n"
            "Query: 'jobs paying over 80000' -> job_search, salary_min=80000\n"
            "Query: 'what does a nurse do?' -> career_info\n"
            "Query: 'what is the salary for a firefighter?' -> career_info\n"
            "Query: 'what education do I need to be a pharmacist?' -> career_info\n"
            "Query: 'tell me about plumbers and show me jobs' -> both\n"
            "Query: 'electrical jobs in surrey, vancouver, richmond' -> job_search, keywords=electrical, city=Surrey,Vancouver,Richmond\n"
            "Query: 'nursing jobs in kelowna and kamloops' -> job_search, keywords=nursing, city=Kelowna,Kamloops\n"
            "Query: 'jobs in Toronto' -> job_search, keywords=null, city=Ontario\n"
            "Query: 'nursing jobs in Calgary' -> job_search, keywords=nursing, city=Alberta\n"
            "Query: 'jobs in Montreal' -> job_search, keywords=null, city=Quebec\n"
            "Query: 'developer jobs in Seattle' -> job_search, keywords=developer, city=Washington\n"
            "Query: 'show me jobs in Edmonton' -> job_search, keywords=null, city=Alberta\n"
            "Query: 'engineering jobs in San Francisco' -> job_search, keywords=engineering, city=California\n\n"
            "OUTPUT FORMAT:\n"
            "{\n"
            '  "intent": "job_search" or "career_info" or "both" or "out_of_scope",\n'
            '  "job_search_params": {\n'
            '    "keywords": "extracted job title or null",\n'
            '    "employer": "extracted company name or null",\n'
            '    "city": "extracted city, province, state or country or null",\n'
            '    "employment_type": "Full-time or Part-time or null",\n'
            '    "salary_min": null\n'
            "  }\n"
            "}\n\n"
            f"Query: {user_query}"
        )

        multi_cities = None
        try:
            intent_res  = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": intent_prompt}],
                temperature=0,
            )
            raw_intent  = intent_res.choices[0].message.content.strip()
            intent_data = parse_intent(raw_intent)
            intent      = intent_data["intent"]
            params      = intent_data.get("job_search_params", {})

            # Post-processing pipeline
            if params.get("city"):
                params["city"] = clean_city(params["city"])
            params = fix_city_of_misclassification(params)
            if params.get("city"):
                params["city"] = resolve_city(params["city"])
            if params.get("employer"):
                params["employer"] = resolve_employer(params["employer"])

            #multi-city detection
            multi_cities = None
            city = params.get("city")
            if city and ("," in city or " and " in city.lower()):
                raw_cities = re.split(r',\s*|\s+and\s+', city, flags=re.IGNORECASE)
                multi_cities = [clean_city(c.strip()) for c in raw_cities if c.strip()]
                params["city"] = None
                print(f"Debug: Multi-city detected: {multi_cities}")        

        except json.JSONDecodeError as e:
            print(f"DEBUG: Intent JSON parse failed ({e}) — raw was: {repr(raw_intent)}")
            intent = "career_info"
            params = {}
        except Exception as e:
            print(f"DEBUG: Intent detection failed ({type(e).__name__}): {e}")
            intent = "career_info"
            params = {}

        print(f"DEBUG: Intent={intent} | Params={params}")

        system_rules = (
            "You are a WorkBC Career Advisor for British Columbia. "
            "BE CONCISE. Use bullet points.\n\n"

            "RULE 1 — DATA ONLY:\n"
            "- Use ONLY the data provided below — never your training knowledge\n"
            "- Only mention careers, NOC codes, and salaries from the data\n"
            "- NOC codes are always 5 digits — never output other formats\n"
            "- If no relevant data exists, say: 'I don't have that information in WorkBC records'\n\n"

            "RULE 2 — RESPONSE FORMAT:\n"
            "- Start directly with the answer — no preamble like 'Based on the data' "
            "or 'According to the context'\n"
            "- For ONE career: bold NOC and salary in the first line, then 6-8 duty bullets. "
            "Example: '**Registered Nurses (NOC: 31301) — Salary: $87,229.63**'\n"
            "- For COMPARISONS: respond with ONLY a markdown table "
            "(NOC | Title | Key Difference | Salary). No text before or after.\n"
            "- No closing remarks, no 'For more information' postamble\n\n"

            "RULE 3 — NO ADVICE FROM TRAINING:\n"
            "- Never add 'how to' steps, training tips, or career path advice not in the data\n"
            "- If user asks 'how do I become X', describe what the WorkBC profile says\n"
            "- Avoid phrases like 'You would typically need to' or 'If you want to become'\n\n"

            "RULE 4 — URLS FROM CONTEXT ONLY:\n"
            "- Format links as [View Career Profile](URL) using URLs from the data\n"
            "- Never construct, guess, or invent URLs — only use URL: fields shown\n"
            "- Omit the link silently if no URL is in the data for a career\n"
        )
  

        answer        = ""
        career_answer = ""
        search_term   = user_query
        jobs          = []
        total         = 0
        page          = 1
        has_more      = False

        if intent == "out_of_scope":
            answer = (
                "I'm the WorkBC Career Advisor! I can help you: \n\n"
                "* **Learn about career** - duties, salary, education requirements\n"
                "* **Search for jobs** - by title, employer, or city\n"
                "* **Compare careers** - side by side with salary data\n\n"
                "Try asking: *\"What does a nurse do?\"* or *\"Find nursing jobs in Vancouver\"*"
            )
        elif intent == "career_info":
            answer, search_term = await get_career_answer(
                user_query, sanitized_history, system_rules
            )
        
        elif intent == "job_search":
            city = params.get("city")
            if city and is_out_of_scope(city):
                answer = format_job_results([], params, 0)
            elif multi_cities:
                # Multi-city: bar chart + top 5 job cards
                loop = asyncio.get_event_loop()
                city_counts, agg_total = await loop.run_in_executor(
                    None, partial(search_jobs_by_city, params, multi_cities)
                )
                keyword_str = params.get("keywords") or params.get("employer") or "all"
                answer = format_city_bar_chart(city_counts, agg_total, keyword_str)

                params["_multi_cities"] = multi_cities
                jobs, total = await get_job_results(params, from_offset=0)
                has_more = total > PAGE_SIZE
                r.setex(
                    f"job_search_params:{session_id}",
                    3600,
                    json.dumps({"params": params, "page": 1}),
                )
            else:
                jobs, total = await get_job_results(params, from_offset=0)
                has_more    = total > PAGE_SIZE
                r.setex(
                    f"job_search_params:{session_id}",
                    3600,
                    json.dumps({"params": params, "page": 1}),
                )
                answer = format_job_results(jobs, params, total)


        history_answer = career_answer if intent == "both" else answer
        sanitized_history.append({"role": "user",      "content": user_query})
        sanitized_history.append({"role": "assistant",  "content": history_answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {
            "answer":        answer,
            "career_answer": career_answer,
            "jobs":          jobs,
            "total":         total,
            "page":          page,
            "has_more":      has_more,
            "session_id":    session_id,
            "debug_search":  search_term,
            "debug_intent":  intent,
            "debug_params":  params,
            "suggestions":   generate_suggestions(
                intent,
                user_query,
                answer=answer,
                params=params,
                has_results=(total > 0 if intent == "job_search" else True),
            ),
        }

    except HTTPException:
        raise
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Internal Server Error: {str(e)}")

#---------------------------------------------------------------------------
#clear session of chat
#---------------------------------------------------------------------------

@app.post("/api/clear_session")
async def clear_session(request: ClearSessionRequest):
    """Delete conversation history and stored params for a session."""
    try:
        r.delete(f"chat_history:{request.session_id}")
        r.delete(f"job_search_params:{request.session_id}")
        return {"status": "cleared"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

#---------------------------------------------------------------------------
# Admin reload endpoint and scheduled refresh
#---------------------------------------------------------------------------
@app.post("/api/admin/reload_videos")
async def reload_videos():
    """Manually trigger an immediate video reload from PostgreSQL."""
    old_count = len(VIDEO_MAP)
    build_video_map()
    return {
        "status":   "reloaded",
        "previous": old_count,
        "current":  len(VIDEO_MAP),
    }


async def video_reload_task():
    """Refresh videos from PostgreSQL every hour."""
    while True:
        await asyncio.sleep(60 * 60)
        try:
            print("DEBUG: Hourly video reload from PostgreSQL")
            build_video_map()
        except Exception as e:
            print(f"WARN: Hourly reload failed: {e}")
@app.on_event("startup")
async def start_background_tasks():
    asyncio.create_task(video_reload_task())
    print("DEBUG: Started video reload background task")

#----------------------------------------------------------------------------
#Feedback buttons
#----------------------------------------------------------------------------
@app.post("/api/feedback")
async def submit_feedback(request: FeedbackRequest):
    """Store user feedback on a bot response."""
    try:
        # Validate rating
        if request.rating not in ("up", "down"):
            raise HTTPException(status_code=400, detail="rating must be 'up' or 'down'")

        # Build feedback record
        from datetime import datetime
        feedback = {
            "session_id":   request.session_id,
            "message_id":   request.message_id,
            "user_query":   request.user_query[:500],  # cap length
            "bot_response": request.bot_response[:2000],  # cap length
            "rating":       request.rating,
            "comment":      (request.comment or "")[:500],
            "intent":       request.intent or "",
            "timestamp":    request.timestamp or datetime.utcnow().isoformat() + "Z",
        }

        # Store in Redis with a 30-day expiry
        # Key format: feedback:<timestamp>:<session_id>
        feedback_key = f"feedback:{feedback['timestamp']}:{request.session_id}"
        r.setex(feedback_key, 60 * 60 * 24 * 30, json.dumps(feedback))

        # Also add to a sorted list for easy retrieval
        r.lpush("feedback:all", json.dumps(feedback))
        r.ltrim("feedback:all", 0, 999)  # Keep last 1000 entries

        print(f"DEBUG: Feedback received — {request.rating} for session {request.session_id}")

        return {"status": "received"}

    except HTTPException:
        raise
    except Exception as e:
        traceback.print_exc()
        # Don't expose internal errors — just log and return generic response
        print(f"ERROR: Feedback save failed: {e}")
        return {"status": "received"}  # Pretend success to avoid blocking user

#-----------------------------------------------------------------------------------
# Admin feedback
#-----------------------------------------------------------------------------------
@app.get("/api/admin/feedback")
async def get_feedback(limit: int = 100):
    """Retrieve recent feedback for review."""
    try:
        raw_items = r.lrange("feedback:all", 0, limit - 1)
        items = [json.loads(item) for item in raw_items]

        # Summary stats
        up_count = sum(1 for i in items if i.get("rating") == "up")
        down_count = sum(1 for i in items if i.get("rating") == "down")

        return {
            "total":        len(items),
            "thumbs_up":    up_count,
            "thumbs_down":  down_count,
            "satisfaction": round(up_count / len(items) * 100, 1) if items else 0,
            "items":        items,
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

#----------------------------------------------------------------------------
# feedback view html
#----------------------------------------------------------------------------
@app.get("/api/admin/feedback/view", response_class=HTMLResponse)
async def view_feedback_html(limit: int = 100):
    """HTML dashboard for browsing user feedback."""
    try:
        raw_items = r.lrange("feedback:all", 0, limit - 1)
        items = [json.loads(item) for item in raw_items]

        up_count = sum(1 for i in items if i.get("rating") == "up")
        down_count = sum(1 for i in items if i.get("rating") == "down")
        satisfaction = round(up_count / len(items) * 100, 1) if items else 0

        # Build HTML rows
        rows_html = ""
        for item in items:
            rating_emoji = "👍" if item.get("rating") == "up" else "👎"
            rating_color = "#28a745" if item.get("rating") == "up" else "#dc3545"
            timestamp = item.get("timestamp", "")[:19].replace("T", " ")
            comment = item.get("comment", "") or "<em style='color:#999'>(no comment)</em>"

            rows_html += f"""
            <tr>
                <td style="white-space:nowrap; font-family:monospace; font-size:12px;">{timestamp}</td>
                <td style="text-align:center; font-size:20px; color:{rating_color};">{rating_emoji}</td>
                <td style="max-width:200px; font-size:13px;">{item.get('user_query', '')[:150]}</td>
                <td style="max-width:300px; font-size:12px; color:#555;">{item.get('bot_response', '')[:200]}...</td>
                <td style="font-size:13px;">{comment}</td>
                <td style="font-size:11px; color:#888;">{item.get('intent', '')}</td>
                <td style="font-family:monospace; font-size:11px; color:#888;">{item.get('session_id', '')[:12]}</td>
            </tr>
            """

        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <title>WorkBC Career Advisor — Feedback</title>
            <meta charset="utf-8">
            <style>
                body {{
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f5f7fa;
                    color: #333;
                }}
                h1 {{
                    margin: 0 0 20px;
                    color: #04364A;
                }}
                .stats {{
                    display: flex;
                    gap: 20px;
                    margin-bottom: 30px;
                }}
                .stat-card {{
                    background: white;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 16px 24px;
                    flex: 1;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }}
                .stat-label {{
                    color: #888;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }}
                .stat-value {{
                    font-size: 32px;
                    font-weight: bold;
                    color: #028090;
                    margin-top: 6px;
                }}
                table {{
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    border-radius: 8px;
                    overflow: hidden;
                }}
                th {{
                    background: #028090;
                    color: white;
                    text-align: left;
                    padding: 12px;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }}
                td {{
                    padding: 10px 12px;
                    border-top: 1px solid #eee;
                    vertical-align: top;
                }}
                tr:hover {{
                    background: #f9fafb;
                }}
                .empty {{
                    text-align: center;
                    padding: 40px;
                    color: #999;
                    font-style: italic;
                }}
            </style>
        </head>
        <body>
            <h1>📊 WorkBC Career Advisor — User Feedback</h1>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Feedback</div>
                    <div class="stat-value">{len(items)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Thumbs Up</div>
                    <div class="stat-value" style="color:#28a745;">{up_count}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Thumbs Down</div>
                    <div class="stat-value" style="color:#dc3545;">{down_count}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Satisfaction</div>
                    <div class="stat-value">{satisfaction}%</div>
                </div>
            </div>

            {"<table>" if items else ""}
                {f'''
                <thead>
                    <tr>
                        <th>Time (UTC)</th>
                        <th>Rating</th>
                        <th>Question</th>
                        <th>Response</th>
                        <th>Comment</th>
                        <th>Intent</th>
                        <th>Session</th>
                    </tr>
                </thead>
                <tbody>
                    {rows_html}
                </tbody>
                ''' if items else '<div class="empty">No feedback received yet.</div>'}
            {"</table>" if items else ""}
        </body>
        </html>
        """

        return HTMLResponse(content=html)

    except Exception as e:
        return HTMLResponse(content=f"<h1>Error loading feedback</h1><pre>{e}</pre>")

# ---------------------------------------------------------------------------
# 7. HEALTH CHECK
# ---------------------------------------------------------------------------
@app.get("/health")
@app.get("/api/health")
async def health_check():
    return {
        "status":              "healthy",
        "mistral_endpoint":    f"http://{MISTRAL_HOST}:{MISTRAL_PORT}",
        "opensearch_server":   OPENSEARCH_SERVER,
        "opensearch_user_set": bool(OPENSEARCH_USER),
        "opensearch_pass_set": bool(OPENSEARCH_PASS),
        "workbc_base_url":     WORKBC_BASE_URL,
        "max_tokens":          MAX_TOKENS,
        "page_size":           PAGE_SIZE,
    }


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
