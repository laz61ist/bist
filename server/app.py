#!/usr/bin/env python3
"""docs/ altindaki markdown dosyalarini tarayicida gosteren kucuk sunucu.

Kullanim:
    python3 server/app.py                 # http://127.0.0.1:8000
    python3 server/app.py --port 9000 --host 0.0.0.0

Statik surum icin: python3 tools/build_site.py  (ayni render katmanini kullanir)
"""
from __future__ import annotations

import argparse
import html
import sys
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "server"))

from render import md_files, page, render_nav, to_html  # noqa: E402

DOCS = REPO / "docs"


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
        files = md_files(DOCS)

        if path in ("", "index.html"):
            body = (
                f"<h1>bist docs</h1><p>{len(files)} markdown dosyasi. Soldan sec.</p>"
                if files
                else "<h1>bist docs</h1><p class='empty'>docs/ bos. "
                "<code>python3 tools/zip_to_md.py incoming/paket.zip</code> ile doldur.</p>"
            )
            self._send(page(title="index", nav=render_nav(files, DOCS, None), body=body, count=len(files)))
            return

        target = (DOCS / path).resolve()
        if not str(target).startswith(str(DOCS.resolve())) or not target.is_file():
            self._send(
                page(title="404", nav=render_nav(files, DOCS, None),
                     body="<h1>404</h1><p class='empty'>Dosya yok.</p>", count=len(files)),
                404,
            )
            return

        text = target.read_text(encoding="utf-8", errors="replace")
        self._send(page(title=html.escape(target.name), nav=render_nav(files, DOCS, path),
                        body=to_html(text), count=len(files)))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=8000)
    args = ap.parse_args()
    DOCS.mkdir(parents=True, exist_ok=True)
    srv = ThreadingHTTPServer((args.host, args.port), Handler)
    print(f"bist docs sunucusu: http://{args.host}:{args.port}  (docs/ = {DOCS})", flush=True)
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
