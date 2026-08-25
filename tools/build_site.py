#!/usr/bin/env python3
"""docs/ altindaki markdown dosyalarini statik HTML siteye cevirir (GitHub Pages icin).

Kullanim:
    python3 tools/build_site.py                    # docs/ -> _site/
    python3 tools/build_site.py --src docs --out _site
"""
from __future__ import annotations

import argparse
import html
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "server"))

from render import md_files, page, render_nav, to_html  # noqa: E402


def build(src: Path, out: Path) -> list[Path]:
    """src altindaki her .md icin out altina .html uret, ayrica index.html yaz."""
    files = md_files(src)
    out.mkdir(parents=True, exist_ok=True)
    written: list[Path] = []

    for f in files:
        rel = f.relative_to(src)
        nav = render_nav(files, src, str(rel), link_ext=".html")
        body = to_html(f.read_text(encoding="utf-8", errors="replace"))
        target = out / rel.with_suffix(".html")
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(
            page(title=html.escape(f.name), nav=nav, body=body, count=len(files)),
            encoding="utf-8",
        )
        written.append(target)

    nav = render_nav(files, src, None, link_ext=".html")
    if files:
        items = "".join(
            f'<li><a href="{f.relative_to(src).with_suffix(".html")}">'
            f"{html.escape(str(f.relative_to(src)))}</a></li>"
            for f in files
        )
        body = f"<h1>bist docs</h1><p>{len(files)} markdown dosyasi.</p><ul>{items}</ul>"
    else:
        body = "<h1>bist docs</h1><p class='empty'>docs/ bos.</p>"
    index = out / "index.html"
    index.write_text(page(title="index", nav=nav, body=body, count=len(files)), encoding="utf-8")
    written.append(index)
    # Jekyll'in _ ile baslayan yollari yutmasini engelle
    (out / ".nojekyll").write_text("", encoding="utf-8")
    return written


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", type=Path, default=REPO / "docs")
    ap.add_argument("--out", type=Path, default=REPO / "_site")
    args = ap.parse_args()
    written = build(args.src, args.out)
    for w in written:
        print(f"  + {w}")
    print(f"\n{len(written)} dosya uretildi -> {args.out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
