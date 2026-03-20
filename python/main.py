import os
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import chromadb
from sentence_transformers import SentenceTransformer
from openai import OpenAI
import uvicorn

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
    api_key="none"
)

MODEL_NAME = "TheBloke/Mistral-7B-Instruct-v0.2-AWQ"

chroma_client = chromadb.HttpClient(host=CHROMA_HOST, port=int(CHROMA_PORT))
collection = chroma_client.get_collection("career_content")

class QueryRequest(BaseModel):
    prompt: str

@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    user_query = request.prompt
    
    # --- STEP 1: DECOMPOSITION ---
    decomposition_prompt = (
        f"System: Extract career names. Output ONLY the primary career names mentioned, "
        f"separated by a pipe (|). Example: 'Nurse vs Teacher' -> 'Nurse|Teacher'\n\n"
        f"Analyze: '{user_query}'"
    )
    
    try:
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=[{"role": "user", "content": decomposition_prompt}],
            temperature=0 # Added for consistency
        )
        analysis = completion.choices[0].message.content
        sub_queries = [q.strip() for q in analysis.split('|') if q.strip()]
    except Exception:
        sub_queries = [user_query]

    # --- STEP 2: DIRECT RETRIEVAL ---
    context_chunks = []
    for q in sub_queries:
        q_emb = bi_encoder.encode(f"Represent this sentence for searching relevant passages: {q}").tolist()
        results = collection.query(query_embeddings=[q_emb], n_results=5)
        
        for doc, meta in zip(results['documents'][0], results['metadatas'][0]):
            salary = meta.get('salary', 0)
            salary_str = f"${float(salary):,.2f}" if salary else "Data missing"
            
            chunk = (
                f"JOB: {meta.get('job_title')}\n"
                f"SALARY: {salary_str}\n"
                f"URL: {meta.get('url')}\n"
                f"CONTENT: {doc}\n"
            )
            context_chunks.append(chunk)

    top_context = "\n---\n".join(list(set(context_chunks))[:10]) 

    # --- STEP 3: GENERATION ---
    try:
        response = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=[{
                "role": "user", 
                "content": f"System: Use the context to answer. Use Markdown tables for comparisons. Bold salaries.\n\nContext:\n{top_context}\n\nQuestion: {user_query}"
            }]
        )
        answer = response.choices[0].message.content
    except Exception as e:
        answer = f"Error communicating with Mistral: {str(e)}"

    return {"answer": answer, "debug": {"searched_for": sub_queries}}

# --- NEW: HEALTH CHECK ---
@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}"}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
