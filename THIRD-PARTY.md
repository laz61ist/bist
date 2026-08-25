# Üçüncü taraf içerik ve lisanslar

Bu depo, başka projelerden alınan (vendored) skill ve şablon dosyaları içerir.
Her birinin lisans metni `licenses/` altındadır.

## 1. BIST sistem paketi — 5 skill + proje talimatı

| Alan | Değer |
|---|---|
| Kaynak | Eren Gül Aydın tarafından hazırlanan "Borsa İstanbul Analiz Sistemi" paketi |
| Uyarlandığı proje | [anthropics/financial-services](https://github.com/anthropics/financial-services) `market-researcher` ajanı |
| Lisans | Apache License 2.0 — `licenses/BIST-PAKETI-Apache-2.0.txt` |
| Depodaki yeri | `.claude/skills/bist-*/SKILL.md`, `docs/bist-sistem/` |

Uyarlama kapsamı: Borsa İstanbul veri kaynakları, TMS 29 enflasyon düzeltmesi
kontrolü, TFRS terminolojisi, likidite filtresi, Türkçe çıktı.

## 2. GitHub Spec Kit

| Alan | Değer |
|---|---|
| Kaynak | [github/spec-kit](https://github.com/github/spec-kit) |
| Telif | Copyright GitHub, Inc. |
| Lisans | MIT — `licenses/spec-kit-MIT.txt` |
| Depodaki yeri | `.claude/skills/speckit-*/`, `.specify/` |

Dosyalar elle kopyalanmadı; deponun kendi CLI'ı ile üretildi:

```bash
specify init --here --force --non-interactive --integration claude --script sh
```

## 3. Superpowers

| Alan | Değer |
|---|---|
| Kaynak | [obra/superpowers](https://github.com/obra/superpowers) |
| Telif | Copyright (c) 2025 Jesse Vincent |
| Lisans | MIT — `licenses/superpowers-MIT.txt` |
| Sürüm | 6.3.0 |
| Depodaki yeri | `.claude/skills/` altındaki 14 skill |

Kopyalanan skill'ler: brainstorming, dispatching-parallel-agents, executing-plans,
finishing-a-development-branch, receiving-code-review, requesting-code-review,
subagent-driven-development, systematic-debugging, test-driven-development,
using-git-worktrees, using-superpowers, verification-before-completion,
writing-plans, writing-skills.

## Güncelleme

Vendor edilen içerik üst kaynaktan otomatik güncellenmez. Yenilemek için:

```bash
# spec-kit
specify init --here --force --non-interactive --integration claude --script sh

# superpowers
git clone --depth 1 https://github.com/obra/superpowers /tmp/sp
cp -r /tmp/sp/skills/. .claude/skills/

# BIST skill'leri (docs/bist-sistem güncellendikten sonra)
python3 tools/install_bist_skills.py
```
