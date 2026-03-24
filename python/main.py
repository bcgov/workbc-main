import os
import json
import redis
import traceback
from fastapi import FastAPI, HTTPException
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

# Updated CORS for your React frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], 
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- 2. INITIALIZATION ---
DEVICE = "cpu" 
bi_encoder = SentenceTransformer("BAAI/bge-base-en-v1.5", device=DEVICE)

vllm_client = OpenAI(
    base_url=f"http://{MISTRAL_HOST}:{MISTRAL_PORT}/v1", 
    api_key="none",
    timeout=120.0
)

MODEL_NAME = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"
chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection = chroma_client.get_collection("career_content")
r = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True, socket_timeout=5)

class QueryRequest(BaseModel):
    prompt: str
    session_id: str

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key = f"chat_history:{session_id}"

        # --- STEP 1: RETRIEVE & SANITIZE HISTORY ---
        try:
            raw_history = r.get(redis_key)
            chat_history = json.loads(raw_history) if raw_history else []
        except Exception:
            chat_history = []

        sanitized_history = []
        next_role_expected = "user"
        for msg in chat_history:
            if msg["role"] == next_role_expected:
                sanitized_history.append(msg)
                next_role_expected = "assistant" if next_role_expected == "user" else "user"

        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # --- STEP 2: QUERY DECOMPOSITION & REWRITING ---
        # Forces the AI to identify the specific careers to search for
        rewrite_prompt = (
            f"System: Extract the primary job titles mentioned in the question or history. "
            f"If multiple, separate with '|'. Output ONLY the job titles.\n\n"
            f"History: {sanitized_history[-2:]}\n"
            f"Question: {user_query}"
        )
        try:
            rewrite_res = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": rewrite_prompt}],
                temperature=0
            )
            search_terms = rewrite_res.choices[0].message.content.strip().split('|')
        except:
            search_terms = [user_query]

        # --- STEP 3: DIRECT RAG RETRIEVAL ---
        context_chunks = []
        for term in search_terms:
            instruction = "Represent this sentence for searching relevant passages: "
            q_emb = bi_encoder.encode(instruction + term.strip()).tolist()
            results = collection.query(query_embeddings=[q_emb], n_results=5)
            
            for i in range(len(results['documents'][0])):
                if results['distances'][0][i] < 0.50:
                    meta = results['metadatas'][0][i]
                    # We force the critical data into the text block
                    context_chunks.append(
                        f"JOB: {meta.get('job_title')}\n"
                        f"NOC: {meta.get('noc_code', 'N/A')}\n"
                        f"SALARY: **${meta.get('salary', 'N/A')}**\n"
                        f"URL: {meta.get('url', '#')}\n"
                        f"CONTENT: {results['documents'][0][i]}"
                    )

        top_context = "\n---\n".join(list(set(context_chunks))) if context_chunks else "No WorkBC data found."

        # --- STEP 4: STRICT GENERATION ---
        system_rules = (
            "You are a WorkBC Career Advisor. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "3. Always include the NOC code and **bold** salaries.\n"
            "4. Format links as [View Career Profile](URL).\n"
            "5. If context is missing, say you don't have that information in WorkBC records."
        )

        messages = [{"role": "system", "content": system_rules}]
        messages.extend(sanitized_history[-6:])
        messages.append({"role": "user", "content": f"Context:\n{top_context}\n\nQuestion: {user_query}"})

        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=messages,
            temperature=0.0, # Lock creativity to 0
            max_tokens=1000
        )
        answer = completion.choices[0].message.content

        # --- STEP 5: SAVE TO REDIS ---
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {"answer": answer, "session_id": session_id}

    except Exception as e:
        traceback.print_exc()
        return {"detail": f"Internal Error: {str(e)}"}, 500


# --- NEW: HEALTH CHECK ---
@app.get("/health") 
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)