#!/usr/bin/env python3
"""ZIP icindeki Markdown dosyalarini cikarip repo docs/ altina yerlestirir.

Kullanim:
    python3 tools/zip_to_md.py incoming/paket.zip
    python3 tools/zip_to_md.py incoming/paket.zip --dest docs/spec --flat
    python3 tools/zip_to_md.py --all            # incoming/ altindaki tum ziplar
    python3 tools/zip_to_md.py incoming/paket.zip --commit

Guvenlik: zip-slip (mutlak yol / ".." ) reddedilir, sadece .md/.markdown alinir.
"""
from __future__ import annotations

import argparse
import subprocess
import sys
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
MD_SUFFIXES = {".md", ".markdown", ".mdown", ".mkd"}
JUNK_PARTS = {"__MACOSX", ".DS_Store", ".git"}
# Windows'ta uretilen ziplerde UTF-8 bayragi yoksa isimler cp437 okunur.
FALLBACK_ENCODINGS = ("utf-8", "cp1254", "cp857")


def decode_name(info: zipfile.ZipInfo) -> str:
    """Turkce karakterli dosya adlarini duzelt (UTF-8 bayragi yoksa)."""
    if info.flag_bits & 0x800:
        return info.filename
    raw = info.filename.encode("cp437", errors="replace")
    for enc in FALLBACK_ENCODINGS:
        try:
            return raw.decode(enc)
        except UnicodeDecodeError:
            continue
    return info.filename


def is_safe(rel: Path) -> bool:
    if rel.is_absolute():
        return False
    return ".." not in rel.parts and not any(p in JUNK_PARTS for p in rel.parts)


def unique_path(path: Path) -> Path:
    """Ayni isim varsa uzerine yazma; -2, -3 ... ekle."""
    if not path.exists():
        return path
    stem, suffix, n = path.stem, path.suffix, 2
    while True:
        candidate = path.with_name(f"{stem}-{n}{suffix}")
        if not candidate.exists():
            return candidate
        n += 1


def extract(zip_path: Path, dest: Path, flat: bool, overwrite: bool) -> list[Path]:
    written: list[Path] = []
    with zipfile.ZipFile(zip_path) as zf:
        for info in zf.infolist():
            if info.is_dir():
                continue
            name = decode_name(info)
            rel = Path(name)
            if rel.suffix.lower() not in MD_SUFFIXES:
                continue
            if not is_safe(rel):
                print(f"  ATLANDI (guvensiz yol): {name}", file=sys.stderr)
                continue
            target = dest / (rel.name if flat else rel)
            resolved = target.resolve()
            if not str(resolved).startswith(str(dest.resolve())):
                print(f"  ATLANDI (dest disi): {name}", file=sys.stderr)
                continue
            if not overwrite:
                target = unique_path(target)
            target.parent.mkdir(parents=True, exist_ok=True)
            with zf.open(info) as src:
                data = src.read()
            target.write_bytes(data)
            written.append(target)
    return written


def git(*args: str) -> int:
    return subprocess.call(["git", "-C", str(REPO), *args])


def main() -> int:
    ap = argparse.ArgumentParser(description="ZIP icinden .md dosyalarini cikar")
    ap.add_argument("zips", nargs="*", type=Path, help="zip dosyalari")
    ap.add_argument("--all", action="store_true", help="incoming/ altindaki tum ziplar")
    ap.add_argument("--dest", type=Path, default=None, help="hedef klasor (varsayilan docs/<zip-adi>)")
    ap.add_argument("--flat", action="store_true", help="klasor yapisini duzlestir")
    ap.add_argument("--overwrite", action="store_true", help="ayni isimde dosyanin uzerine yaz")
    ap.add_argument("--commit", action="store_true", help="cikarilan dosyalari commit'le")
    args = ap.parse_args()

    zips = list(args.zips)
    if args.all:
        zips += sorted((REPO / "incoming").glob("*.zip"))
    if not zips:
        ap.error("zip dosyasi verilmedi (veya --all ile incoming/ bos)")

    total: list[Path] = []
    for zp in zips:
        zp = zp if zp.is_absolute() else REPO / zp
        if not zp.is_file():
            print(f"HATA: bulunamadi -> {zp}", file=sys.stderr)
            return 2
        if not zipfile.is_zipfile(zp):
            print(f"HATA: gecerli zip degil -> {zp}", file=sys.stderr)
            return 2
        dest = args.dest if args.dest else REPO / "docs" / zp.stem
        dest = dest if dest.is_absolute() else REPO / dest
        dest.mkdir(parents=True, exist_ok=True)
        print(f"\n{zp.name} -> {dest.relative_to(REPO)}")
        written = extract(zp, dest, args.flat, args.overwrite)
        for w in written:
            print(f"  + {w.relative_to(REPO)}")
        if not written:
            print("  (md dosyasi bulunamadi)")
        total += written

    print(f"\nToplam {len(total)} markdown dosyasi cikarildi.")

    if args.commit and total:
        git("add", "--", *[str(p.relative_to(REPO)) for p in total])
        names = ", ".join(sorted({Path(p).name for p in zips}))
        git("commit", "-m", f"docs: {names} icinden {len(total)} markdown dosyasi eklendi")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
