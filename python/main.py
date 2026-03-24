import os
import json
import redis
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import chromadb
from sentence_transformers import SentenceTransformer
from openai import OpenAI
import uvicorn
import traceback

REDIS_HOST = os.getenv("REDIS_HOST2", "localhost")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))


app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- 1. CONFIGURATION (Using your K8s Env Vars) ---
# Mistral is on port 80 based on your logs
MISTRAL_HOST = os.getenv("MISTRAL_SERVICE_SERVICE_HOST", "mistral-service")
MISTRAL_PORT = os.getenv("MISTRAL_SERVICE_SERVICE_PORT", "80")

# Chroma is on port 8000
CHROMA_HOST = os.getenv("CHROMA_SERVICE_HOST", "chroma-service")
CHROMA_PORT = os.getenv("CHROMA_SERVICE_PORT", "8000")

# --- 2. INITIALIZATION ---
DEVICE = "cpu" 
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device=DEVICE)

# Updated base_url to use Port 80
vllm_client = OpenAI(
    base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1", 
    api_key="none",
    timeout=120.0
)

MODEL_NAME = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"

chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection = chroma_client.get_collection("career_content")

# Initialize AWS Redis Client
r = redis.Redis(
    host=REDIS_HOST, 
    port=REDIS_PORT, 
    decode_responses=True,
    socket_timeout=5
)

class QueryRequest(BaseModel):
    prompt: str
    session_id: str

@app.post("/api/ask")
@app.post("/api/ask/")
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # 1. Retrieve & Sanitize History (The "400 Fix")
        try:
            raw_history = r.get(redis_key)
            chat_history = json.loads(raw_history) if raw_history else []
        except Exception as e:
            print(f"Redis error: {e}")
            chat_history = []

        sanitized_history = []
        next_role = "user"
        for msg in chat_history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
        
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # 2. RAG Retrieval with Strict Guard
        search_term = user_query
        if sanitized_history and len(user_query.split()) < 5:
            last_msg = next((m['content'] for m in reversed(sanitized_history) if m['role'] == 'user'), "")
            search_term = f"{last_msg} {user_query}"

        instruction = "Represent this sentence for searching relevant passages: "
        q_emb = bi_encoder.encode(instruction + search_term).tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=5)
        
        context_chunks = []
        for i in range(len(results['documents'][0])):
            # STICK TO THE 0.48 GUARD from your working version
            if results['distances'][0][i] < 0.48: 
                meta = results['metadatas'][0][i]
                context_chunks.append(
                    f"JOB: {meta.get('job_title')}\n"
                    f"SALARY: **{meta.get('salary', 'N/A')}**\n"
                    f"URL: [{meta.get('job_title')}]({meta.get('url', '#')})\n"
                    f"CONTENT: {results['documents'][0][i]}"
                )

        # 3. Force Grounding in the Prompt
        if not context_chunks:
            top_context = "CRITICAL: No WorkBC career data found for this query."
        else:
            top_context = "\n---\n".join(context_chunks)

        system_instruction = (
            "You are the WorkBC Career Advisor. RULES:\n"
            "1. ONLY use the provided Context to answer.\n"
            "2. If the Context says 'No WorkBC career data found' or is unrelated to careers (like weather), "
            "respond: 'I am sorry, but I only have access to WorkBC career data and cannot answer that.'\n"
            "3. Do not use outside knowledge.\n"
            "4. Use Markdown tables for job comparisons.\n"
            "5. Provide WorkBC URLs as clickable Markdown links: [View Career Profile](URL)."
        )

        # 4. Construct Messages
        messages = [{"role": "system", "content": system_instruction}]
        messages.extend(sanitized_history[-6:])
        messages.append({"role": "user", "content": f"Context:\n{top_context}\n\nQuestion: {user_query}"})

        # 5. Generate & Save
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=messages,
            temperature=0.1, # Lower temperature = less hallucination
            max_tokens=800
        )
        answer = completion.choices[0].message.content

        # Update History
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        traceback.print_exc()
        return {"detail": "Internal Server Error"}, 500


# --- NEW: HEALTH CHECK ---
@app.get("/health") 
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
