import os
import json
import asyncio
import traceback
from functools import partial

import redis
import chromadb
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel
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

        # --- STEP 1: RETRIEVE & CLEAN HISTORY ---
        try:
            raw_history = r.get(redis_key)
            history = json.loads(raw_history) if raw_history else []
        except (json.JSONDecodeError, redis.RedisError) as e:
            print(f"WARN: Redis/JSON error, starting fresh: {e}")
            history = []

        # Enforce strict user/assistant alternating roles
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"

        # History must end with assistant since next message is from user
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()

        # --- STEP 2: QUERY REWRITING ---
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

            # Strip bullet points, numbers, quotes from each line
            lines = [line.strip('- *123456789."\' ') for line in raw_content.split('\n')]

            # Filter out conversational filler
            filtered_lines = [
                l for l in lines
                if len(l) > 0
                and "Based on" not in l
                and "Therefore" not in l
                and "current query" not in l.lower()
            ]

            search_term = ", ".join(filtered_lines) if filtered_lines else raw_content

        except Exception as e:
            print(f"DEBUG: Rewriter failed, falling back to raw query: {e}")
            search_term = user_query

        print(f"DEBUG: Final Search Term for Chroma: {search_term}")

        # --- STEP 3: RAG RETRIEVAL ---
        # Run blocking CPU encode off the async event loop
        loop = asyncio.get_event_loop()
        q_emb_array = await loop.run_in_executor(
            None,
            partial(
                bi_encoder.encode,
                f"Represent this sentence for searching relevant passages: {search_term}",
                normalize_embeddings=True
            )
        )
        q_emb = q_emb_array.tolist()

        results = collection.query(query_embeddings=[q_emb], n_results=6)

        context_chunks = []
        for i in range(len(results['documents'][0])):
            distance = results['distances'][0][i]
            job_title = results['metadatas'][0][i].get('job_title')

            print(f"DEBUG: Chroma found '{job_title}' with distance {distance}")

            if distance > 1.0:
                print(f"DEBUG: Skipping result {i} — distance too high: {distance}")
                continue

            meta = results['metadatas'][0][i]
            salary_val = meta.get('salary', 'N/A')

            try:
                salary_str = f"**${float(salary_val):,.2f}**" if salary_val != 'N/A' else "Data missing"
            except (ValueError, TypeError):
                salary_str = f"**${salary_val}**"

            context_chunks.append(
                f"JOB: {meta.get('job_title')} (NOC: {meta.get('noc_code', 'N/A')})\n"
                f"SALARY: {salary_str}\n"
                f"URL: {meta.get('url', '#')}\n"
                f"CONTENT: {results['documents'][0][i]}"
            )

        # Truncate by chunk boundary, not raw character slice
        MAX_CONTEXT_CHARS = 3500
        truncated_chunks = []
        total = 0
        for chunk in context_chunks:
            if total + len(chunk) > MAX_CONTEXT_CHARS:
                break
            truncated_chunks.append(chunk)
            total += len(chunk)

        top_context = "\n---\n".join(truncated_chunks) if truncated_chunks else "No WorkBC data found."

        # --- STEP 4: FINAL MESSAGE ASSEMBLY ---
        system_rules = (
            "You are a WorkBC Career Advisor. BE CONCISE. Use bullet points. Rules:\n"
            "1. Use ONLY the provided Context. No external sources or internal knowledge.\n"
            "2. IDENTITY CHECK: If the context describes a job that is NOT what the user asked for "
            "(e.g., user asks for 'Teacher' but context is 'Principal'), do NOT use that data.\n"
            "3. If comparing careers, YOU MUST USE A MARKDOWN TABLE.\n"
            "4. Always include the NOC code and **bold** salaries.\n"
            "5. Format links as [View Career Profile](URL).\n"
            "6. If context is missing, say you don't have that information in WorkBC records."
        )

        # History window must start with a user message
        history_window = sanitized_history[-2:]
        while history_window and history_window[0]["role"] != "user":
            history_window.pop(0)

        current_user_content = f"Context:\n{top_context}\n\nQuestion: {user_query}"

        # System rules as a fake user/assistant exchange at the top keeps
        # Mistral's instruction-following reliable without polluting Redis history
        final_messages = [
            {"role": "user", "content": system_rules},
            {"role": "assistant", "content": "Understood. I will follow these guidelines strictly."},
            *history_window,
            {"role": "user", "content": current_user_content},
        ]

        # --- STEP 5: GENERATE ---
        try:
            completion = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=final_messages,
                temperature=0.0,
                max_tokens=800
            )
            answer = completion.choices[0].message.content
        except Exception as e:
            raise HTTPException(status_code=502, detail=f"LLM inference error: {str(e)}")

        # --- STEP 6: SAVE HISTORY ---
        sanitized_history.append({"role": "user", "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))

        return {
            "answer": answer,
            "session_id": session_id,
            "debug_search": search_term,
        }

    except HTTPException:
        # Re-raise HTTPExceptions without wrapping them
        raise
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Internal Server Error: {str(e)}")


# --- HEALTH CHECK ---
@app.get("/health")
@app.get("/api/health")
async def health_check():
    return {
        "status": "healthy",
        "mistral_endpoint": f"http://{MISTRAL_HOST}:{MISTRAL_PORT}",
    }


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
