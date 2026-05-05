import os
import re
import json
import asyncio
import traceback
from functools import partial

import redis
import chromadb
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from opensearchpy import OpenSearch
from openai import OpenAI
import uvicorn

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

# ---------------------------------------------------------------------------
# 5. HELPERS
# ---------------------------------------------------------------------------

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
            # fall back to most recent user query from history
            if looks_like_question(search_term):
                for msg in reversed(sanitized_history):
                    if msg["role"] == "user" and msg["content"].strip() != user_query.strip():
                        search_term = msg["content"]
                        print(f"DEBUG: Rewriter returned question — falling back to previous query: {search_term}")
                        break

        except Exception as e:
            print(f"DEBUG: Rewriter failed, falling back to raw query: {e}")
            search_term = user_query

    print(f"DEBUG: Final Search Term for Chroma: {search_term}")
    

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
    n_chunks = 4 if is_comparison else 6
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
    for i in range(len(results['documents'][0])):
        distance  = results['distances'][0][i]
        job_title = results['metadatas'][0][i].get('job_title')
        print(f"DEBUG: Chroma found '{job_title}' with distance {distance}")
        if distance > 0.5:
            print(f"DEBUG: Skipping '{job_title}' — distance too high: {distance}")
            continue
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

    current_user_content = (
        f"WorkBC career data:\n{top_context}\n\n"
        f"IMPORTANT: The ONLY careers available in WorkBC data for this query are:\n"
        f"- {careers_list}\n\n"
        f"You may ONLY mention these exact careers and their NOC codes/salaries/URLs from above. "
        f"Do NOT mention any career not in this list. "
        f"Do NOT invent NOC codes — WorkBC NOC codes are always 5 digits. "
        f"If the user asks about a career not in the list, use the closest match and explicitly state "
        f"'WorkBC does not have a specific profile for that term'.\n\n"
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

    except Exception as e:
        raise HTTPException(status_code=502, detail=f"LLM inference error: {str(e)}")

    return answer, search_term


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
            "- 'both' = user wants career info AND job postings\n\n"
            "EXAMPLES:\n"
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
            "Query: 'tell me about plumbers and show me jobs' -> both\n\n"
            "Query: 'electrical jobs in surrey, vancouver, richmond' -> job_search, keywords=electrical, city=Surrey,Vancouver,Richmond\n"
            "Query: 'nursing jobs in kelowna and kamloops' -> job_search, keywords=nursing, city=Kelowna,Kamloops\n"
            "Query: 'jobs in Toronto' -> job_search, keywords=null, city=Ontario\n"
            "Query: 'nursing jobs in Calgary' -> job_search, keywords=nursing, city=Alberta\n"
            "Query: 'jobs in Montreal' -> job_search, keywords=null, city=Quebec\n"
            "Query: 'developer jobs in Seattle' -> job_search, keywords=developer, city=Washington\n"
            "Query: 'show me jobs in Edmonton' -> job_search, keywords=null, city=Alberta\n"
            "Query: 'engineering jobs in San Francisco' -> job_search, keywords=engineering, city=California\n"
            "OUTPUT FORMAT:\n"
            "{\n"
            '  "intent": "job_search" or "career_info" or "both",\n'
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
            "You are a WorkBC Career Advisor. BE CONCISE. Use bullet points.\n\n"
            "CRITICAL RULES — never violate these:\n"
            "1. CONTEXT ONLY: Use ONLY the information provided below to answer. "
            "Read the provided data carefully before responding. "
            "NEVER start your response with phrases like 'Based on the context', "
            "'According to the data', 'Here is a comparison of', or any preamble. "
            "Start your response directly with the answer — for comparisons, start "
            "directly with the markdown table.\n"
            "just answer naturally as if you already know the information.\n"
            "2. IDENTITY CHECK: If the provided data contains the EXACT career the user asked about, "
            "describe only that one — ignore unrelated careers in the data. "
            "If the EXACT career is NOT in the provided data, but a closely related career exists, "
            "describe the closest match and clearly state: "
            "'WorkBC does not have a profile for [user's term], but the closest related career is [actual title]'. "
            "If NO related career exists in the data, follow Rule 6 — do NOT invent careers, NOC codes, or URLs.\n"
            "3. COMPARISON TABLE: ONLY if the user explicitly asks to compare, "
            "asks about differences, or uses words like 'versus' or 'vs', "
            "respond with ONLY a markdown table: NOC | Job Title | Key Difference | Salary. "
            "Include only the careers the user named — ignore other careers in the data even if present. "
            "Max 5 rows. No text before the table. No text after the table. "
            "Do NOT describe each career separately — the table IS the answer. "
            "Do NOT add disclaimers about careers not included.\n"
            "4. SINGLE CAREER FORMAT: For questions about ONE career, "
            "state the NOC code and **bold** salary in your FIRST bullet point. "
            "Example: '**Registered Nurses (NOC: 31301) — Salary: $87,229.63**'. "
            "Then list 6-8 bullet points of key duties. "
            "Never skip or defer the salary to later in the response.\n"            
            "5. URLS ONLY FROM CONTEXT: Format links as [View Career Profile](URL) "
            "using ONLY URLs that appear word-for-word in the Context. "
            "Each context chunk contains a URL: field — use that value for the link. "
            "NEVER invent, guess or construct URLs. "
            "If no URL exists in the context for a job, silently omit the link with no explanation.\n"
            "6. NO DATA: If the provided data has no relevant information, "
            "say: 'I don\'t have that information in WorkBC records.' "
            "If the user asks about a specific career that is NOT in the provided data, "
            "do NOT attempt to answer from general knowledge — just state it is not available. "
            "NEVER reference 'the context' or 'the provided context' in your response.\n"
            "7. LENGTH: For SINGLE-career questions, 6-8 bullet points maximum. "
            "Summarize the main duties — do not list every specialization or sub-role. "
            "For COMPARISON questions, respond with a markdown table only — "
            "no bullet points, no introduction, no closing remarks. "
            "Never start a table or list you cannot complete.\n"              
            "8. NO HALLUCINATION: Only mention job titles, NOC codes, salaries and URLs "
            "that appear word-for-word in the provided data. "
            "If a career is NOT in the data, do NOT invent it. "
            "NOC codes from WorkBC are 5 digits — never output 6-digit NOC codes. "
            "Never construct or guess URLs — only use the URL: field from the data. "
            "If you cannot find the answer in the data, say 'I don't have that information in WorkBC records' "
            "rather than answering from training knowledge."
        )

        answer        = ""
        career_answer = ""
        search_term   = user_query
        jobs          = []
        total         = 0
        page          = 1
        has_more      = False

        if intent == "career_info":
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
        }

    except HTTPException:
        raise
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Internal Server Error: {str(e)}")


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
