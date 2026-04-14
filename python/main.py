ps(os_query, indent=2)}")
    response = os_client.search(index="jobs_en", body=os_query)
    print(f"DEBUG: OpenSearch returned {response['hits']['total']['value']} total matches")
 
    jobs = []
    for hit in response["hits"]["hits"]:
        src = hit["_source"]
 
        # Safe extraction for all array fields — handles None and empty list []
        jobs.append({
            "job_id":          src.get("JobId"),
            "title":           src.get("Title"),
            "employer":        src.get("EmployerName"),
            "city":            (src.get("City")                                      or [None])[0],
            "salary":          src.get("SalarySummary", "Not specified"),
            "hours":           (src.get("HoursOfWork",        {}).get("Description") or [None])[0],
            "employment_type": (src.get("PeriodOfEmployment", {}).get("Description") or [None])[0],
            "noc_code":        src.get("Noc2021"),
            "industry":        src.get("Industry"),
            "url":             build_job_url(src),
            "description":     src.get("JobDescription", "")[:200],
        })
 
    return jobs
 
 
def format_job_results(jobs: list, params: dict) -> str:
    """Format job results as markdown text for the chat UI."""
    if not jobs:
        return (
            "I searched WorkBC's job bank but couldn't find any current postings "
            "matching your request. Try broader keywords or a different location."
        )
 
    location_str = f" in **{params['city']}**" if params.get("city") else ""
    keyword_str  = f"**{params['keywords']}**" if params.get("keywords") else "your search"
    lines = [f"Here are the top {len(jobs)} job postings for {keyword_str}{location_str}:\n"]
 
    for i, job in enumerate(jobs, 1):
        employment = job["employment_type"] or "Not specified"
        hours      = job["hours"]           or "Not specified"
        lines.append(
            f"**{i}. {job['title']}**\n"
            f"🏢 {job['employer']} | 📍 {job['city']}\n"
            f"💰 {job['salary']} | ⏱ {hours} | {employment}\n"
            f"🏭 {job['industry']} | NOC: {job['noc_code']}\n"
            f"📋 {job['description']}...\n"
            f"[View Job Posting]({job['url']})\n"
            f"🔎 `Job ID: {job['job_id']}`\n"   # debug — remove before production
        )
 
    lines.append(
        "_Results from WorkBC Job Bank. "
        "Postings may change — click the link to confirm details._"
    )
    return "\n".join(lines)
 
 
async def get_career_answer(
    user_query: str,
    sanitized_history: list,
    system_rules: str,
) -> tuple[str, str]:
    """
    Run the full RAG + Mistral career info flow.
    Returns (answer, search_term).
    """
    # Query rewriting
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
            temperature=0,
        )
        raw_content    = rewrite_res.choices[0].message.content.strip()
        lines          = [line.strip('- *123456789."\' ') for line in raw_content.split('\n')]
        filtered_lines = [
            l for l in lines
            if len(l) > 0
            and "Based on"      not in l
            and "Therefore"     not in l
            and "current query" not in l.lower()
        ]
        # Fallback to user_query not raw_content
        search_term = ", ".join(filtered_lines) if filtered_lines else user_query
    except Exception as e:
        print(f"DEBUG: Rewriter failed, falling back to raw query: {e}")
        search_term = user_query
 
    print(f"DEBUG: Final Search Term for Chroma: {search_term}")
 
    # RAG retrieval — run blocking encode off the event loop
    loop        = asyncio.get_event_loop()
    q_emb_array = await loop.run_in_executor(
        None,
        partial(
            bi_encoder.encode,
            f"Represent this sentence for searching relevant passages: {search_term}",
            normalize_embeddings=True,
        )
    )
    q_emb = q_emb_array.tolist()
 
    results = collection.query(query_embeddings=[q_emb], n_results=6)
 
    context_chunks = []
    for i in range(len(results['documents'][0])):
        distance  = results['distances'][0][i]
        job_title = results['metadatas'][0][i].get('job_title')
        print(f"DEBUG: Chroma found '{job_title}' with distance {distance}")
 
        # Tighter threshold — BGE cosine distances above 0.5 are likely irrelevant
        if distance > 0.5:
            print(f"DEBUG: Skipping '{job_title}' — distance too high: {distance}")
            continue
 
        meta       = results['metadatas'][0][i]
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
 
    # Truncate by chunk boundary not raw character slice
    MAX_CONTEXT_CHARS = 3500
    truncated_chunks  = []
    total             = 0
    for chunk in context_chunks:
        if total + len(chunk) > MAX_CONTEXT_CHARS:
            break
        truncated_chunks.append(chunk)
        total += len(chunk)
 
    top_context = "\n---\n".join(truncated_chunks) if truncated_chunks else "No WorkBC data found."
 
    # History window — must start with user
    history_window = sanitized_history[-2:]
    while history_window and history_window[0]["role"] != "user":
        history_window.pop(0)
 
    current_user_content = f"Context:\n{top_context}\n\nQuestion: {user_query}"
 
    final_messages = [
        {"role": "user",      "content": system_rules},
        {"role": "assistant", "content": "Understood. I will follow these guidelines strictly."},
        *history_window,
        {"role": "user",      "content": current_user_content},
    ]
 
    try:
        completion = vllm_client.chat.completions.create(
            model=MODEL_NAME,
            messages=final_messages,
            temperature=0.0,
            max_tokens=800,
        )
        answer = completion.choices[0].message.content
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"LLM inference error: {str(e)}")
 
    return answer, search_term
 
 
