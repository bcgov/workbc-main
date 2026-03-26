import os
import json
import redis
import traceback
import uvicorn
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import chromadb
from sentence_transformers import SentenceTransformer
from openai import OpenAI
from opensearchpy import OpenSearch

# --- 1. CONFIGURATION ---
REDIS_HOST = os.getenv("REDIS_HOST2", "localhost")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
MISTRAL_HOST = os.getenv("MISTRAL_SERVICE_SERVICE_HOST", "mistral-service")
MISTRAL_PORT = os.getenv("MISTRAL_SERVICE_SERVICE_PORT", "80")
CHROMA_HOST = os.getenv("CHROMA_SERVICE_HOST", "chroma-service")
CHROMA_PORT = os.getenv("CHROMA_SERVICE_PORT", "8000")

# OpenSearch Credentials
OS_HOST = os.getenv("ConnectionStrings__ElasticSearchServer")
OS_USER = os.getenv("IndexSettings__ElasticUser")
OS_PASS = os.getenv("IndexSettings__ElasticPassword")

app = FastAPI()
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_credentials=True, allow_methods=["*"], allow_headers=["*"])

# --- 2. INITIALIZATION ---
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device="cpu")
vllm_client = OpenAI(base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1", api_key="none", timeout=120.0)
MODEL_NAME = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"
chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection = chroma_client.get_collection("career_content")
r = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)

# OpenSearch Client
os_client = None
if all([OS_HOST, OS_USER, OS_PASS]):
    clean_host = OS_HOST.replace("https://", "").replace("http://", "").split(":")[0]
    os_client = OpenSearch(
        hosts=[{'host': clean_host, 'port': 443}],
        http_auth=(OS_USER, OS_PASS),
        use_ssl=True, verify_certs=True, ssl_assert_hostname=False, ssl_show_warn=False
    )

# --- 3. HELPERS ---

def get_live_job_data(title: str = "", city: str = "", employer: str = ""):
    """Queries OpenSearch for precise job filtering and returns a summary count + list."""
    if not os_client: return {"count": 0, "jobs": []}
    
    must_clauses = []
    if title:
        must_clauses.append({"multi_match": {"query": title, "fields": ["Title^3", "JobDescription"], "fuzziness": "AUTO"}})
    if city:
        must_clauses.append({"match_phrase": {"City": city}})
    if employer:
        must_clauses.append({"match_phrase": {"EmployerName": employer}})
    
    query = {"query": {"bool": {"must": must_clauses}}} if must_clauses else {"query": {"match_all": {}}}
    
    try:
        count_res = os_client.count(index="jobs_en", body=query)
        search_res = os_client.search(index="jobs_en", body={**query, "size": 5})
        
        jobs = [{
            "title": h['_source'].get("Title"),
            "employer": h['_source'].get("EmployerName"),
            "location": ", ".join(h['_source'].get("City", [])),
            "salary": h['_source'].get("SalarySummary", "Not listed"),
            "url": h['_source'].get("ApplyWebsite")
        } for h in search_res['hits']['hits']]
        
        return {"count": count_res['count'], "jobs": jobs}
    except:
        return {"count": 0, "jobs": []}

class QueryRequest(BaseModel):
    prompt: str
    session_id: str

# --- 4. THE API ROUTE ---

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # 1. History Handling
        raw_h = r.get(redis_key)
        history = json.loads(raw_h) if raw_h else []
        sanitized_h = []
        role = "user"
        for m in history:
            if m["role"] == role:
                sanitized_h.append(m); role = "assistant" if role == "user" else "user"
        if sanitized_h and sanitized_h[-1]["role"] == "user": sanitized_h.pop()

        # 2. Advanced Query Rewriting (Entity Extraction)
        rewrite_prompt = (
            f"Query: {user_query}\nHistory: {sanitized_h[-2:]}\n"
            "TASK: Extract Job Title, City, and Employer.\n"
            "FORMAT: Title | City | Employer\n"
            "Example: 'Any jobs at Best Buy in Surrey?' -> | Surrey | Best Buy"
        )
        try:
            rw = vllm_client.chat.completions.create(model=MODEL_NAME, messages=[{"role": "user", "content": rewrite_prompt}], temperature=0)
            parts = rw.choices[0].message.content.split("|")
            s_title = parts[0].strip().strip("- *\"' ")
            s_city = parts[1].strip().strip("- *\"' ") if len(parts) > 1 else ""
            s_employer = parts[2].strip().strip("- *\"' ") if len(parts) > 2 else ""
        except:
            s_title, s_city, s_employer = user_query, "", ""

        # 3. ChromaDB Retrieval (Career Advice)
        q_emb = bi_encoder.encode(f"Represent: {s_title}", normalize_embeddings=True).tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=1)
        
        career_context = ""
        if results['documents'][0] and results['distances'][0][0] < 1.0:
            meta = results['metadatas'][0][0]
            career_context = f"JOB: {meta.get('job_title')}\nSALARY: {meta.get('salary')}\nCONTENT: {results['documents'][0][0]}"

        # 4. OpenSearch Retrieval (Live Jobs)
        # GATEKEEPER: Only search if intent is "hiring/job" or specific entity found
        is_job_search = any([s_city, s_employer, "job" in user_query.lower(), "hiring" in user_query.lower()])
        job_data = get_live_job_data(s_title, s_city, s_employer) if is_job_search else {"count": 0, "jobs": []}

        # 5. Advice Generation
        summary = f"I found {job_data['count']} active job postings matching your request." if is_job_search else ""
        system_rules = (
            "You are a WorkBC Career Advisor. Use ONLY the provided context for requirements. "
            "If a job count is provided, mention it in your summary. Do not list individual jobs in your text."
        )
        
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=[{"role": "user", "content": f"{system_rules}\n\nContext: {career_context}\nSummary: {summary}\nUser: {user_query}"}],
            temperature=0.0
        )
        answer = completion.choices[0].message.content

        # Update Redis
        sanitized_h.append({"role": "user", "content": user_query})
        sanitized_h.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_h[-10:]))

        return {
            "answer": answer,
            "live_jobs": job_data['jobs'],
            "total_count": job_data['count'],
            "session_id": session_id
        }

    except Exception as e:
        traceback.print_exc()
        return {"error": str(e)}, 500

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)