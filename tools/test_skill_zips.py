#!/usr/bin/env python3
"""claude.ai arayuzune yuklenebilir skill zip'leri icin testler."""
import sys
import tempfile
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "tools"))

from make_skill_zips import build_zips  # noqa: E402


def main() -> int:
    fails = []

    def check(cond, label):
        if not cond:
            fails.append(label)

    with tempfile.TemporaryDirectory() as td:
        src = Path(td) / "skills"
        (src / "bist-ornek").mkdir(parents=True)
        (src / "bist-ornek" / "SKILL.md").write_text(
            "---\nname: bist-ornek\ndescription: test\n---\n\n# Ornek\n", encoding="utf-8")
        # yardimci dosyali skill: zip'e SADECE SKILL.md girmeli
        (src / "bist-ikinci").mkdir()
        (src / "bist-ikinci" / "SKILL.md").write_text(
            "---\nname: bist-ikinci\ndescription: test2\n---\n\n# Iki\n", encoding="utf-8")
        (src / "bist-ikinci" / "yardimci.md").write_text("ek", encoding="utf-8")
        # onek eslesmeyen skill atlanmali
        (src / "baska-skill").mkdir()
        (src / "baska-skill" / "SKILL.md").write_text(
            "---\nname: baska-skill\ndescription: x\n---\n", encoding="utf-8")

        out = Path(td) / "dist"
        made = build_zips(src, out, prefix="bist-")

        check(len(made) == 2, f"2 zip beklenirdi, {len(made)} uretildi")
        check((out / "bist-ornek.zip").is_file(), "bist-ornek.zip yok")
        check(not (out / "baska-skill.zip").exists(), "onek disi skill zip'lendi")

        with zipfile.ZipFile(out / "bist-ikinci.zip") as z:
            names = z.namelist()
            check(names == ["SKILL.md"], f"zip icinde sadece SKILL.md olmali, var: {names}")
            check("/" not in names[0], "zip icinde klasor var")
            body = z.read("SKILL.md").decode("utf-8")
            check("name: bist-ikinci" in body, "SKILL.md icerigi bozulmus")

        # idempotent: ikinci calistirma ayni sonucu vermeli
        made2 = build_zips(src, out, prefix="bist-")
        check(len(made2) == 2, "ikinci calistirmada zip sayisi degisti")

    if fails:
        print("BASARISIZ:")
        for f in fails:
            print("  -", f)
        return 1
    print("Tum testler gecti.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
