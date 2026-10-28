"""Per-video metrics: velocity, engagement and the outlier score."""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import datetime, timezone

SHORTS_MAX_SECONDS = 180  # Shorts can be up to 3 minutes since Oct 2024

_DURATION_RE = re.compile(r"P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?")


def parse_duration(iso: str) -> int:
    """ISO-8601 duration ("PT1H2M3S") -> seconds."""
    m = _DURATION_RE.fullmatch(iso or "")
    if not m:
        return 0
    d, h, mi, s = (int(x) if x else 0 for x in m.groups())
    return d * 86400 + h * 3600 + mi * 60 + s


@dataclass
class Video:
    id: str
    title: str
    channel_id: str
    channel_title: str
    published_at: datetime
    duration_s: int
    views: int
    likes: int = 0
    comments: int = 0
    tags: list[str] = field(default_factory=list)
    category_id: str = ""
    subscribers: int | None = None

    @property
    def url(self) -> str:
        return f"https://www.youtube.com/watch?v={self.id}"

    @property
    def is_short(self) -> bool:
        return 0 < self.duration_s <= SHORTS_MAX_SECONDS

    def age_days(self, now: datetime | None = None) -> float:
        now = now or datetime.now(timezone.utc)
        # Floor at one hour so brand-new uploads don't explode the rate
        return max((now - self.published_at).total_seconds(), 3600) / 86400

    def views_per_day(self, now: datetime | None = None) -> float:
        return self.views / self.age_days(now)

    @property
    def engagement(self) -> float:
        return (self.likes + self.comments) / self.views if self.views else 0.0

    @property
    def outlier(self) -> float | None:
        """Views / subscribers. >1 means the video reached beyond the channel's own audience."""
        if self.subscribers is None:
            return None
        return self.views / max(self.subscribers, 100)

    def to_row(self, now: datetime | None = None) -> dict:
        return {
            "id": self.id,
            "title": self.title,
            "channel": self.channel_title,
            "subscribers": self.subscribers,
            "published_at": self.published_at.isoformat(),
            "age_days": round(self.age_days(now), 1),
            "duration_s": self.duration_s,
            "format": "short" if self.is_short else "long",
            "views": self.views,
            "views_per_day": round(self.views_per_day(now)),
            "engagement_pct": round(self.engagement * 100, 2),
            "outlier": round(self.outlier, 2) if self.outlier is not None else None,
            "url": self.url,
        }
