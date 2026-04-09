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

# --- 3. OPENSEARCH HELPER (Live Job Bank) ---
def get_live_jobs(title="", city="", employer=""):
    if not os_client: return []
    must_clauses = []
    if title: must_clauses.append({"match": {"Title": {"query": title, "fuzziness": "AUTO"}}})
    if city: must_clauses.append({"match": {"City": city}})
    if employer: must_clauses.append({"match": {"EmployerName": {"query": employer, "fuzziness": "AUTO"}}})
    
    query = {"query": {"bool": {"must": must_clauses}}}
    try:
        res = os_client.search(index="jobs_en", body={**query, "size": 3})
        return [{
            "t": h['_source'].get("Title"),
            "e": h['_source'].get("EmployerName"),
            "c": h['_source'].get("City"),
            "s": h['_source'].get("SalarySummary"),
            "u": h['_source'].get("ExternalSource", {}).get("Source", [{"Url": "#"}])[0].get("Url")
        } for h in res['hits']['hits']]
    except: return []

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # 1. Clean History
        raw_history = r.get(redis_key)
        history = json.loads(raw_history) if raw_history else []
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        if sanitized_history and sanitized_history[-1]["role"] == "user": sanitized_history.pop()

        # 2. Advanced Extraction (The Router)
        extract_prompt = (
            f"History: {sanitized_history[-2:]}\nQuery: {user_query}\n\n"
            "Identify: 1. Job Title 2. City 3. Is user asking for LIVE OPENINGS? (Yes/No)\n"
            "Format: Title | City | IsJobSearch"
        )
        ex_res = vllm_client.chat.completions.create(model=MODEL_NAME, messages=[{"role": "user", "content": extract_prompt}], temperature=0)
        parts = ex_res.choices[0].message.content.strip().split("|")
        s_title = parts[0].strip() if len(parts) > 0 else user_query
        s_city = parts[1].strip() if len(parts) > 1 else ""
        is_job_request = "yes" in parts[2].lower() if len(parts) > 2 else False

            # --- STEP 3: RAG RETRIEVAL (Restored & Tuned) ---
    q_emb = bi_encoder.encode(
        f"Represent this sentence for searching: {search_term}",
        normalize_embeddings=True
    ).tolist()

    # n_results=2 is required so Rule #3 (Tables) can actually function!
    results = collection.query(query_embeddings=[q_emb], n_results=2)

    context_chunks = []
    for i in range(len(results['documents'][0])):
        distance = results['distances'][0][i]
        meta = results['metadatas'][0][i]
        
        # TIGHTENED SHIELD: 0.85 is the sweet spot for 'Identity Checks'
        if distance > 0.85:
            print(f"DEBUG: Skipping '{meta.get('job_title')}' - Distance {distance} too high.")
            continue

        # Robust Salary Boldness (Rule #4)
        salary_val = meta.get('salary', 'N/A')
        try:
            # Formats 125000 -> **$125,000.00**
            salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data missing"
        except:
            salary_str = f"**${salary_val}**"

        context_chunks.append(
            f"JOB_PROFILE_{i+1}:\n"
            f"TITLE: {meta.get('job_title')}\n"
            f"NOC: {meta.get('noc_code', 'N/A')}\n"
            f"SALARY: {salary_str}\n"
            f"PROFILE_URL: {meta.get('url', '#')}\n"
            f"DUTIES: {results['documents'][0][i]}"
        )

    # Logic to handle missing context (Rule #6)
    if not context_chunks:
        top_context = "No WorkBC data found for this query in our records."
    else:
        # Join the two careers for the LLM to compare
        top_context = "\n\n---\n\n".join(context_chunks)[:3800]

        # 4. Job Bank Append (OpenSearch) - ONLY if user asks
        job_list_text = ""
        if is_job_request:
            jobs = get_live_jobs(title=s_title, city=s_city)
            if jobs:
                job_list_text = "\n\n### 🔎 Current Openings\n"
                for j in jobs:
                    job_list_text += f"* **{j['t']}** at {j['e']} ({j['c']})\n  Pay: {j['s']}\n  [Apply Here]({j['u']})\n"
            else:
                job_list_text = "\n\n*(No live postings found in WorkBC records)*"

        # 5. Final Message Assembly using your Strict Rules
        system_rules = (
            "You are a WorkBC Career Advisor. BE CONCISE. Use bullet points. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. IDENTITY CHECK: If context describes a job NOT asked for, do NOT use it.\n"
            "3. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "4. Always include the NOC code and **bold** salaries.\n"
            "5. Format links as [View Career Profile](URL).\n"
            "6. If context is missing, say you don't have that information in WorkBC records."
        )

        final_messages = sanitized_history[-2:]
        final_messages.append({"role": "user", "content": f"{system_rules}\n\nContext:\n{top_context}\n\nQuestion: {user_query}"})

        completion = vllm_client.chat.completions.create(model=MODEL_NAME, messages=final_messages, temperature=0.0)
        answer = completion.choices[0].message.content + job_list_text

        # 6. Update Redis
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        traceback.print_exc()
        return {"error": str(e)}, 500

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)