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

# --- 3. WELCOME MESSAGE ---
def get_welcome_message():
    """Initial greeting with quick options"""
    return """# 👋 Welcome to WorkBC Career Assistant!

I can help you with:

**🔍 Job Search**
- "Show me recent job postings"
- "Find software developer jobs in Vancouver"
- "Search for nursing positions in Victoria"

**💼 Career Information**
- "What does a registered nurse do?"
- "Compare salaries for teacher vs accountant"
- "Tell me about NOC code 2174"

**📊 Career Planning**
- "What are the duties of a mechanical engineer?"
- "How much does a graphic designer make?"

---

**What would you like to know?**
"""

# --- 4. OPENSEARCH HELPERS ---
def get_recent_jobs(limit=10):
    """Get most recent jobs from Job Bank"""
    if not os_client: 
        return []
    
    try:
        res = os_client.search(
            index="jobs_en", 
            body={
                "query": {"match_all": {}},
                "sort": [{"_id": {"order": "desc"}}],
                "size": limit
            }
        )
        return [{
            "title": h['_source'].get("Title"),
            "employer": h['_source'].get("EmployerName"),
            "city": h['_source'].get("City"),
            "salary": h['_source'].get("SalarySummary", "Not specified"),
            "url": h['_source'].get("ExternalSource", {}).get("Source", [{"Url": "#"}])[0].get("Url", "#")
        } for h in res['hits']['hits']]
    except Exception as e:
        print(f"OpenSearch recent jobs error: {e}")
        return []

def get_live_jobs(title="", city="", employer=""):
    """Search for specific jobs"""
    if not os_client: 
        return []
    
    must_clauses = []
    if title: 
        must_clauses.append({"match": {"Title": {"query": title, "fuzziness": "AUTO"}}})
    if city: 
        must_clauses.append({"match": {"City": city}})
    if employer: 
        must_clauses.append({"match": {"EmployerName": {"query": employer, "fuzziness": "AUTO"}}})
    
    query = {"query": {"bool": {"must": must_clauses}}} if must_clauses else {"query": {"match_all": {}}}
    
    try:
        res = os_client.search(index="jobs_en", body={**query, "size": 15})
        return [{
            "title": h['_source'].get("Title"),
            "employer": h['_source'].get("EmployerName"),
            "city": h['_source'].get("City"),
            "salary": h['_source'].get("SalarySummary", "Not specified"),
            "url": h['_source'].get("ExternalSource", {}).get("Source", [{"Url": "#"}])[0].get("Url", "#")
        } for h in res['hits']['hits']]
    except Exception as e:
        print(f"OpenSearch search error: {e}")
        return []

