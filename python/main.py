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
            f"Based on history, what specific job title is being discussed? "
            f"Output ONLY the title.\nHistory: {sanitized_history[-2:]}\nQuery: {user_query}"
        )
        try:
            rewrite_res = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": rewrite_prompt}],
                temperature=0
            )
            search_term = rewrite_res.choices[0].message.content.strip()
        except:
            search_term = user_query

        # --- STEP 3: RAG RETRIEVAL (Fixes Salary Hallucination) ---
        q_emb = bi_encoder.encode(f"Represent this sentence for searching: {search_term}").tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=5)
        
        context_chunks = []
        for i in range(len(results['documents'][0])):
            if results['distances'][0][i] < 0.50:
                meta = results['metadatas'][0][i]
                context_chunks.append(
                    f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
                    f"SALARY: **${meta.get('salary', 'N/A')}**\n"
                    f"URL: {meta.get('url', '#')}\n"
                    f"CONTENT: {results['documents'][0][i]}"
                )

        top_context = "\n---\n".join(context_chunks) if context_chunks else "No WorkBC data found."

        # --- STEP 4: FINAL MESSAGE ASSEMBLY (The "Stone Wall" Logic) ---
        # 1. Rules move inside the user content to avoid 'system' role conflicts
        system_rules = (
            "You are a WorkBC Career Advisor. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "3. Always include the NOC code and **bold** salaries.\n"
            "4. Format links as [View Career Profile](URL).\n"
            "5. If context is missing, say you don't have that information in WorkBC records."
        )

        final_messages = []
        # Keep last 6 messages (3 turns)
        history_window = sanitized_history[-6:]
        
        # Ensure we always start the list with a 'user' role
        if history_window and history_window[0]["role"] == "assistant":
            history_window.pop(0)

        final_messages.extend(history_window)

        # Build the final payload
        current_user_content = f"Context:\n{top_context}\n\nQuestion: {user_query}"
        
        # If it's the start of a chat, put rules in the first message
        if not final_messages:
            final_messages.append({"role": "user", "content": f"{system_rules}{current_user_content}"})
        else:
            # Inject rules into the very first message of the thread for persistence
            final_messages[0]["content"] = system_rules + final_messages[0]["content"]
            final_messages.append({"role": "user", "content": current_user_content})

        # --- STEP 5: GENERATE & SAVE ---
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=final_messages,
            temperature=0.0, # Forces accuracy over creativity
            max_tokens=1000
        )
        answer = completion.choices[0].message.content

        # Update Redis with clean User/Assistant pairs
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

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