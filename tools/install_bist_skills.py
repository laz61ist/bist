#!/usr/bin/env python3
"""docs/bist-sistem/*-SKILL.md dosyalarini .claude/skills/<name>/SKILL.md olarak kurar.

Skill adi dosya adindan degil, YAML frontmatter'daki `name` alanindan alinir.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
SRC = REPO / "docs" / "bist-sistem"
DEST = REPO / ".claude" / "skills"
NAME_RE = re.compile(r"^name:\s*(.+?)\s*$", re.MULTILINE)


def skill_name(text: str) -> str | None:
    if not text.startswith("---"):
        return None
    lines = text.splitlines(keepends=True)
    close = next((i for i in range(1, len(lines)) if lines[i].rstrip() == "---"), None)
    if close is None:
        return None
    m = NAME_RE.search("".join(lines[1:close]))
    return m.group(1).strip().strip('"').strip("'") if m else None


def main() -> int:
    sources = sorted(SRC.glob("*-SKILL.md"))
    if not sources:
        print(f"HATA: {SRC} altinda *-SKILL.md yok", file=sys.stderr)
        return 2
    for src in sources:
        text = src.read_text(encoding="utf-8")
        name = skill_name(text)
        if not name:
            print(f"HATA: {src.name} icinde frontmatter `name` yok", file=sys.stderr)
            return 2
        target = DEST / name / "SKILL.md"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(text, encoding="utf-8")
        print(f"  + .claude/skills/{name}/SKILL.md   <- {src.name}")
    print(f"\n{len(sources)} BIST skill kuruldu.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
