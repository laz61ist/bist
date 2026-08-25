#!/usr/bin/env python3
"""claude.ai arayuzune yuklenebilir skill zip'leri uretir.

Kurulum rehberi ADIM 2: "Zip'in icinde tek SKILL.md olmali, klasor olmamali."
Bu arac her skill icin tam olarak bunu uretir.

Kullanim:
    python3 tools/make_skill_zips.py                  # bist-* skill'leri -> dist/
    python3 tools/make_skill_zips.py --prefix speckit-
    python3 tools/make_skill_zips.py --prefix ""      # tum skill'ler
"""
from __future__ import annotations

import argparse
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent


def build_zips(src: Path, out: Path, prefix: str = "bist-") -> list[Path]:
    """src altindaki her <prefix>* skill klasoru icin out/<ad>.zip uret.

    Zip icinde SADECE SKILL.md bulunur, klasor girisi olmaz; arayuz
    yalnizca bu yapiyi kabul ediyor.
    """
    out.mkdir(parents=True, exist_ok=True)
    made: list[Path] = []
    for skill_dir in sorted(p for p in src.iterdir() if p.is_dir()):
        if prefix and not skill_dir.name.startswith(prefix):
            continue
        skill_md = skill_dir / "SKILL.md"
        if not skill_md.is_file():
            continue
        target = out / f"{skill_dir.name}.zip"
        with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as z:
            z.writestr("SKILL.md", skill_md.read_text(encoding="utf-8"))
        made.append(target)
    return made


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", type=Path, default=REPO / ".claude" / "skills")
    ap.add_argument("--out", type=Path, default=REPO / "dist")
    ap.add_argument("--prefix", default="bist-")
    args = ap.parse_args()
    made = build_zips(args.src, args.out, args.prefix)
    for m in made:
        print(f"  + {m.relative_to(REPO)}")
    if not made:
        print(f"  ({args.prefix}* onekli skill bulunamadi)")
    print(f"\n{len(made)} zip uretildi -> {args.out.relative_to(REPO)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
