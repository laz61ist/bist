"""Markdown / CSV / JSON output."""

from __future__ import annotations

import csv
import json
from pathlib import Path

from .metrics import Video


def _fmt(n) -> str:
    if n is None:
        return "-"
    if isinstance(n, float) and n < 1000:
        return f"{n:.2f}"
    n = float(n)
    for unit, div in (("B", 1e9), ("M", 1e6), ("K", 1e3)):
        if n >= div:
            return f"{n / div:.1f}{unit}"
    return f"{n:.0f}"


def video_table(videos: list[Video]) -> str:
    lines = ["| # | Başlık | Kanal | Abone | İzlenme | Günlük | Outlier | Format |", "|---|---|---|---|---|---|---|---|"]
    for i, v in enumerate(videos, 1):
        title = v.title.replace("|", "/")[:70]
        lines.append(
            f"| {i} | [{title}]({v.url}) | {v.channel_title.replace('|', '/')} | {_fmt(v.subscribers)} | "
            f"{_fmt(v.views)} | {_fmt(v.views_per_day())} | {_fmt(v.outlier)} | {'Short' if v.is_short else 'Uzun'} |"
        )
    return "\n".join(lines)


def niche_ranking_table(summaries: list[tuple[str, dict]]) -> str:
    lines = [
        "| Niş | Fırsat | Medyan günlük izlenme | Küçük kanal payı | Medyan outlier | Shorts payı | Etkileşim % |",
        "|---|---|---|---|---|---|---|",
    ]
    for name, s in sorted(summaries, key=lambda x: x[1].get("opportunity", 0), reverse=True):
        if not s.get("videos"):
            lines.append(f"| {name} | 0 | - | - | - | - | - |")
            continue
        lines.append(
            f"| {name} | **{s['opportunity']}** | {_fmt(s['median_views_per_day'])} | %{round(s['small_channel_share'] * 100)} | "
            f"{_fmt(s['median_outlier'])} | %{round(s['shorts_share'] * 100)} | {s['median_engagement_pct']} |"
        )
    return "\n".join(lines)


def patterns_block(p: dict) -> str:
    words = ", ".join(f"{w} ({c})" for w, c in p["top_words"]) or "-"
    tags = ", ".join(f"{t} ({c})" for t, c in p["top_tags"][:10]) or "-"
    return (
        f"- Sık kelimeler: {words}\n"
        f"- Sık etiketler: {tags}\n"
        f"- Ortalama başlık uzunluğu: {p['avg_title_len']} karakter\n"
        f"- Rakam içeren: %{p['with_number_pct']} · Soru: %{p['question_pct']} · "
        f"BÜYÜK HARF kelime: %{p['caps_word_pct']} · Emoji: %{p['emoji_pct']}"
    )


def write_csv(videos: list[Video], path: Path) -> None:
    rows = [v.to_row() for v in videos]
    if not rows:
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0]))
        w.writeheader()
        w.writerows(rows)


def write_json(data: dict, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2, default=str), encoding="utf-8")
