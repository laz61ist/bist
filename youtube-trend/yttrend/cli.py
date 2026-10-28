"""Command line entry point: python -m yttrend <command> ..."""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

from dotenv import load_dotenv

from . import analysis, report
from .metrics import Video
from .store import Store
from .youtube import QuotaExceeded, YouTubeClient

ROOT = Path(__file__).resolve().parent.parent
OUTPUT = ROOT / "output"
DB = ROOT / "data" / "trend.db"


def _slug(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")[:40]


def _out_path(name: str) -> Path:
    return OUTPUT / f"{datetime.now():%Y-%m-%d_%H%M}_{_slug(name)}"


def collect_niche(yt: YouTubeClient, query: str, region: str, days: int, duration: str | None) -> list[Video]:
    ids = yt.search_ids(query, region=region, days=days, duration=duration)
    videos = yt.videos(ids)
    yt.attach_subscribers(videos)
    return videos


def niche_section(name: str, videos: list[Video]) -> tuple[str, dict]:
    summary = analysis.niche_summary(videos)
    patterns = analysis.title_patterns(videos)
    md = [
        f"## {name}",
        f"{summary['videos']} video · {summary.get('channels', 0)} kanal · fırsat skoru **{summary['opportunity']}**",
        "",
        "### En hızlı büyüyenler (günlük izlenme)",
        report.video_table(analysis.top_velocity(videos, 10)),
        "",
        "### Küçük kanalların patlayan videoları (izlenme / abone)",
        report.video_table(analysis.top_outliers(videos, 10, small_only=True)) or "-",
        "",
        "### Başlık kalıpları",
        report.patterns_block(patterns),
        "",
    ]
    data = {
        "niche": name,
        "summary": summary,
        "title_patterns": patterns,
        "top_velocity": [v.to_row() for v in analysis.top_velocity(videos, 15)],
        "top_outliers": [v.to_row() for v in analysis.top_outliers(videos, 15)],
    }
    return "\n".join(md), data


def finish(title: str, body: list[str], data: dict, videos: list[Video], yt: YouTubeClient, args) -> None:
    out = _out_path(title)
    if args.ai:
        from .ai import generate_ideas

        print("Claude içerik planını hazırlıyor...", file=sys.stderr)
        body += ["---", "# Claude içerik planı", generate_ideas(data, n_ideas=args.ideas)]
    header = [f"# {title}", f"_{datetime.now(timezone.utc):%Y-%m-%d %H:%M} UTC · harcanan API kotası: {yt.quota_used} birim_", ""]
    out.parent.mkdir(parents=True, exist_ok=True)
    out.with_suffix(".md").write_text("\n".join(header + body), encoding="utf-8")
    report.write_csv(videos, out.with_suffix(".csv"))
    report.write_json(data, out.with_suffix(".json"))
    print(f"Rapor: {out.with_suffix('.md')}\nVeri:  {out.with_suffix('.csv')}, {out.with_suffix('.json')}")
    print(f"Kullanılan kota: {yt.quota_used} / 10.000 birim")


def cmd_trending(yt: YouTubeClient, args) -> None:
    cats = yt.categories(args.region)
    videos = yt.most_popular(args.region, args.category, limit=args.limit)
    yt.attach_subscribers(videos)
    Store(DB).save(videos, niche=f"trending:{args.region}:{args.category or 'all'}")
    by_cat: dict[str, int] = {}
    for v in videos:
        name = cats.get(v.category_id, v.category_id)
        by_cat[name] = by_cat.get(name, 0) + 1
    body = [
        "## Kategori dağılımı",
        "\n".join(f"- {k}: {n}" for k, n in sorted(by_cat.items(), key=lambda x: -x[1])),
        "",
        "## Trend listesi",
        report.video_table(videos),
        "",
        "## Başlık kalıpları",
        report.patterns_block(analysis.title_patterns(videos)),
    ]
    data = {
        "source": f"YouTube trending {args.region}",
        "categories": by_cat,
        "title_patterns": analysis.title_patterns(videos),
        "videos": [v.to_row() for v in videos],
    }
    finish(f"Trendler {args.region}", body, data, videos, yt, args)


def cmd_niche(yt: YouTubeClient, args) -> None:
    videos = collect_niche(yt, args.query, args.region, args.days, args.duration)
    Store(DB).save(videos, niche=args.query)
    md, data = niche_section(args.query, videos)
    finish(f"Niş analizi - {args.query}", [md], data, videos, yt, args)


def cmd_scan(yt: YouTubeClient, args) -> None:
    cfg = json.loads(Path(args.config).read_text(encoding="utf-8"))
    region, days = cfg.get("region", "US"), cfg.get("days", 30)
    store = Store(DB)
    sections, summaries, all_videos, datas = [], [], [], []
    for niche in cfg["niches"]:
        print(f"Taranıyor: {niche['name']}", file=sys.stderr)
        videos = collect_niche(yt, niche["query"], region, days, niche.get("duration"))
        store.save(videos, niche=niche["name"])
        md, data = niche_section(niche["name"], videos)
        sections.append(md)
        summaries.append((niche["name"], data["summary"]))
        datas.append(data)
        all_videos += videos
    body = ["## Niş sıralaması", report.niche_ranking_table(summaries), "", *sections]
    finish("Niş taraması", body, {"region": region, "days": days, "niches": datas}, all_videos, yt, args)


def cmd_growth(args) -> None:
    rows = Store(DB).growth(args.niche)
    if not rows:
        print("Henüz büyüme verisi yok: aynı nişi en az 6 saat arayla iki kez tarayın.")
        return
    print("| Başlık | Kanal | Niş | Kazanılan | Süre (saat) | Günlük kazanım |\n|---|---|---|---|---|---|")
    for r in rows[: args.limit]:
        print(f"| {r['title'][:60]} | {r['channel']} | {r['niche']} | {r['views_gained']} | {r['hours']} | {r['gain_per_day']} |")


def main(argv: list[str] | None = None) -> None:
    load_dotenv(ROOT / ".env")
    p = argparse.ArgumentParser(prog="yttrend", description="YouTube trend ve niş analizi")
    sub = p.add_subparsers(dest="cmd", required=True)

    def add_common(sp):
        sp.add_argument("--ai", action="store_true", help="Claude ile içerik planı ve video fikirleri üret")
        sp.add_argument("--ideas", type=int, default=10, help="Üretilecek fikir sayısı")

    t = sub.add_parser("trending", help="Ülkenin trend listesi (ucuz: ~3 birim)")
    t.add_argument("--region", default="US")
    t.add_argument("--category", help="Kategori id (ör. 1 Film & Animation, 24 Entertainment, 27 Education)")
    t.add_argument("--limit", type=int, default=50)
    add_common(t)

    n = sub.add_parser("niche", help="Tek bir arama sorgusunu analiz et (~102 birim)")
    n.add_argument("query")
    n.add_argument("--region", default="US")
    n.add_argument("--days", type=int, default=30, help="Son kaç günde yayınlanan videolar")
    n.add_argument("--duration", choices=["short", "medium", "long"], help="short = 4 dk altı")
    add_common(n)

    s = sub.add_parser("scan", help="config dosyasındaki tüm nişleri karşılaştır")
    s.add_argument("--config", default=str(ROOT / "config" / "nisler.json"))
    add_common(s)

    g = sub.add_parser("growth", help="Tekrarlanan taramalardan gerçek büyüme hızı")
    g.add_argument("--niche")
    g.add_argument("--limit", type=int, default=25)

    args = p.parse_args(argv)
    if args.cmd == "growth":
        return cmd_growth(args)
    try:
        yt = YouTubeClient(os.environ.get("YOUTUBE_API_KEY", ""))
        {"trending": cmd_trending, "niche": cmd_niche, "scan": cmd_scan}[args.cmd](yt, args)
    except (ValueError, QuotaExceeded) as e:
        sys.exit(f"Hata: {e}")


if __name__ == "__main__":
    main()
