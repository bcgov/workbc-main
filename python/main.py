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
    user_query = request.prompt
    session_id = request.session_id
    redis_key = f"chat_history:{session_id}"

    # A. Retrieve Chat History from Redis
    try:
        raw_history = r.get(redis_key)
        chat_history = json.loads(raw_history) if raw_history else []
    except Exception as e:
        print(f"Redis connection error: {e}")
        chat_history = []

    # B. Query Contextualization (Memory Logic)
    # If the question is short, we append previous context to help the vector search
    search_term = user_query
    if chat_history and len(user_query.split()) < 5:
        last_user_msg = next((m['content'] for m in reversed(chat_history) if m['role'] == 'user'), "")
        search_term = f"{last_user_msg} {user_query}"

    # C. Retrieval with Distance Guard (Accuracy Fix)
    # BGE models require this specific instruction prefix for retrieval
    instruction = "Represent this sentence for searching relevant passages: "
    q_emb = bi_encoder.encode(instruction + search_term).tolist()
    
    results = collection.query(query_embeddings=[q_emb], n_results=5)
    
    context_chunks = []
    for i in range(len(results['documents'][0])):
        distance = results['distances'][0][i]
        
        # DISTANCE GUARD: 
        # Only accept documents that are a strong mathematical match (< 0.48).
        # This prevents the bot from talking about "Marketing" when asked about "Nurses".
        if distance < 0.48:
            doc = results['documents'][0][i]
            meta = results['metadatas'][0][i]
            salary = meta.get('salary', 'Data not available')
            url = meta.get('url', '#')
            context_chunks.append(
                f"JOB: {meta.get('job_title')}\n"
                f"ANNUAL SALARY: {salary}\n"
                f"URL: {url}\n"
                f"CONTENT: {doc}"
            )

    # D. Prepare Response Context
    if not context_chunks:
        top_context = "No specific WorkBC data was found for this career or query in the database."
    else:
        top_context = "\n---\n".join(context_chunks)

    # E. Build LLM Prompt
    system_prompt = (
        "You are the WorkBC Career Advisor. Your goal is to provide accurate BC career data.\n"
        "RULES:\n"
        "1. Answer strictly using the provided Context.\n"
        "2. If the Context is unrelated to the user's career question, state that you don't have that data.\n"
        "3. Use Markdown tables for job comparisons.\n"
        "4. Always **bold** annual salaries.\n"
        "5. Provide WorkBC URLs as clickable Markdown links: [View Career Profile](URL)."
    )

    messages = [{"role": "system", "content": system_prompt}]
    
    # Inject up to 6 previous messages for conversation continuity
    messages.extend(chat_history[-6:])
    
    # Add the final user query with the RAG context
    messages.append({
        "role": "user", 
        "content": f"Context:\n{top_context}\n\nQuestion: {user_query}"
    })

    # F. Generate Answer
    try:
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=messages,
            temperature=0.2,
            max_tokens=1000
        )
        answer = completion.choices[0].message.content

        # G. Save new exchange back to AWS Redis
        chat_history.append({"role": "user", "content": user_query})
        chat_history.append({"role": "assistant", "content": answer})
        
        # Save to Redis for 1 hour (3600s), keeping only the last 10 messages
        r.setex(redis_key, 3600, json.dumps(chat_history[-10:]))

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Mistral Error: {str(e)}")

    return {
        "answer": answer,
        "session_id": session_id
    }

    
    


# --- NEW: HEALTH CHECK ---
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