def format_jobs_as_text(jobs, title="Job Openings"):
    """Format job list as markdown text for React chatbot"""
    if not jobs:
        return ""
    
    text = f"## 📋 {title}\n\n"
    for idx, j in enumerate(jobs, 1):
        text += (
            f"**{idx}. {j['title']}**\n"
            f"   • Company: {j['employer']}\n"
            f"   • Location: {j['city']}\n"
            f"   • Salary: {j['salary']}\n"
            f"   • [Apply Now]({j['url']})\n\n"
        )
    return text

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt.strip()
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # 1. Get or Create History
        raw_history = r.get(redis_key)
        history = json.loads(raw_history) if raw_history else []
        
        # AUTO-WELCOME: If this is the first interaction, send welcome message
        if not history:
            welcome = get_welcome_message()
            initial_history = [{"role": "assistant", "content": welcome}]
            r.setex(redis_key, 3600, json.dumps(initial_history))
            
            # If user sent an empty query, just return welcome
            if not user_query:
                return {"answer": welcome, "session_id": session_id}
            
            # Otherwise, set history and continue processing their query
            history = initial_history

        # Handle empty queries for existing conversations
        if not user_query:
            return {
                "answer": "Please ask me a question! Try 'Show me recent jobs' or 'What does a nurse do?'",
                "session_id": session_id
            }

        # 2. Clean History
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        if sanitized_history and sanitized_history[-1]["role"] == "user": 
            sanitized_history.pop()

        # 3. Query Classification
        classify_prompt = (
            f"History: {sanitized_history[-2:]}\n"
            f"User Query: {user_query}\n\n"
            "Classify this query:\n"
            "A) RECENT_JOBS - User wants to see latest/recent job postings (no specific search)\n"
            "B) JOB_SEARCH - User wants to search for specific jobs (title, location, company)\n"
            "C) CAREER_INFO - User wants career information, duties, salary data, comparisons\n\n"
            "Also extract:\n"
            "- Job Title (if mentioned)\n"
            "- City/Location (if mentioned)\n\n"
            "Format: CATEGORY | Job Title | City"
        )
        
        classify_res = vllm_client.chat.completions.create(
            model=MODEL_NAME, 
            messages=[{"role": "user", "content": classify_prompt}], 
            temperature=0
        )
        
        parts = classify_res.choices[0].message.content.strip().split("|")
        category = parts[0].strip().upper() if len(parts) > 0 else "CAREER_INFO"
        job_title = parts[1].strip() if len(parts) > 1 else ""
        city = parts[2].strip() if len(parts) > 2 else ""

        print(f"DEBUG: Category={category}, Title={job_title}, City={city}")

        # ROUTING LOGIC
        if "RECENT_JOBS" in category or any(keyword in user_query.lower() for keyword in ["recent job", "latest job", "new posting", "what's available"]):
            # ===== PATH A: SHOW RECENT JOBS =====
            recent_jobs = get_recent_jobs(limit=10)
            
            if recent_jobs:
                answer = "Here are the **10 most recent job postings** from WorkBC Job Bank:\n\n"
                answer += format_jobs_as_text(recent_jobs, "Latest Postings")
                answer += "\n💡 **Want to search for something specific?** Try:\n"
                answer += "- 'Find software developer jobs in Vancouver'\n"
                answer += "- 'Show me nursing positions in Victoria'\n"
                answer += "- 'Search for accountant jobs'"
            else:
                answer = "Unable to retrieve recent jobs at the moment. Please try again later."
        
        elif "JOB_SEARCH" in category or any(keyword in user_query.lower() for keyword in ["find job", "search job", "hiring", "opening", "apply", "job posting", "available position", "show me job"]):
            # ===== PATH B: JOB SEARCH =====
            jobs = get_live_jobs(title=job_title, city=city)
            
            if jobs:
                search_desc = f"'{job_title}'" if job_title else "all jobs"
                if city:
                    search_desc += f" in {city}"
                
                answer = f"Found **{len(jobs)} job opening(s)** for {search_desc}:\n\n"
                answer += format_jobs_as_text(jobs, f"Search Results")
                answer += "\n💡 **Refine your search:** Try adding location or different job titles."
            else:
                search_terms = []
                if job_title:
                    search_terms.append(f"'{job_title}'")
                if city:
                    search_terms.append(f"in {city}")
                
                answer = f"No job postings found {' '.join(search_terms)}.\n\n"
                answer += "**Tips:**\n"
                answer += "- Try broader search terms (e.g., 'developer' instead of 'senior React developer')\n"
                answer += "- Remove location filters\n"
                answer += "- Check spelling\n\n"
                answer += "Or ask: *'Show me recent jobs'* to see the latest postings."
        
        else:
            # ===== PATH C: CAREER INFO (ChromaDB + LLM) =====
            search_term = job_title if job_title else user_query
            q_emb = bi_encoder.encode(
                f"Represent this sentence for searching: {search_term}",
                normalize_embeddings=True
            ).tolist()

            results = collection.query(query_embeddings=[q_emb], n_results=2)

            context_chunks = []
            for i in range(len(results['documents'][0])):
                distance = results['distances'][0][i]
                meta = results['metadatas'][0][i]
                
                if distance > 0.85:
                    print(f"DEBUG: Skipping '{meta.get('job_title')}' - Distance {distance:.3f} too high")
                    continue

                salary_val = meta.get('salary', 'N/A')
                try:
                    salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data unavailable"
                except:
                    salary_str = f"**{salary_val}**"

                context_chunks.append(
                    f"### Career Profile {i+1}\n"
                    f"**Title:** {meta.get('job_title')}\n"
                    f"**NOC Code:** {meta.get('noc_code', 'N/A')}\n"
                    f"**Salary:** {salary_str}\n"
                    f"**Profile:** {meta.get('url', '#')}\n"
                    f"**Duties:**\n{results['documents'][0][i]}\n"
                )

            if not context_chunks:
                top_context = "No WorkBC career data found for this query."
            else:
                top_context = "\n\n---\n\n".join(context_chunks)[:4000]

            system_rules = (
                "You are a WorkBC Career Advisor. Follow these rules strictly:\n"
                "1. Use ONLY the provided Context. Do not use external knowledge.\n"
                "2. If the context doesn't match the query, say so clearly.\n"
                "3. When comparing careers, use a markdown table.\n"
                "4. Always include NOC codes and bold salaries.\n"
                "5. Format profile links as [View Profile](URL).\n"
                "6. Be concise and use bullet points.\n"
            )

            final_messages = sanitized_history[-2:] if sanitized_history else []
            final_messages.append({
                "role": "user", 
                "content": f"{system_rules}\n\nContext:\n{top_context}\n\nQuestion: {user_query}"
            })

            completion = vllm_client.chat.completions.create(
                model=MODEL_NAME, 
                messages=final_messages, 
                temperature=0.0
            )
            answer = completion.choices[0].message.content

        # 4. Update Redis
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        traceback.print_exc()
        return {"error": str(e)}, 500

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
