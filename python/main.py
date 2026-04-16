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

# Fixed at 800 for reliable T4 GPU performance (~7-10 seconds per response)
MAX_TOKENS = 800

# Number of job results per page
PAGE_SIZE = 5

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ---------------------------------------------------------------------------
# 2. INITIALIZATION
# ---------------------------------------------------------------------------
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device="cpu")

vllm_client = OpenAI(
    base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1",
    api_key="none",
    timeout=90.0,
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
# 3. REQUEST MODEL
# ---------------------------------------------------------------------------
class QueryRequest(BaseModel):
    prompt: str
    session_id: str


# ---------------------------------------------------------------------------
# 4. HELPERS
# ---------------------------------------------------------------------------

def strip_html(text: str) -> str:
    """Remove HTML tags and decode common HTML entities from job descriptions."""
    if not text:
        return ""
    text = re.sub(r'<[^>]+>', ' ', text)
    text = text.replace('&amp;',  '&')
    text = text.replace('&lt;',   '<')
    text = text.replace('&gt;',   '>')
    text = text.replace('&nbsp;', ' ')
    text = text.replace('&#39;',  "'")
    text = text.replace('&quot;', '"')
    text = re.sub(r'\s+', ' ', text).strip()
    return text


def parse_intent(raw: str) -> dict:
    """Parse intent JSON from LLM output, handling markdown fences and escaped underscores."""
    cleaned = (
        raw.strip()
           .removeprefix("```json")
           .removeprefix("```")
           .removesuffix("```")
           .strip()
           .replace("\\_", "_")
    )
    parsed = json.loads(cleaned)
    parsed["intent"] = parsed.get("intent") or "career_info"
    return parsed


BAD_URL_FRAGMENTS = [
    "dev2.workbc.ca",
    "/#",
    "#/",
    "localhost",
]


def build_job_url(src: dict) -> str:
    """
    Build the best available URL for a job posting.
    Priority: ApplyWebsite → ExternalSource → WorkBC job bank URL from JobId.
    """
    job_id     = src.get("JobId", "")
    workbc_url = f"{WORKBC_BASE_URL}/search-and-prepare-job/find-jobs#/job-details/{job_id}"

    apply_website = (src.get("ApplyWebsite") or "").strip()
    if apply_website and not any(bad in apply_website for bad in BAD_URL_FRAGMENTS):
        return apply_website

    external_url = (
        (src.get("ExternalSource") or {})
        .get("Source", [{}])[0]
        .get("Url", "")
        .strip()
    )
    if external_url:
        return external_url

    return workbc_url


def format_job_results(jobs: list, params: dict, total: int) -> str:
    """
    Format job results as markdown header text for the chat UI.
    Returns a no-results message with helpful suggestions when empty.
    """
    employer = params.get("employer")
    city     = params.get("city")
    keywords = params.get("keywords")

    if not jobs:
        if employer and city:
            return (
                f"I couldn't find any current **{employer}** postings in **{city}**. "
                f"They may not have active listings there right now.\n\n"
                f"Try asking: *{employer} jobs* to see all their BC postings."
            )
        elif employer:
            return (
                f"I couldn't find any current **{employer}** postings in WorkBC's job bank. "
                f"They may not have active listings right now."
            )
        else:
            return (
                "I searched WorkBC's job bank but couldn't find any current postings "
                "matching your request. Try broader keywords or a different location."
            )

    location_str = f" in **{city}**"       if city     else ""
    keyword_str  = f"**{employer}**"        if employer else \
                   f"**{keywords}**"         if keywords else "your search"
    return f"Found **{total}** job postings for {keyword_str}{location_str}:"


def search_jobs(params: dict, size: int = PAGE_SIZE, from_offset: int = 0) -> tuple[list, int]:
    """
    Build and execute an OpenSearch query.
    Returns (jobs, total_count).
    Supports employer wildcard filter for company-specific searches.
    """
    must_clauses   = []
    filter_clauses = [{"range": {"ExpireDate": {"gte": "now"}}}]

    # Job title keywords — skip if employer is specified to avoid noise
    if params.get("keywords") and not params.get("employer"):
        generic = {"jobs", "job", "work", "position", "positions", "opening", "openings"}
        if params["keywords"].lower().strip() not in generic:
            must_clauses.append({
                "multi_match": {
                    "query":  params["keywords"],
                    "fields": ["Title^3", "JobDescription"],
                }
            })

    # Employer wildcard — matches "Best Buy", "Best Buy Canada", "Best Buy Inc." etc.
    if params.get("employer"):
        filter_clauses.append({
            "wildcard": {
                "EmployerName.keyword": f"*{params['employer']}*"
            }
        })

    # City filter — City.keyword for exact matching
    city = params.get("city")
    if city and city.upper() not in ("BC", "BRITISH COLUMBIA"):
        filter_clauses.append({"terms": {"City.keyword": [city]}})

    if params.get("employment_type"):
        filter_clauses.append({
            "term": {"HoursOfWork.Description": params["employment_type"]}
        })

    if params.get("salary_min"):
        filter_clauses.append({
            "range": {"Salary": {"gte": params["salary_min"]}}
        })

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

    print(f"DEBUG: OpenSearch query (from={from_offset}): {json.dumps(os_query, indent=2)}")
    response = os_client.search(index="jobs_en", body=os_query)
    total    = response["hits"]["total"]["value"]
    print(f"DEBUG: OpenSearch returned {total} total matches")

    jobs = []
    for hit in response["hits"]["hits"]:
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

    return jobs, total


async def get_job_results(params: dict, from_offset: int = 0) -> tuple[list, int]:
    """Run job search in executor. Returns (jobs, total)."""
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(
        None, partial(search_jobs, params, PAGE_SIZE, from_offset)
    )


async def handle_load_more(session_id: str) -> dict:
    """
    Handle load more request — fetch next page of the last job search.
    Uses stored search params from Redis.
    """
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
    """
    Run the full RAG + Mistral career info flow.
    Returns (answer, search_term).
    Appends a truncation note if the response was cut off by max_tokens.
    """
    rewrite_prompt = (
        f"Current User Query: {user_query}\n"
        f"Last 2 Chat Messages: {sanitized_history[-2:]}\n\n"
        "TASK: Identify the EXACT job titles the user is asking about NOW. "
        "If this is a follow-up comparison question (e.g. 'what is the difference', "
        "'compare with', 'how does it compare', 'what about'), include BOTH the "
        "current job AND the job from the previous message. "
        "If the user is asking a completely NEW question, IGNORE the history. "
        "Output ONLY the job titles, comma separated. No explanation."
    )

    try:
        rewrite_res    = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=[{"role": "user", "content": rewrite_prompt}],
            temperature=0,
        )
        raw_content    = rewrite_res.choices[0].message.content.strip()
        lines          = [line.strip('- *123456789."\' ') for line in raw_content.split('\n')]
        filtered_lines = [
            l for l in lines
            if len(l) > 0
            and "Based on"      not in l
            and "Therefore"     not in l
            and "current query" not in l.lower()
        ]
        search_term = ", ".join(filtered_lines) if filtered_lines else user_query
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

    results = collection.query(query_embeddings=[q_emb], n_results=6)

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

    MAX_CONTEXT_CHARS = 3500
    truncated_chunks  = []
    total_chars       = 0
    for chunk in context_chunks:
        if total_chars + len(chunk) > MAX_CONTEXT_CHARS:
            break
        truncated_chunks.append(chunk)
        total_chars += len(chunk)

    top_context = "\n---\n".join(truncated_chunks) if truncated_chunks else "No WorkBC data found."

    history_window = sanitized_history[-2:]
    while history_window and history_window[0]["role"] != "user":
        history_window.pop(0)

    current_user_content = f"Context:\n{top_context}\n\nQuestion: {user_query}"

    final_messages = [
        {"role": "user",      "content": system_rules},
        {"role": "assistant", "content": "Understood. I will follow these guidelines strictly."},
        *history_window,
        {"role": "user",      "content": current_user_content},
    ]

    try:
        completion    = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=final_messages,
            temperature=0.0,
            max_tokens=MAX_TOKENS,
        )
        answer        = completion.choices[0].message.content
        finish_reason = completion.choices[0].finish_reason

        if finish_reason == "length":
            answer += (
                "\n\n---\n"
                "_Response was truncated due to length. "
                "Try asking a more specific question for complete information._"
            )
            print(f"DEBUG: Response truncated at max_tokens={MAX_TOKENS}")

    except Exception as e:
        raise HTTPException(status_code=502, detail=f"LLM inference error: {str(e)}")

    return answer, search_term


