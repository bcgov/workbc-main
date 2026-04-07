import os
import json
import redis
import traceback
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import chromadb
from sentence_transformers import SentenceTransformer
from openai import OpenAI
import uvicorn

# --- 1. CONFIGURATION ---
REDIS_HOST = os.getenv("REDIS_HOST2", "localhost")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
MISTRAL_HOST = os.getenv("MISTRAL_SERVICE_SERVICE_HOST", "mistral-service")
MISTRAL_PORT = os.getenv("MISTRAL_SERVICE_SERVICE_PORT", "80")
CHROMA_HOST = os.getenv("CHROMA_SERVICE_HOST", "chroma-service")
CHROMA_PORT = os.getenv("CHROMA_SERVICE_PORT", "8000")

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- 2. INITIALIZATION ---
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device="cpu")
vllm_client = OpenAI(
    base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1",
    api_key="none",
    timeout=120.0
)

MODEL_NAME = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"
chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection = chroma_client.get_collection("career_content")
r = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)

class QueryRequest(BaseModel):
    prompt: str
    session_id: str

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # --- STEP 1: RETRIEVE & CLEAN HISTORY (The "400 Error" Fix) ---
        try:
            raw_history = r.get(redis_key)
            history = json.loads(raw_history) if raw_history else []
        except:
            history = []

        # Logic to ensure strict user/assistant alternating roles
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"

        # Since our next message IS a 'user' message, history MUST end with 'assistant'
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # --- STEP 2: QUERY REWRITING (Fixes "the position" search) ---
        rewrite_prompt = (
            f"Current User Query: {user_query}\n"
            f"Last 2 Chat Messages: {sanitized_history[-2:]}\n\n"
            "TASK: Identify the EXACT job titles the user is asking about NOW. "
            "If the user is asking a NEW question (e.g. switching from Arts to Trades), "
            "IGNORE the history. Output ONLY the job titles for the NEW search."
        )

        try:
            rewrite_res = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": rewrite_prompt}],
                temperature=0
            )
            raw_content = rewrite_res.choices[0].message.content.strip()

            # 1. Split by newlines and remove bullet points/numbers/quotes
            lines = [line.strip('- *123456789."\' ') for line in raw_content.split('\n')]

            # 2. Filter out conversational filler like "Based on..." or "The job is..."
            filtered_lines = [
                l for l in lines
                if len(l) > 0 and "Based on" not in l and "Therefore" not in l and "current query" not in l.lower()
            ]

            # 3. Join them back together or fallback to the raw content if the filter was too aggressive
            search_term = ", ".join(filtered_lines) if filtered_lines else raw_content
        except Exception as e:
            print(f"DEBUG: Rewriter failed: {e}")
            search_term = user_query

        print(f"DEBUG: Final Search Term for Chroma: {search_term}")


            #search_term = rewrite_res.choices[0].message.content.strip()
       # except:
        #    search_term = user_query
        #print(f"DEBUG: Search Term being used: {search_term}")


        # --- STEP 3: RAG RETRIEVAL (Fixes Salary Hallucination) ---
        q_emb = bi_encoder.encode(
            f"Represent this sentence for searching: {search_term}",
            normalize_embeddings=True
        ).tolist()

        results = collection.query(query_embeddings=[q_emb], n_results=2)

        context_chunks = []
        for i in range(len(results['documents'][0])):

            # --- NEW: The 'Soft' Distance Filter ---
            # BGE embeddings usually score relevant matches between 0.4 and 0.9.
            # Anything above 1.1 is likely completely unrelated to the query.
            distance = results['distances'][0][i]
            job_title = results['metadatas'][0][i].get('job_title') # Get the title

            # This shows you exactly what Chroma found and how 'far' it is
            print(f"DEBUG: Chroma found '{job_title}' with distance {distance}")
            if distance > 1.0:
                print(f"DEBUG: Skipping result {i} due to high distance: {distance}")
                continue
            # ---------------------------------------

            # REMOVED: The 'if distance < 0.50' check that was causing hallucinations
            meta = results['metadatas'][0][i]
            salary_val = meta.get('salary', 'N/A')

            # Formatting salary like your V1 script
            try:
                salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data missing"
            except:
                salary_str = f"**${salary_val}**"

            context_chunks.append(
                f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
                f"SALARY: {salary_str}\n"
                f"URL: {meta.get('url', '#')}\n"
                f"CONTENT: {results['documents'][0][i]}"
            )

        full_context = "\n---\n".join(context_chunks) if context_chunks else "No WorkBC data found for this query."
        top_context = full_context[:3500]

        #top_context = "\n---\n".join(context_chunks) if context_chunks else "No WorkBC data found for this query."


        # --- STEP 4: FINAL MESSAGE ASSEMBLY (The "Stone Wall" Logic) ---
        # 1. Rules move inside the user content to avoid 'system' role conflicts
        system_rules = (
            "You are a WorkBC Career Advisor. BE CONCISE. Use bullet points. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. IDENTITY CHECK: If the context describes a job that is NOT what the user asked for "
            "(e.g., user asks for 'Teacher' but context is 'Principal'), do NOT use that data. "
            "3. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "4. Always include the NOC code and **bold** salaries.\n"
            "5. Format links as [View Career Profile](URL).\n"
            "6. If context is missing, say you don't have that information in WorkBC records."
        )

        # Build history window (last 2 messages / 1 messages)
        history_window = sanitized_history[-2:]
        if history_window and history_window[0]["role"] == "assistant":
            history_window.pop(0)

        # Build message list for the LLM
        final_messages = []
        final_messages.extend(history_window)

        current_user_content = f"Context:\n{top_context}\n\nQuestion: {user_query}"

        # FIXED: Prepend rules to the first message of this specific prompt
        # without corrupting the permanent Redis history.
        if not final_messages:
            final_messages.append({"role": "user", "content": f"{system_rules}\n\n{current_user_content}"})
        else:
            # We copy the first message so we don't modify the reference in sanitized_history
            first_msg_copy = final_messages[0].copy()
            first_msg_copy["content"] = f"{system_rules}\n\n{first_msg_copy['content']}"
            final_messages[0] = first_msg_copy
            final_messages.append({"role": "user", "content": current_user_content})



        # --- STEP 5: GENERATE & SAVE ---
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=final_messages,
            temperature=0.0, # Forces accuracy over creativity
            max_tokens=800
        )
        answer = completion.choices[0].message.content

        # Update Redis with clean User/Assistant pairs
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id, "debug_search": search_term}

    except Exception as e:
        traceback.print_exc()
        return {"error": f"Internal Server Error: {str(e)}"}, 500

# --- NEW: HEALTH CHECK ---
@app.get("/health")
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)