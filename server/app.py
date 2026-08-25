#!/usr/bin/env python3
"""docs/ altindaki markdown dosyalarini tarayicida gosteren kucuk sunucu.

Kullanim:
    python3 server/app.py                 # http://127.0.0.1:8000
    python3 server/app.py --port 9000 --host 0.0.0.0
"""
from __future__ import annotations

import argparse
import html
import urllib.parse
from functools import partial
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

try:
    import markdown as md_lib
except ImportError:  # pragma: no cover
    md_lib = None

REPO = Path(__file__).resolve().parent.parent
DOCS = REPO / "docs"

PAGE = """<!doctype html>
<html lang="tr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title} - bist docs</title>
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


def md_files() -> list[Path]:
    if not DOCS.is_dir():
        return []
    return sorted(p for p in DOCS.rglob("*") if p.is_file() and p.suffix.lower() in {".md", ".markdown"})


def render_nav(files: list[Path], current: str | None) -> str:
    out, group = [], None
    for f in files:
        rel = f.relative_to(DOCS)
        g = str(rel.parent) if str(rel.parent) != "." else ""
        if g != group:
            group = g
            if g:
                out.append(f'<div class="grp">{html.escape(g)}</div>')
        href = "/" + urllib.parse.quote(str(rel))
        cls = ' class="on"' if str(rel) == current else ""
        out.append(f'<a href="{href}"{cls}>{html.escape(rel.name)}</a>')
    return "".join(out) or '<div class="empty">bos</div>'


def to_html(text: str) -> str:
    if md_lib is None:
        return "<pre>" + html.escape(text) + "</pre>"
    return md_lib.markdown(text, extensions=["extra", "tables", "fenced_code", "toc", "sane_lists"])


class Handler(BaseHTTPRequestHandler):
    server_version = "bist-docs"

    def log_message(self, fmt, *a):  # sessiz log
        pass

    def _send(self, body: str, code: int = 200) -> None:
        data = body.encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self) -> None:  # noqa: N802
        path = urllib.parse.unquote(urllib.parse.urlparse(self.path).path).lstrip("/")
        files = md_files()

        if path in ("", "index.html"):
            body = (
                "<h1>bist docs</h1>"
                f"<p>{len(files)} markdown dosyasi. Soldan sec.</p>"
                if files
                else "<h1>bist docs</h1><p class='empty'>docs/ bos. "
                "<code>python3 tools/zip_to_md.py incoming/paket.zip</code> ile doldur.</p>"
            )
            self._send(PAGE.format(title="index", nav=render_nav(files, None), body=body, count=len(files)))
            return

        target = (DOCS / path).resolve()
        if not str(target).startswith(str(DOCS.resolve())) or not target.is_file():
            self._send(PAGE.format(title="404", nav=render_nav(files, None),
                                   body="<h1>404</h1><p class='empty'>Dosya yok.</p>", count=len(files)), 404)
            return

        text = target.read_text(encoding="utf-8", errors="replace")
        self._send(PAGE.format(title=html.escape(target.name), nav=render_nav(files, path),
                               body=to_html(text), count=len(files)))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=8000)
    args = ap.parse_args()
    DOCS.mkdir(parents=True, exist_ok=True)
    srv = ThreadingHTTPServer((args.host, args.port), partial(Handler))
    print(f"bist docs sunucusu: http://{args.host}:{args.port}  (docs/ = {DOCS})", flush=True)
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
