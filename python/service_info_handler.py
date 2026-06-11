from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Optional

COLLECTION_NAME = "workbc_services"
BGE_QUERY_PREFIX = "Represent this sentence for searching relevant passages: "
LLM_MAX_TOKENS = 500

# ---------------------------------------------------------------------------
# Region detection (regions + major cities)
# ---------------------------------------------------------------------------

CITIES_BY_REGION = {
    "Cariboo":                  ["prince george", "quesnel", "williams lake", "100 mile house"],
    "Kootenay":                 ["cranbrook", "nelson", "castlegar", "trail", "fernie"],
    "Mainland/Southwest":       ["vancouver", "surrey", "burnaby", "coquitlam", "richmond",
                                 "abbotsford", "whistler", "squamish", "langley", "delta"],
    "North Coast and Nechako":  ["prince rupert", "terrace", "smithers", "burns lake", "kitimat"],
    "Northeast":                ["fort st. john", "fort st john", "dawson creek", "chetwynd"],
    "Thompson-Okanagan":        ["kelowna", "kamloops", "vernon", "penticton", "salmon arm"],
    "Vancouver Island/Coast":   ["victoria", "nanaimo", "courtenay", "campbell river", "duncan"],
}

REGION_ALIASES: dict[str, str] = {
    "cariboo": "Cariboo",
    "kootenay": "Kootenay", "kootenays": "Kootenay",
    "mainland": "Mainland/Southwest", "southwest": "Mainland/Southwest",
    "lower mainland": "Mainland/Southwest", "metro vancouver": "Mainland/Southwest",
    "north coast": "North Coast and Nechako", "nechako": "North Coast and Nechako",
    "northeast": "Northeast", "north east": "Northeast",
    "thompson": "Thompson-Okanagan", "okanagan": "Thompson-Okanagan",
    "vancouver island": "Vancouver Island/Coast", "the island": "Vancouver Island/Coast",
}
for _region, _cities in CITIES_BY_REGION.items():
    for _city in _cities:
        REGION_ALIASES[_city] = _region


def extract_region(query: str) -> Optional[str]:
    q = query.lower()
    for alias in sorted(REGION_ALIASES, key=len, reverse=True):
        if alias in q:
            return REGION_ALIASES[alias]
    return None


# ---------------------------------------------------------------------------
# Sub-intent detection
# ---------------------------------------------------------------------------

SUB_INTENT_PATTERNS = [
    ("eligibility", [r"\beligib", r"\bqualify\b", r"\bwho can\b", r"\brequirements?\b"]),
    ("contact",     [r"\bcontact\b", r"\bemail\b", r"\bphone\b", r"\bget in touch\b"]),
    ("region",      [r"\bby region\b", r"\bnear me\b", r"\bwhere (is|are|can)\b",
                     r"\blocations?\b", r"\bfind programs\b"]),
    ("services",    [r"\bservices\b", r"\bwhat .{0,30}(offer|include|provide)", r"\bsupports?\b"]),
    ("overview",    [r"\bwhat is\b", r"\btell me about\b", r"\boverview\b", r"\bdescribe\b"]),
]


def detect_sub_intent(query: str) -> str:
    for sub_intent, patterns in SUB_INTENT_PATTERNS:
        if any(re.search(p, query, re.I) for p in patterns):
            return sub_intent
    return "overview"


# ---------------------------------------------------------------------------
# Response model
# ---------------------------------------------------------------------------

@dataclass
class ServiceResponse:
    text: str
    suggestions: list[str] = field(default_factory=list)
    sources: list[dict] = field(default_factory=list)      # [{"title", "url"}]
    matched_intent: str = ""
    matched_region: Optional[str] = None
    mode: str = "verbatim"        # "llm_summary" | "verbatim_fallback" | "verbatim"


# ---------------------------------------------------------------------------
# Grounding validation — the safety net that makes LLM summaries shippable
# ---------------------------------------------------------------------------

_EMAIL_RE = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
_URL_RE = re.compile(r"https?://[^\s)\]>\"']+")
_MONEY_RE = re.compile(r"\$\s?([\d,]+(?:\.\d+)?)")


def _normalize_money(m: str) -> str:
    return m.replace(",", "").replace(" ", "")