async def get_job_answer(params: dict) -> str:
    """Run job search in executor and return formatted markdown string."""
    loop = asyncio.get_event_loop()
    jobs = await loop.run_in_executor(None, partial(search_jobs, params, 5))
    return format_job_results(jobs, params)
 
 
# ---------------------------------------------------------------------------
# 5. MAIN ENDPOINT
# ---------------------------------------------------------------------------
@app.post("/api/ask")
async def ask_career_bot(request: QueryRequest):
    try:
        user_query = request.prompt
        session_id = request.session_id
        redis_key  = f"chat_history:{session_id}"
 
        # --- History ---
        try:
            raw_history = r.get(redis_key)
            history     = json.loads(raw_history) if raw_history else []
        except (json.JSONDecodeError, redis.RedisError) as e:
            print(f"WARN: Redis/JSON error, starting fresh: {e}")
            history = []
 
        sanitized_history = []
        next_role = "user"
        for msg in history:
            if msg["role"] == next_role:
                sanitized_history.append(msg)
                next_role = "assistant" if next_role == "user" else "user"
 
        if sanitized_history and sanitized_history[-1]["role"] == "user":
            sanitized_history.pop()
 
        # --- Intent detection ---
        intent_prompt = (
            "Classify this query and return JSON only, no explanation, no markdown fences.\n\n"
            "RULES:\n"
            "- 'job_search' = user wants to find/see/browse actual job postings or openings\n"
            "- 'career_info' = user wants to learn about a career (duties, salary, education)\n"
            "- 'both' = user wants career info AND job postings\n\n"
            "EXAMPLES:\n"
            "Query: 'find nursing jobs in Vancouver' -> job_search\n"
            "Query: 'show me accounting jobs' -> job_search\n"
            "Query: 'any openings for teachers?' -> job_search\n"
            "Query: 'jobs paying over 80000' -> job_search\n"
            "Query: 'what does a nurse do?' -> career_info\n"
            "Query: 'what is the salary for a firefighter?' -> career_info\n"
            "Query: 'what education do I need to be a pharmacist?' -> career_info\n"
            "Query: 'tell me about plumbers and show me jobs' -> both\n\n"
            "OUTPUT FORMAT:\n"
            "{\n"
            '  "intent": "job_search" or "career_info" or "both",\n'
            '  "job_search_params": {\n'
            '    "keywords": "extracted job title or null",\n'
            '    "city": "extracted city or null",\n'
            '    "employment_type": "Full-time or Part-time or null",\n'
            '    "salary_min": null\n'
            "  }\n"
            "}\n\n"
            f"Query: {user_query}"
        )
 
        try:
            intent_res  = vllm_client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": intent_prompt}],
                temperature=0,
            )
            raw_intent  = intent_res.choices[0].message.content.strip()
            intent_data = parse_intent(raw_intent)
            intent      = intent_data["intent"]
            params      = intent_data.get("job_search_params", {})
 
        except json.JSONDecodeError as e:
            print(f"DEBUG: Intent JSON parse failed ({e}) — raw was: {repr(raw_intent)}")
            intent = "career_info"
            params = {}
        except Exception as e:
            print(f"DEBUG: Intent detection failed ({type(e).__name__}): {e}")
            intent = "career_info"
            params = {}
 
        print(f"DEBUG: Intent={intent} | Params={params}")
 
        # --- System rules (career info path) ---
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
 
        # --- Route by intent ---
        answer      = ""
        search_term = user_query
 
        if intent == "career_info":
            answer, search_term = await get_career_answer(
                user_query, sanitized_history, system_rules
            )
 
        elif intent == "job_search":
            answer = await get_job_answer(params)
 
        elif intent == "both":
            # Run career info and job search in parallel
            career_task = asyncio.create_task(
                get_career_answer(user_query, sanitized_history, system_rules)
            )
            jobs_task = asyncio.create_task(get_job_answer(params))
 
            (career_answer, search_term), job_answer = await asyncio.gather(
                career_task, jobs_task
            )
 
            answer = (
                f"{career_answer}\n\n"
                "---\n\n"
                "## Current Job Postings\n\n"
                f"{job_answer}"
            )
 
        # --- Save history ---
        sanitized_history.append({"role": "user",     "content": user_query})
        sanitized_history.append({"role": "assistant", "content": answer})
        r.setex(redis_key, 3600, json.dumps(sanitized_history[-10:]))
 
        return {
            "answer":        answer,
            "session_id":    session_id,
            "debug_search":  search_term,
            "debug_intent":  intent,
            "debug_params":  params,
        }
 
    except HTTPException:
        raise
    except Exception as e:
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Internal Server Error: {str(e)}")
 
 
# ---------------------------------------------------------------------------
# 6. HEALTH CHECK
# ---------------------------------------------------------------------------
@app.get("/health")
@app.get("/api/health")
async def health_check():
    return {
        "status":              "healthy",
        "mistral_endpoint":    f"http://{MISTRAL_HOST}:{MISTRAL_PORT}",
        "opensearch_server":   OPENSEARCH_SERVER,
        "opensearch_user_set": bool(OPENSEARCH_USER),
        "opensearch_pass_set": bool(OPENSEARCH_PASS),
        "workbc_base_url":     WORKBC_BASE_URL,   # confirm correct env var loaded
    }
 
 
if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)