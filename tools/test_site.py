#!/usr/bin/env python3
"""Render modulu ve statik site ureticisi testleri: python3 tools/test_site.py"""
import sys
import tempfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "server"))
sys.path.insert(0, str(REPO / "tools"))

from render import md_files, render_nav, to_html, page  # noqa: E402
from build_site import build  # noqa: E402


def main() -> int:
    fails = []

    def check(cond, label):
        if not cond:
            fails.append(label)

    # --- to_html ---
    h = to_html("# Baslik\n\n| a | b |\n|---|---|\n| 1 | 2 |\n")
    check("<table>" in h, "to_html: tablo render edilmedi")
    check("Baslik" in h, "to_html: baslik kayboldu")
    h2 = to_html("```php\n<?php echo 1;\n```\n")
    check("language-php" in h2, "to_html: fenced code dil sinifi yok")
    # XSS: ham HTML markdown'da gecerlidir ama script beklemiyoruz
    check("Ş" in to_html("# Ş"), "to_html: Turkce karakter bozuldu")

    # --- md_files / render_nav ---
    with tempfile.TemporaryDirectory() as td:
        root = Path(td)
        (root / "alt").mkdir()
        (root / "b.md").write_text("# B", encoding="utf-8")
        (root / "a.md").write_text("# A", encoding="utf-8")
        (root / "alt" / "c.md").write_text("# C", encoding="utf-8")
        (root / "resim.png").write_bytes(b"x")

        files = md_files(root)
        names = [f.name for f in files]
        check(names == ["a.md", "b.md", "c.md"], f"md_files: siralama/filtre hatali -> {names}")
        check(all(f.suffix == ".md" for f in files), "md_files: md disi dosya sizdi")

        nav = render_nav(files, root, "a.md")
        check('href="a.md"' in nav, "render_nav: kok sayfada goreli link uretilmedi")
        check('class="on"' in nav, "render_nav: aktif dosya isaretlenmedi")
        check("alt" in nav, "render_nav: klasor grubu yok")

        # bos klasor
        with tempfile.TemporaryDirectory() as td2:
            check("bos" in render_nav([], Path(td2), None), "render_nav: bos durum metni yok")

        # --- page ---
        p = page(title="T", nav=nav, body="<p>x</p>", count=3)
        check("<title>T" in p, "page: baslik yok")
        check("prefers-color-scheme" in p, "page: koyu tema yok")

        # --- build (statik site) ---
        with tempfile.TemporaryDirectory() as out_td:
            out = Path(out_td)
            written = build(root, out)
            check((out / "index.html").is_file(), "build: index.html uretilmedi")
            check((out / "a.html").is_file(), "build: a.html uretilmedi")
            check((out / "alt" / "c.html").is_file(), "build: alt/c.html uretilmedi")
            check(len(written) == 4, f"build: 4 dosya beklenirdi (3 md + index), {len(written)} uretildi")
            check(not (out / "resim.png").exists(), "build: md disi dosya kopyalandi")
            idx = (out / "index.html").read_text(encoding="utf-8")
            check('href="a.html"' in idx, "build: index navigasyonu goreli .html linki kullanmiyor")
            check('href="/' not in idx, "build: index'te mutlak link var (Pages alt yolunda kirilir)")
            sub = (out / "alt" / "c.html").read_text(encoding="utf-8")
            check('href="../a.html"' in sub, "build: alt klasorde ust dizine goreli link yok")
            check('href="/' not in sub, "build: alt sayfada mutlak link var")
            body = (out / "a.html").read_text(encoding="utf-8")
            check("<h1" in body, "build: markdown govdesi render edilmedi")

    if fails:
        print("BASARISIZ:")
        for f in fails:
            print("  -", f)
        return 1
    print("Tum testler gecti.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
