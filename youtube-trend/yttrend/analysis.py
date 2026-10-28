"""Niche-level aggregation: demand, competition and title patterns."""

from __future__ import annotations

import math
import re
from collections import Counter
from datetime import datetime
from statistics import median

from .metrics import Video

SMALL_CHANNEL_SUBS = 50_000

STOPWORDS = set(
    """a an and are as at be by for from how i in is it its me my of on or our so that the this to
    vs was we what when who why will with you your | - & ft feat official video shorts short""".split()
)


def niche_summary(videos: list[Video], now: datetime | None = None) -> dict:
    """Aggregate stats for one niche (a set of recent top videos for a query)."""
    if not videos:
        return {"videos": 0, "opportunity": 0.0}
    vpd = [v.views_per_day(now) for v in videos]
    known = [v for v in videos if v.subscribers is not None]
    small = [v for v in known if v.subscribers < SMALL_CHANNEL_SUBS]
    small_share = len(small) / len(known) if known else 0.0
    outliers = [v.outlier for v in known]
    med_vpd = median(vpd)
    return {
        "videos": len(videos),
        "channels": len({v.channel_id for v in videos}),
        "median_views_per_day": round(med_vpd),
        "median_views": round(median(v.views for v in videos)),
        "median_outlier": round(median(outliers), 2) if outliers else None,
        "small_channel_share": round(small_share, 2),
        "shorts_share": round(sum(v.is_short for v in videos) / len(videos), 2),
        "median_engagement_pct": round(median(v.engagement for v in videos) * 100, 2),
        "opportunity": opportunity_score(med_vpd, small_share),
    }


def opportunity_score(median_vpd: float, small_share: float) -> float:
    """Demand (log of median daily views) weighted by how often small channels make the top list.

    A niche where only big channels rank scores ~half of one where newcomers break through.
    """
    return round(math.log10(median_vpd + 1) * (0.5 + small_share), 2)


def top_outliers(videos: list[Video], n: int = 10, small_only: bool = False) -> list[Video]:
    pool = [v for v in videos if v.outlier is not None]
    if small_only:
        pool = [v for v in pool if v.subscribers < SMALL_CHANNEL_SUBS]
    return sorted(pool, key=lambda v: v.outlier, reverse=True)[:n]


def top_velocity(videos: list[Video], n: int = 10, now: datetime | None = None) -> list[Video]:
    return sorted(videos, key=lambda v: v.views_per_day(now), reverse=True)[:n]


def title_patterns(videos: list[Video], top_words: int = 15) -> dict:
    """What winning titles look like: frequent words and structural habits."""
    words: Counter[str] = Counter()
    tags: Counter[str] = Counter()
    for v in videos:
        words.update(w for w in re.findall(r"[a-z0-9']+", v.title.lower()) if w not in STOPWORDS and len(w) > 2)
        tags.update(t.lower() for t in v.tags)
    n = len(videos) or 1
    return {
        "top_words": words.most_common(top_words),
        "top_tags": tags.most_common(top_words),
        "avg_title_len": round(sum(len(v.title) for v in videos) / n),
        "with_number_pct": round(100 * sum(bool(re.search(r"\d", v.title)) for v in videos) / n),
        "question_pct": round(100 * sum("?" in v.title for v in videos) / n),
        "caps_word_pct": round(100 * sum(bool(re.search(r"\b[A-Z]{3,}\b", v.title)) for v in videos) / n),
        "emoji_pct": round(100 * sum(bool(re.search(r"[\U0001F300-\U0001FAFF]", v.title)) for v in videos) / n),
    }
