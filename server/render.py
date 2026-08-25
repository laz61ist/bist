"""Markdown -> HTML render katmani.

Hem canli sunucu (server/app.py) hem statik site ureticisi (tools/build_site.py)
bu modulu kullanir; iki cikti gorsel olarak ayni kalir.
"""
from __future__ import annotations

import html
import os
import urllib.parse
from pathlib import Path

try:
    import markdown as md_lib
except ImportError:  # pragma: no cover
    md_lib = None

MD_SUFFIXES = {".md", ".markdown", ".mdown", ".mkd"}

_TEMPLATE = """<!doctype html>
<html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title} - bist docs</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23FF6B00'/%3E%3Ctext x='16' y='23' font-family='monospace' font-size='18' font-weight='700' fill='%23fff' text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
<style>
:root{{color-scheme:light dark;--bg:#fff;--fg:#1a1a1a;--mut:#666;--line:#e3e3e3;--acc:#0a58ca;--code:#f5f5f5}}
@media (prefers-color-scheme:dark){{:root{{--bg:#16181c;--fg:#e6e6e6;--mut:#9aa0a6;--line:#2c3036;--acc:#7aa7ff;--code:#1f2329}}}}
*{{box-sizing:border-box}}
body{{margin:0;background:var(--bg);color:var(--fg);font:16px/1.65 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;display:flex;min-height:100vh}}
aside{{width:290px;flex:0 0 290px;border-right:1px solid var(--line);padding:20px 16px;overflow:auto;max-height:100vh}}
aside h2{{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut);margin:0 0 12px}}
aside a{{display:block;padding:5px 8px;border-radius:6px;color:var(--fg);text-decoration:none;font-size:14px;word-break:break-word}}
aside a:hover{{background:var(--code)}}
aside a.on{{background:var(--acc);color:#fff}}
aside .grp{{color:var(--mut);font-size:12px;margin:14px 0 4px;font-weight:600}}
main{{flex:1;padding:32px 40px;overflow:auto;max-height:100vh;max-width:900px}}
main img{{max-width:100%}}
pre{{background:var(--code);padding:14px;border-radius:8px;overflow-x:auto}}
code{{background:var(--code);padding:2px 5px;border-radius:4px;font-size:.9em}}
pre code{{background:none;padding:0}}
table{{border-collapse:collapse;display:block;overflow-x:auto;max-width:100%}}
th,td{{border:1px solid var(--line);padding:7px 11px;text-align:left}}
blockquote{{border-left:3px solid var(--line);margin:0;padding-left:14px;color:var(--mut)}}
h1,h2,h3{{line-height:1.3}}
a{{color:var(--acc)}}
.empty{{color:var(--mut)}}
@media (max-width:800px){{body{{flex-direction:column}}aside{{width:100%;flex:none;max-height:none;border-right:0;border-bottom:1px solid var(--line)}}main{{padding:20px}}}}
</style></head><body>
<aside><h2>docs ({count})</h2>{nav}</aside>
<main>{body}</main></body></html>"""


def page(*, title: str, nav: str, body: str, count: int) -> str:
    """Tam HTML sayfasini uret."""
    return _TEMPLATE.format(title=title, nav=nav, body=body, count=count)


def md_files(root: Path) -> list[Path]:
    """root altindaki tum markdown dosyalarini menu sirasina gore dondur.

    Once kok dizindeki dosyalar, sonra klasor klasor. Duz yol siralamasi
    (`a.md`, `alt/c.md`, `b.md`) klasor gruplarini bolerdi.
    """
    if not root.is_dir():
        return []
    found = (p for p in root.rglob("*") if p.is_file() and p.suffix.lower() in MD_SUFFIXES)
    return sorted(found, key=lambda p: (p.relative_to(root).parts[:-1], p.name.lower()))


def render_nav(files: list[Path], root: Path, current: str | None, link_ext: str | None = None) -> str:
    """Sol menuyu uret.

    link_ext verilirse (ornegin ".html") baglantilar o uzantiyi kullanir.
    Baglantilar `current`in bulundugu klasore GORELI uretilir; GitHub Pages
    proje sitesi /<repo>/ alt yolunda yayinlandigi icin mutlak link kirilir.
    """
    out: list[str] = []
    group: str | None = None
    base = Path(current).parent if current else Path(".")
    for f in files:
        rel = f.relative_to(root)
        grp = str(rel.parent) if str(rel.parent) != "." else ""
        if grp != group:
            group = grp
            if grp:
                out.append(f'<div class="grp">{html.escape(grp)}</div>')
        link_rel = rel.with_suffix(link_ext) if link_ext else rel
        href = urllib.parse.quote(os.path.relpath(link_rel, base))
        cls = ' class="on"' if str(rel) == current else ""
        out.append(f'<a href="{href}"{cls}>{html.escape(rel.name)}</a>')
    return "".join(out) or '<div class="empty">bos</div>'


def to_html(text: str) -> str:
    """Markdown metnini HTML'e cevir. markdown paketi yoksa duz metin olarak goster."""
    if md_lib is None:
        return "<pre>" + html.escape(text) + "</pre>"
    return md_lib.markdown(text, extensions=["extra", "tables", "fenced_code", "toc", "sane_lists"])
