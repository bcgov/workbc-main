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
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # A. Retrieve History
        try:
            raw_history = r.get(redis_key)
            chat_history = json.loads(raw_history) if raw_history else []
        except Exception as e:
            print(f"Redis lookup error: {e}")
            chat_history = []

        # B. RAG: Vector Search
        search_term = user_query
        if chat_history and len(user_query.split()) < 5:
            last_user_msg = next((m['content'] for m in reversed(chat_history) if m['role'] == 'user'), "")
            search_term = f"{last_user_msg} {user_query}"

        instruction = "Represent this sentence for searching relevant passages: "
        q_emb = bi_encoder.encode(instruction + search_term).tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=5)
        
        context_chunks = []
        for i in range(len(results['documents'][0])):
            if results['distances'][0][i] < 0.48:
                meta = results['metadatas'][0][i]
                context_chunks.append(
                    f"JOB: {meta.get('job_title')}\n"
                    f"SALARY: {meta.get('salary', 'N/A')}\n"
                    f"URL: {meta.get('url', '#')}\n"
                    f"CONTENT: {results['documents'][0][i]}"
                )

        top_context = "\n---\n".join(context_chunks) if context_chunks else "No specific WorkBC data found."

        # C. PATTERN SANITIZER (The Mistral 400 Fix)
        # We build a fresh clean history list
        sanitized_history = []
        next_role_expected = "user"
        
        for msg in chat_history:
            if msg["role"] == next_role_expected:
                sanitized_history.append(msg)
                next_role_expected = "assistant" if next_role_expected == "user" else "user"

        # If the last message in history is 'user', we pop it because 
        # our NEW message is also 'user'. Mistral requires User -> Assistant -> User.
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # D. Build LLM Payload
        # We put instructions in a User message if History is empty, 
        # some vLLM/Mistral versions prefer this over 'system'.
        messages = []
        if not sanitized_history:
            final_prompt = f"Instruction: You are a WorkBC Career Advisor. Context: {top_context}\n\nQuestion: {user_query}"
        else:
            final_prompt = f"Context: {top_context}\n\nQuestion: {user_query}"

        messages.extend(sanitized_history[-6:])
        messages.append({"role": "user", "content": final_prompt})

        # E. Generate Answer
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=messages,
            temperature=0.2,
            max_tokens=800
        )
        answer = completion.choices[0].message.content

        # F. SELF-HEALING REDIS SAVE
        # We save the SANITIZED history + the new exchange.
        # This overwrites any "broken" history in Redis so the error doesn't repeat.
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        print("!!! SERVER ERROR !!!")
        traceback.print_exc()
        return {"detail": f"Internal Error: {str(e)}"}, 500
    


# --- NEW: HEALTH CHECK ---
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
