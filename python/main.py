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

# OpenSearch Connection
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

# OpenSearch Client Setup
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
    if title:
        must_clauses.append({"multi_match": {"query": title, "fields": ["Title^3", "JobDescription"], "fuzziness": "AUTO"}})
    if city:
        must_clauses.append({"match_phrase": {"City": city}})
    if employer:
        must_clauses.append({"match_phrase": {"EmployerName": employer}})
    
    query = {"query": {"bool": {"must": must_clauses}}} if must_clauses else {"query": {"match_all": {}}}
    
    try:
        count_res = os_client.count(index="jobs_en", body=query)
        search_res = os_client.search(index="jobs_en", body={**query, "size": 5}) # Top 5 for Phase 1
        
        jobs = []
        for hit in search_res['hits']['hits']:
            s = hit['_source']
            jobs.append({
                "title": s.get("Title"), 
                "employer": s.get("EmployerName"), 
                "city": s.get("City"), 
                "url": s.get("ApplyWebsite", "#")
            })
        return {"count": count_res['count'], "jobs": jobs}
    except Exception as e:
        print(f"OpenSearch Error: {e}")
        return {"count": 0, "jobs": []}

# --- 4. MAIN API ROUTE ---
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # STEP 1: History Sanitization
        raw_history = r.get(redis_key)
        history = json.loads(raw_history) if raw_history else []
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        if sanitized_history and sanitized_history[-1]["role"] == "user": sanitized_history.pop()

        # STEP 2: Extraction/Router (LLM as Navigator)
        rewrite_prompt = (
            f"History: {sanitized_history[-2:]}\n"
            f"Current Query: {user_query}\n\n"
            "TASK: Extract Job Title | City | Employer. Use history for context.\n"
            "FORMAT: Title | City | Employer"
        )
        try:
            rw_res = vllm_client.chat.completions.create(model=MODEL_NAME, messages=[{"role": "user", "content": rewrite_prompt}], temperature=0.0)
            parts = rw_res.choices[0].message.content.strip().split("|")
            s_title = parts[0].strip() if len(parts) > 0 else user_query
            s_city = parts[1].strip() if len(parts) > 1 else ""
            s_employer = parts[2].strip() if len(parts) > 2 else ""
        except:
            s_title, s_city, s_employer = user_query, "", ""

        # STEP 3: Career Advice Retrieval (ChromaDB)
        q_emb = bi_encoder.encode(f"Represent: {s_title}", normalize_embeddings=True).tolist()
        chroma_res = collection.query(query_embeddings=[q_emb], n_results=2)
        
        context_chunks = []
        for i in range(len(chroma_res['documents'][0])):
            distance = chroma_res['distances'][0][i]
            if distance > 1.0: continue # Distance Wall

            meta = chroma_res['metadatas'][0][i]
            salary_val = meta.get('salary', 'N/A')
            try:
                salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data missing"
            except:
                salary_str = f"**${salary_val}**"

            context_chunks.append(
                f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
                f"SALARY: {salary_str}\n"
                f"URL: {meta.get('url', '#')}\n"
                f"CONTENT: {chroma_res['documents'][0][i]}"
            )

        top_context = "\n---\n".join(context_chunks) if context_chunks else "No WorkBC data found."

        # STEP 4: Live Job Bank Retrieval (OpenSearch)
        is_job_request = any([s_city, s_employer, "job" in user_query.lower(), "hiring" in user_query.lower()])
        job_data = get_live_jobs(s_title, s_city, s_employer) if is_job_request else {"count": 0, "jobs": []}

        # STEP 5: Advice Generation
        system_rules = (
            "You are a WorkBC Career Advisor. Rules:\n"
            "1. Use 'Career Context' for duties and **bold** salaries.\n"
            "2. If 'Job Bank Data' shows matches, mention the exact count.\n"
            "3. DO NOT list individual jobs in your text response. State: 'I have displayed the results below.'\n"
            "4. Format links as [View Career Profile](URL)."
            "5. IDENTITY CHECK: If the context describes a job that is NOT what the user asked for "
            "(e.g., user asks for 'Teacher' but context is 'Principal'), do NOT use that data. "
            "6. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "7. Always include the NOC code and **bold** salaries.\n"
            "8. If context is missing, say you don't have that information in WorkBC records."

        )

        history_window = sanitized_history[-2:]
        if history_window and history_window[0]["role"] == "assistant": history_window.pop(0)

        current_content = f"Context:\n{top_context}\n\nJob Bank Data: Found {job_data['count']} jobs.\nQuestion: {user_query}"
        
        final_messages = []
        if not history_window:
            final_messages.append({"role": "user", "content": f"{system_rules}\n\n{current_content}"})
        else:
            first_msg = history_window[0].copy()
            first_msg["content"] = f"{system_rules}\n\n{first_msg['content']}"
            final_messages = [first_msg] + history_window[1:] + [{"role": "user", "content": current_content}]

        completion = vllm_client.chat.completions.create(model=MODEL_NAME, messages=final_messages, temperature=0.0)
        answer = completion.choices[0].message.content

        # --- STEP 6: THE PHASE 1 BRIDGE (Zero-React-Mod) ---
        # Attach the hard OpenSearch data to the answer string manually.
        if job_data['count'] > 0:
            job_section = f"\n\n### 🔎 Job Bank Results ({job_data['count']} found)\n"
            job_list_text = ""
            for j in job_data['jobs']:
                job_list_text += f"* **{j['title']}** at {j['employer']} ({j['city']}) - [Apply]({j['url']})\n"
            answer = f"{answer}{job_section}{job_list_text}"
        elif is_job_request:
            answer = f"{answer}\n\n*(No live postings found for this search in the Job Bank)*"

        # Save History
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        # Return (React only sees 'answer', which now contains everything)
        return {
            "answer": answer, 
            "session_id": session_id
        }

    except Exception as e:
        traceback.print_exc()
        return {"error": str(e)}, 500

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)