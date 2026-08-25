#!/usr/bin/env python3
"""zip_to_md yardimci fonksiyonlari icin hizli testler: python3 tools/test_zip_to_md.py"""
import sys, zipfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from zip_to_md import decode_name, is_safe, resolve_target  # noqa: E402


def zi(name: str, utf8_flag: bool) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(name)
    info.flag_bits = (info.flag_bits | 0x800) if utf8_flag else (info.flag_bits & ~0x800)
    return info


def main() -> int:
    fails = []

    def eq(got, want, label):
        if got != want:
            fails.append(f"{label}: beklenen {want!r}, gelen {got!r}")

    # UTF-8 bayrakli ad oldugu gibi kalir
    eq(decode_name(zi("Şartname_ĞÜİ.md", True)), "Şartname_ĞÜİ.md", "utf8-flagged")
    # Bayraksiz + gercekte UTF-8 baytlari (Linux zip)
    eq(decode_name(zi("Rapor_Özet.md".encode("utf-8").decode("cp437"), False)),
       "Rapor_Özet.md", "no-flag/utf8-bytes")
    # Bayraksiz + cp1254 baytlari (eski Windows zip)
    eq(decode_name(zi("Rapor_Özet.md".encode("cp1254").decode("cp437"), False)),
       "Rapor_Özet.md", "no-flag/cp1254-bytes")
    # Saf ASCII her iki durumda da bozulmaz
    eq(decode_name(zi("plain.md", False)), "plain.md", "ascii-no-flag")

    # zip-slip / junk korumasi
    for bad in ("../evil.md", "a/../../evil.md", "/abs/evil.md", "__MACOSX/._x.md", ".git/config.md"):
        if is_safe(Path(bad)):
            fails.append(f"is_safe: {bad!r} guvenli sayildi (olmamali)")
    for good in ("a.md", "alt/b.md", "a/b/c.md"):
        if not is_safe(Path(good)):
            fails.append(f"is_safe: {good!r} reddedildi (kabul edilmeliydi)")

    # Ayni isim + AYNI icerik -> atla (kopya uretme)
    import tempfile
    with tempfile.TemporaryDirectory() as td:
        d = Path(td)
        (d / "a.md").write_text("ayni icerik", encoding="utf-8")
        got = resolve_target(d / "a.md", b"ayni icerik", overwrite=False)
        if got is not None:
            fails.append(f"resolve_target: ayni icerik atlanmaliydi, {got!r} dondu")

        # Ayni isim + FARKLI icerik -> yeni ad uret
        got = resolve_target(d / "a.md", b"baska icerik", overwrite=False)
        if got != d / "a-2.md":
            fails.append(f"resolve_target: farkli icerikte a-2.md beklenirdi, {got!r} dondu")

        # Dosya yoksa kendi yolunu dondur
        got = resolve_target(d / "yeni.md", b"x", overwrite=False)
        if got != d / "yeni.md":
            fails.append(f"resolve_target: yeni dosyada kendi yolu beklenirdi, {got!r} dondu")

        # overwrite=True -> ayni yolu dondur
        got = resolve_target(d / "a.md", b"baska icerik", overwrite=True)
        if got != d / "a.md":
            fails.append(f"resolve_target: overwrite'ta ayni yol beklenirdi, {got!r} dondu")

    if fails:
        print("BASARISIZ:")
        for f in fails:
            print("  -", f)
        return 1
    print("Tum testler gecti.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