def validate_grounding(answer: str, context: str) -> list[str]:
    """
    Verify every email, URL, and dollar amount in the answer exists in the
    source context. Returns a list of violations (empty = grounded).
    """
    violations = []

    context_emails = set(e.lower() for e in _EMAIL_RE.findall(context))
    for email in _EMAIL_RE.findall(answer):
        if email.lower() not in context_emails:
            violations.append(f"email not in source: {email}")

    context_urls = _URL_RE.findall(context)
    for url in _URL_RE.findall(answer):
        clean = url.rstrip(".,;")
        if not any(clean in cu or cu in clean for cu in context_urls):
            violations.append(f"url not in source: {clean}")

    context_amounts = set(_normalize_money(m) for m in _MONEY_RE.findall(context))
    for amount in _MONEY_RE.findall(answer):
        if _normalize_money(amount) not in context_amounts:
            violations.append(f"dollar amount not in source: ${amount}")

    return violations


# ---------------------------------------------------------------------------
# Handler
# ---------------------------------------------------------------------------

SUMMARY_SYSTEM_RULES = (
    "You are the WorkBC Career Advisor answering questions about WorkBC programs "
    "and services in British Columbia. BE CONCISE.\n\n"
    "RULE 1 — SOURCE DATA ONLY:\n"
    "- Answer using ONLY the WorkBC content provided — never your training knowledge\n"
    "- Quote eligibility criteria, dollar amounts, email addresses, and deadlines "
    "EXACTLY as written in the source — do not round, infer, or generalize them\n"
    "- If the provided content does not answer the question, say: "
    "'The WorkBC page does not specify that — contact details are below.' "
    "and include any contact info from the source\n\n"
    "RULE 2 — FORMAT:\n"
    "- Start directly with the answer — no 'Based on the data' preamble\n"
    "- 2-5 sentences for simple questions; short bullet list only when listing "
    "multiple services or steps\n"
    "- End with the source link(s) formatted as [View on WorkBC.ca](URL), "
    "using ONLY URLs that appear in the source content\n\n"
    "RULE 3 — NEVER INVENT:\n"
    "- No URLs, emails, phone numbers, or dollar amounts that are not in the source\n"
    "- No application steps or deadlines that are not in the source\n"
    "- No program names that are not in the source\n"
)


