"""Analytics for the WorkBC Career Advisor: PostgreSQL-backed interaction and
feedback logging plus admin metrics.

Design constraints:
  * Logging must never add latency to /api/ask — events go into a bounded
    in-memory queue and a background task batch-inserts them every few seconds.
  * A database outage must never break the chatbot — the queue drops events
    when full and the flusher swallows (and logs) insert errors.
  * Query text can contain personal information typed by the public, so rows
    are purged after ANALYTICS_RETENTION_DAYS (default 90) by a daily task.
"""

from __future__ import annotations

import html
import asyncio
import logging

import psycopg2
from psycopg2.extras import RealDictCursor, execute_values

log = logging.getLogger("career_advisor.analytics")

FLUSH_INTERVAL_SECONDS = 5
QUEUE_MAX = 2000
FIELD_CAPS = {"user_query": 500, "bot_response": 500, "comment": 500,
              "keywords": 80, "city": 60, "employer": 60}

SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS chat_interactions (
    id            BIGSERIAL PRIMARY KEY,
    ts            TIMESTAMPTZ NOT NULL DEFAULT now(),
    session_id    TEXT NOT NULL,
    intent        TEXT,
    user_query    TEXT,
    keywords      TEXT,
    city          TEXT,
    employer      TEXT,
    result_count  INTEGER,
    has_results   BOOLEAN,
    response_mode TEXT,
    latency_ms    INTEGER,
    error         BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_ci_ts     ON chat_interactions (ts);
CREATE INDEX IF NOT EXISTS idx_ci_intent ON chat_interactions (intent, ts);

CREATE TABLE IF NOT EXISTS chat_feedback (
    id           BIGSERIAL PRIMARY KEY,
    ts           TIMESTAMPTZ NOT NULL DEFAULT now(),
    session_id   TEXT NOT NULL,
    message_id   TEXT,
    rating       TEXT NOT NULL CHECK (rating IN ('up', 'down')),
    user_query   TEXT,
    bot_response TEXT,
    comment      TEXT,
    intent       TEXT
);
CREATE INDEX IF NOT EXISTS idx_cf_ts ON chat_feedback (ts);
"""

_INSERT_INTERACTION = """
INSERT INTO chat_interactions
    (session_id, intent, user_query, keywords, city, employer,
     result_count, has_results, response_mode, latency_ms, error)
VALUES %s
"""

_INSERT_FEEDBACK = """
INSERT INTO chat_feedback
    (session_id, message_id, rating, user_query, bot_response, comment, intent)
VALUES %s
"""


def _cap(value, key):
    if value is None:
        return None
    return str(value)[:FIELD_CAPS.get(key, 200)]


class AnalyticsLogger:
    """Bounded queue + background batch flusher. All public methods are
    non-blocking; a full queue or dead database silently drops events."""

    def __init__(self, conn_params: dict, io_executor, retention_days: int = 90):
        self.conn_params = conn_params
        self.io_executor = io_executor
        self.retention_days = retention_days
        self.queue: asyncio.Queue = asyncio.Queue(maxsize=QUEUE_MAX)
        self._tasks: list[asyncio.Task] = []
        self.enabled = False

    # -- lifecycle -------------------------------------------------------------

    async def start(self) -> bool:
        loop = asyncio.get_event_loop()
        try:
            await loop.run_in_executor(self.io_executor, self._init_schema)
        except Exception as e:
            log.warning("Analytics disabled — schema init failed: %s", e)
            return False
        self.enabled = True
        self._tasks.append(asyncio.create_task(self._flusher()))
        if self.retention_days > 0:
            self._tasks.append(asyncio.create_task(self._retention_loop()))
        log.info("Analytics enabled (retention: %s days)",
                 self.retention_days or "unlimited")
        return True

    async def close(self):
        for t in self._tasks:
            t.cancel()
        try:
            await asyncio.get_event_loop().run_in_executor(
                self.io_executor, self._flush_sync, self._drain())
        except Exception:
            pass

    # -- non-blocking event intake -----------------------------------------------

    def log_interaction(self, *, session_id: str, intent: str | None,
                        user_query: str | None, keywords: str | None = None,
                        city: str | None = None, employer: str | None = None,
                        result_count: int = 0, has_results: bool | None = None,
                        response_mode: str = "", latency_ms: int | None = None,
                        error: bool = False):
        if not self.enabled:
            return
        row = (session_id, intent, _cap(user_query, "user_query"),
               _cap(keywords, "keywords"), _cap(city, "city"),
               _cap(employer, "employer"), result_count, has_results,
               response_mode, latency_ms, error)
        self._put(("interaction", row))

    def log_feedback(self, *, session_id: str, message_id: str, rating: str,
                     user_query: str, bot_response: str, comment: str,
                     intent: str):
        if not self.enabled:
            return
        row = (session_id, message_id, rating, _cap(user_query, "user_query"),
               _cap(bot_response, "bot_response"), _cap(comment, "comment"),
               intent)
        self._put(("feedback", row))

    def _put(self, item):
        try:
            self.queue.put_nowait(item)
        except asyncio.QueueFull:
            log.warning("Analytics queue full — dropping event")

    # -- background flushing -------------------------------------------------------

    def _drain(self) -> list:
        items = []
        while not self.queue.empty():
            try:
                items.append(self.queue.get_nowait())
            except asyncio.QueueEmpty:
                break
        return items

    async def _flusher(self):
        loop = asyncio.get_event_loop()
        while True:
            await asyncio.sleep(FLUSH_INTERVAL_SECONDS)
            batch = self._drain()
            if not batch:
                continue
            try:
                await loop.run_in_executor(
                    self.io_executor, self._flush_sync, batch)
            except Exception as e:
                log.warning("Analytics flush failed (%d events dropped): %s",
                            len(batch), e)

    def _flush_sync(self, batch: list):
        if not batch:
            return
        interactions = [row for kind, row in batch if kind == "interaction"]
        feedback     = [row for kind, row in batch if kind == "feedback"]
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                if interactions:
                    execute_values(cur, _INSERT_INTERACTION, interactions)
                if feedback:
                    execute_values(cur, _INSERT_FEEDBACK, feedback)
            conn.commit()
        finally:
            conn.close()

    async def _retention_loop(self):
        loop = asyncio.get_event_loop()
        while True:
            try:
                await loop.run_in_executor(self.io_executor, self._purge_sync)
            except Exception as e:
                log.warning("Analytics retention purge failed: %s", e)
            await asyncio.sleep(60 * 60 * 24)

    def _purge_sync(self):
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                for table in ("chat_interactions", "chat_feedback"):
                    cur.execute(
                        f"DELETE FROM {table} WHERE ts < now() - "
                        f"make_interval(days => %s)", (self.retention_days,))
                    if cur.rowcount:
                        log.info("Retention purge: %d rows from %s",
                                 cur.rowcount, table)
            conn.commit()
        finally:
            conn.close()

    # -- sync plumbing ---------------------------------------------------------------

    def _connect(self):
        return psycopg2.connect(connect_timeout=5, **self.conn_params)

    def _init_schema(self):
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute(SCHEMA_SQL)
            conn.commit()
        finally:
            conn.close()

# ---------------------------------------------------------------------------
# Metric queries (read paths — run via executor from the admin endpoints)
# ---------------------------------------------------------------------------

_METRIC_QUERIES = {
    "summary": """
        SELECT
          count(*) FILTER (WHERE ts > now() - interval '24 hours')      AS requests_24h,
          count(*) FILTER (WHERE ts > now() - interval '7 days')        AS requests_7d,
          count(DISTINCT session_id)
              FILTER (WHERE ts > now() - interval '7 days')             AS sessions_7d,
          count(*) FILTER (WHERE error AND ts > now() - interval '24 hours') AS errors_24h
        FROM chat_interactions""",
    "volume_daily": """
        SELECT to_char(date_trunc('day', ts), 'YYYY-MM-DD') AS k,
               count(*) AS n, count(DISTINCT session_id) AS sessions
        FROM chat_interactions
        WHERE ts > now() - interval '14 days'
        GROUP BY 1 ORDER BY 1""",
    "intents": """
        SELECT coalesce(intent, 'unknown') AS k, count(*) AS n
        FROM chat_interactions
        WHERE ts > now() - interval '7 days'
        GROUP BY 1 ORDER BY n DESC""",
    "top_queries": """
        SELECT lower(trim(user_query)) AS k, count(*) AS n
        FROM chat_interactions
        WHERE ts > now() - interval '7 days'
          AND user_query IS NOT NULL AND user_query <> '__load_more__'
        GROUP BY 1 ORDER BY n DESC LIMIT 15""",
    "zero_result_searches": """
        SELECT lower(trim(user_query)) AS k, count(*) AS n
        FROM chat_interactions
        WHERE ts > now() - interval '7 days'
          AND intent IN ('job_search', 'both') AND has_results = FALSE
        GROUP BY 1 ORDER BY n DESC LIMIT 10""",
    "top_cities": """
        SELECT city AS k, count(*) AS n FROM chat_interactions
        WHERE ts > now() - interval '7 days' AND city IS NOT NULL
        GROUP BY 1 ORDER BY n DESC LIMIT 10""",
    "top_keywords": """
        SELECT lower(keywords) AS k, count(*) AS n FROM chat_interactions
        WHERE ts > now() - interval '7 days' AND keywords IS NOT NULL
        GROUP BY 1 ORDER BY n DESC LIMIT 10""",
    "top_employers": """
        SELECT employer AS k, count(*) AS n FROM chat_interactions
        WHERE ts > now() - interval '7 days' AND employer IS NOT NULL
        GROUP BY 1 ORDER BY n DESC LIMIT 10""",
    "latency_by_intent": """
        SELECT coalesce(intent, 'unknown') AS k, count(*) AS n,
               round((percentile_cont(0.5)
                   WITHIN GROUP (ORDER BY latency_ms))::numeric)  AS p50,
               round((percentile_cont(0.95)
                   WITHIN GROUP (ORDER BY latency_ms))::numeric)  AS p95
        FROM chat_interactions
        WHERE ts > now() - interval '7 days' AND latency_ms IS NOT NULL
        GROUP BY 1 ORDER BY n DESC""",
    "satisfaction": """
        SELECT
          count(*) FILTER (WHERE rating = 'up')   AS up,
          count(*) FILTER (WHERE rating = 'down') AS down
        FROM chat_feedback
        WHERE ts > now() - interval '30 days'""",
}


def fetch_metrics(conn_params: dict) -> dict:
    conn = psycopg2.connect(connect_timeout=5, **conn_params)
    try:
        out = {}
        with conn.cursor(cursor_factory=RealDictCursor) as cur:
            for name, sql in _METRIC_QUERIES.items():
                cur.execute(sql)
                rows = cur.fetchall()
                if name in ("summary", "satisfaction"):
                    out[name] = dict(rows[0]) if rows else {}
                else:
                    out[name] = [dict(r) for r in rows]
        return out
    finally:
        conn.close()


def fetch_feedback(conn_params: dict, limit: int = 100) -> list[dict]:
    """Return recent feedback rows shaped like the old Redis JSON entries."""
    conn = psycopg2.connect(connect_timeout=5, **conn_params)
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cur:
            cur.execute("""
                SELECT to_char(ts AT TIME ZONE 'UTC',
                               'YYYY-MM-DD"T"HH24:MI:SS') AS timestamp,
                       session_id, message_id, rating, user_query,
                       bot_response, comment, intent
                FROM chat_feedback ORDER BY ts DESC LIMIT %s""", (limit,))
            return [dict(r) for r in cur.fetchall()]
    finally:
        conn.close()

# ---------------------------------------------------------------------------
# Admin dashboard rendering (all values HTML-escaped)
# ---------------------------------------------------------------------------

def _esc(v, cap: int = 200) -> str:
    return html.escape(str(v if v is not None else "")[:cap])


def _bar_rows(rows: list[dict], max_label: int = 60) -> str:
    if not rows:
        return "<div class='empty'>No data yet.</div>"
    top = max(r["n"] for r in rows) or 1
    out = []
    for r in rows:
        pct = max(2, int(r["n"] / top * 100))
        out.append(
            f"<div class='bar-row'>"
            f"<div class='bar-label' title='{_esc(r['k'], 500)}'>{_esc(r['k'], max_label)}</div>"
            f"<div class='bar-track'><div class='bar-fill' "
            f"style='width:{pct}%'></div></div>"
            f"<div class='bar-num'>{int(r['n'])}</div></div>")
    return "".join(out)


def render_analytics_html(m: dict) -> str:
    s = m.get("summary") or {}
    sat = m.get("satisfaction") or {}
    up, down = int(sat.get("up") or 0), int(sat.get("down") or 0)
    sat_pct = f"{up / (up + down) * 100:.0f}%" if (up + down) else "&mdash;"

    volume_rows = "".join(
        f"<tr><td>{_esc(r['k'])}</td><td>{int(r['n'])}</td>"
        f"<td>{int(r['sessions'])}</td></tr>"
        for r in (m.get("volume_daily") or []))

    latency_rows = "".join(
        f"<tr><td>{_esc(r['k'])}</td><td>{int(r['n'])}</td>"
        f"<td>{int(r['p50'] or 0):,} ms</td><td>{int(r['p95'] or 0):,} ms</td></tr>"
        for r in (m.get("latency_by_intent") or []))

    def card(label, value):
        return (f"<div class='stat-card'><div class='stat-label'>{label}</div>"
                f"<div class='stat-value'>{value}</div></div>")

    def section(title, body):
        return f"<h2>{title}</h2>{body}"

    return f"""<!DOCTYPE html>
<html><head><title>WorkBC Career Advisor — Analytics</title><meta charset="utf-8">
<style>
  body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         margin: 0; padding: 20px; background: #f5f7fa; color: #333; }}
  h1 {{ margin: 0 0 20px; color: #04364A; }}
  h2 {{ margin: 34px 0 12px; color: #04364A; font-size: 18px; }}
  .stats {{ display: flex; gap: 16px; flex-wrap: wrap; }}
  .stat-card {{ background: white; border: 1px solid #ddd; border-radius: 8px;
               padding: 14px 22px; flex: 1; min-width: 140px;
               box-shadow: 0 2px 4px rgba(0,0,0,0.05); }}
  .stat-label {{ color: #888; font-size: 12px; text-transform: uppercase;
                letter-spacing: 1px; }}
  .stat-value {{ font-size: 30px; font-weight: bold; color: #028090;
                margin-top: 6px; }}
  .cols {{ display: flex; gap: 24px; flex-wrap: wrap; }}
  .col {{ flex: 1; min-width: 300px; }}
  .panel {{ background: white; border: 1px solid #ddd; border-radius: 8px;
           padding: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }}
  .bar-row {{ display: flex; align-items: center; gap: 10px; padding: 4px 0; }}
  .bar-label {{ width: 42%; font-size: 13px; overflow: hidden;
               text-overflow: ellipsis; white-space: nowrap; }}
  .bar-track {{ flex: 1; background: #eef1f4; border-radius: 4px; height: 14px; }}
  .bar-fill {{ background: #028090; height: 14px; border-radius: 4px; }}
  .bar-num {{ width: 44px; text-align: right; font-size: 13px; color: #555; }}
  table {{ width: 100%; border-collapse: collapse; background: white;
          box-shadow: 0 2px 4px rgba(0,0,0,0.05); }}
  th {{ background: #028090; color: white; text-align: left; padding: 9px 12px;
       font-size: 12px; text-transform: uppercase; }}
  td {{ padding: 8px 12px; border-top: 1px solid #eee; font-size: 13px; }}
  .empty {{ color: #999; font-style: italic; padding: 12px; }}
</style></head>
<body>
<h1>📈 WorkBC Career Advisor — Analytics</h1>

<div class="stats">
  {card("Requests (24h)", int(s.get("requests_24h") or 0))}
  {card("Requests (7d)", int(s.get("requests_7d") or 0))}
  {card("Sessions (7d)", int(s.get("sessions_7d") or 0))}
  {card("Errors (24h)", int(s.get("errors_24h") or 0))}
  {card("Satisfaction (30d)", f"{sat_pct} <span style='font-size:13px;color:#888'>({up}&uarr; {down}&darr;)</span>")}
</div>

<div class="cols">
  <div class="col">
    {section("Intent mix (7 days)", f"<div class='panel'>{_bar_rows(m.get('intents') or [])}</div>")}
    {section("Top questions (7 days)", f"<div class='panel'>{_bar_rows(m.get('top_queries') or [])}</div>")}
    {section("Zero-result job searches (7 days) — content gaps",
             f"<div class='panel'>{_bar_rows(m.get('zero_result_searches') or [])}</div>")}
  </div>
  <div class="col">
    {section("Top cities searched", f"<div class='panel'>{_bar_rows(m.get('top_cities') or [])}</div>")}
    {section("Top job keywords", f"<div class='panel'>{_bar_rows(m.get('top_keywords') or [])}</div>")}
    {section("Top employers searched", f"<div class='panel'>{_bar_rows(m.get('top_employers') or [])}</div>")}
  </div>
</div>

{section("Daily volume (14 days)",
         f"<table><thead><tr><th>Day</th><th>Requests</th><th>Sessions</th></tr></thead><tbody>{volume_rows}</tbody></table>")}

{section("Latency by intent (7 days)",
         f"<table><thead><tr><th>Intent</th><th>Requests</th><th>P50</th><th>P95</th></tr></thead><tbody>{latency_rows}</tbody></table>")}

</body></html>"""
