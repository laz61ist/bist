"""SQLite snapshots so repeated runs can measure real view growth over time."""

from __future__ import annotations

import sqlite3
from datetime import datetime, timezone
from pathlib import Path

from .metrics import Video

SCHEMA = """
CREATE TABLE IF NOT EXISTS videos (
    id TEXT PRIMARY KEY, title TEXT, channel TEXT, niche TEXT, published_at TEXT, duration_s INTEGER
);
CREATE TABLE IF NOT EXISTS snapshots (
    video_id TEXT, captured_at TEXT, views INTEGER, likes INTEGER, comments INTEGER, subscribers INTEGER,
    PRIMARY KEY (video_id, captured_at)
);
"""


class Store:
    def __init__(self, path: str | Path):
        Path(path).parent.mkdir(parents=True, exist_ok=True)
        self.db = sqlite3.connect(path)
        self.db.executescript(SCHEMA)

    def save(self, videos: list[Video], niche: str, at: datetime | None = None) -> None:
        ts = (at or datetime.now(timezone.utc)).isoformat()
        with self.db:
            self.db.executemany(
                "INSERT OR REPLACE INTO videos VALUES (?,?,?,?,?,?)",
                [(v.id, v.title, v.channel_title, niche, v.published_at.isoformat(), v.duration_s) for v in videos],
            )
            self.db.executemany(
                "INSERT OR REPLACE INTO snapshots VALUES (?,?,?,?,?,?)",
                [(v.id, ts, v.views, v.likes, v.comments, v.subscribers) for v in videos],
            )

    def growth(self, niche: str | None = None, min_hours: float = 6) -> list[dict]:
        """Views gained per day between each video's first and latest snapshot."""
        rows = self.db.execute(
            """
            SELECT v.id, v.title, v.channel, v.niche,
                   MIN(s.captured_at), MAX(s.captured_at)
            FROM videos v JOIN snapshots s ON s.video_id = v.id
            WHERE (? IS NULL OR v.niche = ?)
            GROUP BY v.id HAVING COUNT(*) > 1
            """,
            (niche, niche),
        ).fetchall()
        out = []
        for vid, title, channel, n, first, last in rows:
            v0 = self.db.execute("SELECT views FROM snapshots WHERE video_id=? AND captured_at=?", (vid, first)).fetchone()[0]
            v1 = self.db.execute("SELECT views FROM snapshots WHERE video_id=? AND captured_at=?", (vid, last)).fetchone()[0]
            hours = (datetime.fromisoformat(last) - datetime.fromisoformat(first)).total_seconds() / 3600
            if hours < min_hours:
                continue
            out.append({
                "id": vid, "title": title, "channel": channel, "niche": n,
                "views_gained": v1 - v0, "hours": round(hours, 1),
                "gain_per_day": round((v1 - v0) / hours * 24),
            })
        return sorted(out, key=lambda r: r["gain_per_day"], reverse=True)
