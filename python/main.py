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
            print(f"Redis error: {e}")
            chat_history = []

        # B. Retrieval (RAG)
        search_term = user_query
        if chat_history and len(user_query.split()) < 5:
            last_user_msg = next((m['content'] for m in reversed(chat_history) if m['role'] == 'user'), "")
            search_term = f"{last_user_msg} {user_query}"

        instruction = "Represent this sentence for searching relevant passages: "
        q_emb = bi_encoder.encode(instruction + search_term).tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=5)
        
        context_chunks = []
        for i in range(len(results['documents'][0])):
            if results['distances'][0][i] < 0.48: # Your Distance Guard
                meta = results['metadatas'][0][i]
                context_chunks.append(
                    f"JOB: {meta.get('job_title')}\n"
                    f"ANNUAL SALARY: {meta.get('salary', 'N/A')}\n"
                    f"URL: {meta.get('url', '#')}\n"
                    f"CONTENT: {results['documents'][0][i]}"
                )

        top_context = "\n---\n".join(context_chunks) if context_chunks else "No specific data found."

        # C. FIX: ALTERNATING ROLES SANITIZER (Stops 400 Mistral Errors)
        # Mistral MUST follow: System -> User -> Assistant -> User
        sanitized_history = []
        
        # Ensure we start with a 'user' message after the system prompt
        current_role_needed = "user"
        
        for msg in chat_history:
            if msg["role"] == current_role_needed:
                sanitized_history.append(msg)
                # Flip the requirement for the next message
                current_role_needed = "assistant" if current_role_needed == "user" else "user"

        # If the last message in history is 'user', Mistral will crash because 
        # our NEW prompt is also 'user'. We must end history with an 'assistant'.
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # D. Build Final Message Payload
        messages = [
            {"role": "system", "content": "You are a WorkBC Career Advisor. Use the Context provided."}
        ]
        messages.extend(sanitized_history[-6:]) # Last 3 exchanges
        messages.append({
            "role": "user", 
            "content": f"Context:\n{top_context}\n\nQuestion: {user_query}"
        })

        # E. Generate Answer
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=messages,
            temperature=0.2,
            max_tokens=1000
        )
        answer = completion.choices[0].message.content

        # F. Save to Redis (Save the original query, not the RAG-bloated one)
        chat_history.append({"role": "user", "content": user_query})
        chat_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(chat_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        print("!!! SERVER ERROR !!!")
        traceback.print_exc() # This will show exactly why in kubectl logs
        return {"detail": f"Internal Error: {str(e)}"}, 500
    


# --- NEW: HEALTH CHECK ---
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