class ServiceInfoHandler:
    def __init__(self, chroma_client, embedder,
                 llm_client=None, model_name: str = "",
                 collection_name: str = COLLECTION_NAME):
        self.collection = chroma_client.get_collection(collection_name)
        self.embedder = embedder
        self.llm_client = llm_client
        self.model_name = model_name

    # -- retrieval -----------------------------------------------------------

    def _embed(self, text: str) -> list[list[float]]:
        return self.embedder.encode(
            [f"Represent this sentence for searching relevant passages: {text}"],
            normalize_embeddings=True,
        ).tolist()


    def _query(self, query: str, where: Optional[dict] = None,
               n_results: int = 3) -> list[dict]:
        kwargs = {
            "query_embeddings": self._embed(query),
            "n_results": n_results,
            "include": ["documents", "metadatas"],
        }
        if where:
            kwargs["where"] = where
        res = self.collection.query(**kwargs)
        return [{"text": doc, "metadata": meta}
                for doc, meta in zip(res["documents"][0], res["metadatas"][0])]

    # -- main entry point ----------------------------------------------------

    def handle(self, user_query: str) -> ServiceResponse:
        sub_intent = detect_sub_intent(user_query)
        region = extract_region(user_query)

        # Retrieve the right chunks for the question
        if region:
            hits = self._query(user_query, where={"region": region}, n_results=3)
            if not hits:
                hits = self._query(user_query,
                                   where={"chunk_type": "Service_RegionMap"},
                                   n_results=1)
            sub_intent = "region"
        elif sub_intent == "eligibility":
            hits = self._section_hits(user_query, "Eligible participants")
        elif sub_intent == "services":
            hits = self._section_hits(user_query, "Services")
        elif sub_intent == "contact":
            raw = self._query(user_query + " contact email", n_results=4)
            hits = [h for h in raw if "@" in h["text"]][:2] or raw[:1]
        elif sub_intent == "region":
            hits = self._query(user_query,
                               where={"chunk_type": "Service_RegionMap"},
                               n_results=1)
            if not hits:
                hits = self._query(user_query, n_results=2)
        else:  # overview
            hits = self._query(user_query, where={"chunk_type": "Service_Hero"},
                               n_results=2)
            if not hits:
                hits = self._query(user_query, n_results=2)

        if not hits:
            return ServiceResponse(
                text=("I couldn't find information about that in WorkBC's "
                      "service pages. You can browse all programs at "
                      "https://www.workbc.ca."),
                suggestions=["What programs are available?"],
                matched_intent=sub_intent, mode="verbatim",
            )

        suggestions = self._suggestions_for(sub_intent)
        sources = self._sources_from(hits)

        # LLM summary path (with validation + fallback)
        if self.llm_client is not None:
            summary = self._summarize(user_query, hits)
            if summary is not None:
                return ServiceResponse(
                    text=summary, suggestions=suggestions, sources=sources,
                    matched_intent=sub_intent, matched_region=region,
                    mode="llm_summary",
                )
            # validation failed or LLM error → verbatim fallback
            return ServiceResponse(
                text=self._render_verbatim(hits), suggestions=suggestions,
                sources=sources, matched_intent=sub_intent,
                matched_region=region, mode="verbatim_fallback",
            )

        # No LLM configured → plain verbatim
        return ServiceResponse(
            text=self._render_verbatim(hits), suggestions=suggestions,
            sources=sources, matched_intent=sub_intent,
            matched_region=region, mode="verbatim",
        )

    # -- LLM summarization with grounding validation --------------------------

    def _summarize(self, user_query: str, hits: list[dict]) -> Optional[str]:
        """Return a validated LLM summary, or None to trigger verbatim fallback."""
        context_blocks = []
        for h in hits:
            meta = h["metadata"]
            context_blocks.append(
                f"PAGE: {meta.get('title')}\n"
                f"URL: {meta.get('url')}\n"
                f"CONTENT: {h['text']}"
            )
        context = "\n---\n".join(context_blocks)

        user_content = (
            f"WorkBC source content:\n{context}\n\n"
            f"Question: {user_query}\n\n"
            "Answer the question using ONLY the source content above. "
            "Follow all rules."
        )

        try:
            completion = self.llm_client.chat.completions.create(
                model=self.model_name,
                messages=[
                    {"role": "user", "content": SUMMARY_SYSTEM_RULES},
                    {"role": "assistant",
                     "content": "Understood. I will follow these guidelines strictly."},
                    {"role": "user", "content": user_content},
                ],
                temperature=0.0,
                max_tokens=LLM_MAX_TOKENS,
                frequency_penalty=0.5,
                stop=["\nQuestion:", "\nWorkBC source content:"],
            )
            answer = completion.choices[0].message.content.strip()

            # Belt-and-braces: strip any leaked prompt-format continuation
            for marker in ("\nQuestion:", "Question: What", "\nAnswer the question"):
                idx = answer.find(marker)
                if idx > 0:
                    answer = answer[:idx].strip()
                    print(f"WARN: service_info — stripped leaked prompt "
                          f"continuation at '{marker}'")
        except Exception as e:
            print(f"WARN: service_info LLM call failed — verbatim fallback: {e}")
            return None

        violations = validate_grounding(answer, context)
        if violations:
            print(f"WARN: service_info summary failed grounding validation — "
                  f"verbatim fallback. Violations: {violations}")
            return None

        return answer

    # -- helpers --------------------------------------------------------------

    def _section_hits(self, query: str, section_heading: str) -> list[dict]:
        """Find which program the user means, then pull that exact section."""
        program_hits = self._query(query, where={"chunk_type": "Service_Hero"},
                                   n_results=1)
        if program_hits:
            nid = program_hits[0]["metadata"]["nid"]
            hits = self._query(query, where={"$and": [
                {"nid": nid}, {"section_heading": section_heading}]}, n_results=2)
            if hits:
                # Include the hero for program context in the summary
                return hits + program_hits
            return self._query(query, where={"nid": nid}, n_results=2)
        return self._query(query, where={"section_heading": section_heading},
                           n_results=2)

    @staticmethod
    def _sources_from(hits: list[dict]) -> list[dict]:
        sources, seen = [], set()
        for h in hits:
            url = h["metadata"].get("url", "")
            if url and url not in seen:
                seen.add(url)
                sources.append({"title": h["metadata"].get("title", ""), "url": url})
        return sources

    @staticmethod
    def _render_verbatim(hits: list[dict]) -> str:
        blocks = [h["text"] for h in hits]
        text = "\n\n---\n\n".join(blocks)
        sources = ServiceInfoHandler._sources_from(hits)
        if sources:
            text += "\n\nSource: " + " | ".join(
                f"[{s['title']}]({s['url']})" for s in sources)
        return text

    @staticmethod
    def _suggestions_for(sub_intent: str) -> list[str]:
        return {
            "overview":    ["Am I eligible?", "What does it offer?", "Where is it available?"],
            "eligibility": ["What does it offer?", "Where is it available?", "Contact info"],
            "services":    ["Am I eligible?", "Where is it available?", "Contact info"],
            "region":      ["Other regions", "Am I eligible?", "Contact info"],
            "contact":     ["What is this program?", "Am I eligible?"],
        }.get(sub_intent, ["What programs are available?"])
