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

os_client = None
if all([OS_HOST, OS_USER, OS_PASS]):
    clean_host = OS_HOST.replace("https://", "").replace("http://", "").split(":")[0]
    os_client = OpenSearch(
        hosts=[{'host': clean_host, 'port': 443}],
        http_auth=(OS_USER, OS_PASS),
        use_ssl=True, verify_certs=True, ssl_assert_hostname=False, ssl_show_warn=False
    )

class QueryRequest(BaseModel):
    prompt: str
    session_id: str

# --- 3. DETERMINISTIC OPENSEARCH HELPER ---
def get_live_jobs(title: str = "", city: str = "", employer: str = ""):
    if not os_client: return {"count": 0, "jobs": []}
    must_clauses = []
    # If title is extracted as 'Teaching', we search 'Teacher' as well for better hits
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
        jobs = []
        for hit in search_res['hits']['hits']:
            s = hit['_source']
            jobs.append({"title": s.get("Title"), "employer": s.get("EmployerName"), "city": s.get("City"), "url": s.get("ApplyWebsite", "#")})
        return {"count": count_res['count'], "jobs": jobs}
    except Exception as e:
        return {"count": 0, "jobs": []}

# --- 4. MAIN API ROUTE ---
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # 1. History
        raw_history = r.get(redis_key)
        history = json.loads(raw_history) if raw_history else []
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        if sanitized_history and sanitized_history[-1]["role"] == "user": sanitized_history.pop()

        # 2. Extract Data (Router)
        rewrite_prompt = (
            f"History: {sanitized_history[-2:]}\n"
            f"Query: {user_query}\n\n"
            "Extract: Job Title | City | Employer. Use history if the user says 'What about...'.\n"
            "Format: Title | City | Employer"
        )
        try:
            rw = vllm_client.chat.completions.create(model=MODEL_NAME, messages=[{"role": "user", "content": rewrite_prompt}], temperature=0.0)
            parts = rw.choices[0].message.content.strip().split("|")
            s_title = parts[0].strip() if len(parts) > 0 else user_query
            s_city = parts[1].strip() if len(parts) > 1 else ""
            s_employer = parts[2].strip() if len(parts) > 2 else ""
        except:
            s_title, s_city, s_employer = user_query, "", ""

        # 3. Career Retrieval (SHIELDED)
        q_emb = bi_encoder.encode(f"Represent: {s_title}", normalize_embeddings=True).tolist()
        chroma_res = collection.query(query_embeddings=[q_emb], n_results=1) # Only need the best match
        
        career_context = ""
        if chroma_res['documents'][0]:
            distance = chroma_res['distances'][0][0]
            # --- TIGHT FILTER: Only allow very close matches (0.8 max) ---
            if distance < 0.82: 
                meta = chroma_res['metadatas'][0][0]
                sal = meta.get('salary', 'N/A')
                career_context = f"JOB: {meta.get('job_title')}\nSALARY: **${sal}**\nURL: {meta.get('url')}\nDUTIES: {chroma_res['documents'][0][0]}"

        # 4. Job Search
        is_job_request = any([s_city, s_employer, "job" in user_query.lower()])
        job_data = get_live_jobs(s_title, s_city, s_employer) if is_job_request else {"count": 0, "jobs": []}

        # 5. Build Advice
        system_rules = (
            "You are a WorkBC Career Advisor. Rules:\n"
            "1. ONLY use provided Career Context. If empty, say you don't have that profile.\n"
            "2. Mention the job count from 'Job Bank Summary'.\n"
            "3. DO NOT list individual jobs in your response text."
        )

        job_summary = f"Found {job_data['count']} live jobs."
        ctx_for_llm = career_context if career_context else "No matching career profile in records."
        
        prompt_content = f"Career Context:\n{ctx_for_llm}\n\nJob Bank Summary: {job_summary}\nQuestion: {user_query}"
        
        messages = [{"role": "user", "content": f"{system_rules}\n\n{prompt_content}"}]
        
        completion = vllm_client.chat.completions.create(model=MODEL_NAME, messages=messages, temperature=0.0)
        answer = completion.choices[0].message.content

        # 6. FORCE APPEND JOBS (The Bridge)
        # This part happens outside the LLM, so it CANNOT be ignored or hallucinated.
        if job_data['count'] > 0:
            job_list_text = f"\n\n### 🔎 Job Bank Results for {s_title} ({job_data['count']} found)\n"
            for j in job_data['jobs']:
                job_list_text += f"* **{j['title']}** at {j['employer']} ({j['city']}) - [Apply]({j['url']})\n"
            answer += job_list_text
        elif is_job_request:
            answer += "\n\n*(No live postings found for this search)*"

        # 7. Save & Return
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        traceback.print_exc()
        return {"error": str(e)}, 500

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)