# ---------------------------------------------------------------------------
# 5. MAIN ENDPOINT
# ---------------------------------------------------------------------------
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key  = f"chat_history:{session_id}"

        # Load more — handle before any other processing
        if user_query.strip() == "__load_more__":
            return await handle_load_more(session_id)

        # --- History ---
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

        # --- Intent detection ---
        intent_prompt = (
            "Classify this query and return JSON only, no explanation, no markdown fences.\n\n"
            "RULES:\n"
            "- 'job_search' = user wants to find/see/browse actual job postings or openings\n"
            "- 'career_info' = user wants to learn about a career (duties, salary, education)\n"
            "- 'both' = user wants career info AND job postings\n\n"
            "EXAMPLES:\n"
            "Query: 'find nursing jobs in Vancouver' -> job_search, keywords=nursing, city=Vancouver\n"
            "Query: 'Best Buy jobs in Surrey' -> job_search, employer=Best Buy, city=Surrey\n"
            "Query: 'McDonald's part time jobs' -> job_search, employer=McDonald's, employment_type=Part-time\n"
            "Query: 'Telus jobs in Burnaby' -> job_search, employer=Telus, city=Burnaby\n"
            "Query: 'show me accounting jobs' -> job_search, keywords=accounting\n"
            "Query: 'any openings for teachers?' -> job_search, keywords=teacher\n"
            "Query: 'jobs paying over 80000' -> job_search, salary_min=80000\n"
            "Query: 'what does a nurse do?' -> career_info\n"
            "Query: 'what is the salary for a firefighter?' -> career_info\n"
            "Query: 'what education do I need to be a pharmacist?' -> career_info\n"
            "Query: 'tell me about plumbers and show me jobs' -> both\n\n"
            "OUTPUT FORMAT:\n"
            "{\n"
            '  "intent": "job_search" or "career_info" or "both",\n'
            '  "job_search_params": {\n'
            '    "keywords": "extracted job title or null",\n'
            '    "employer": "extracted company name or null",\n'
            '    "city": "extracted city or null",\n'
            '    "employment_type": "Full-time or Part-time or null",\n'
            '    "salary_min": null\n'
            "  }\n"
            "}\n\n"
            f"Query: {user_query}"
        )

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

        except json.JSONDecodeError as e:
            print(f"DEBUG: Intent JSON parse failed ({e}) — raw was: {repr(raw_intent)}")
            intent = "career_info"
            params = {}
        except Exception as e:
            print(f"DEBUG: Intent detection failed ({type(e).__name__}): {e}")
            intent = "career_info"
            params = {}

        print(f"DEBUG: Intent={intent} | Params={params}")

        # --- System rules ---
        system_rules = (
            "You are a WorkBC Career Advisor. BE CONCISE. Use bullet points. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. IDENTITY CHECK: If the context describes a job that is NOT what the user asked for "
            "(e.g., user asks for 'Teacher' but context is 'Principal'), do NOT use that data.\n"
            "3. If the user asks to compare careers OR asks about differences between careers, "
            "use a SHORT markdown table with MAXIMUM 3 columns: NOC | Job Title | Salary. "
            "Keep the table under 5 rows. Do not add explanation after the table.\n"
            "4. Always include the NOC code and **bold** salaries.\n"
            "5. Format links as [View Career Profile](URL).\n"
            "6. If context is missing, say you don't have that information in WorkBC records.\n"
            "7. Never start a table or list you cannot complete. "
            "If the response would be too long, summarize in bullet points instead."
        )

        # --- Route by intent ---
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
            jobs, total = await get_job_results(params, from_offset=0)
            has_more    = total > PAGE_SIZE

            # Store search params for pagination
            r.setex(
                f"job_search_params:{session_id}",
                3600,
                json.dumps({"params": params, "page": 1}),
            )

            answer = format_job_results(jobs, params, total)

        elif intent == "both":
            career_task = asyncio.create_task(
                get_career_answer(user_query, sanitized_history, system_rules)
            )
            jobs_task = asyncio.create_task(get_job_results(params, from_offset=0))

            (career_answer, search_term), (jobs, total) = await asyncio.gather(
                career_task, jobs_task
            )

            has_more = total > PAGE_SIZE

            r.setex(
                f"job_search_params:{session_id}",
                3600,
                json.dumps({"params": params, "page": 1}),
            )

            answer = format_job_results(jobs, params, total)

        # --- Save history ---
        history_answer = career_answer if intent == "both" else answer
        sanitized_history.append({"role": "user",     "content": user_query})
        sanitized_history.append({"role": "assistant", "content": history_answer})
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
# 6. HEALTH CHECK
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

 