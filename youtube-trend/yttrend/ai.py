"""Turn the collected trend data into a content plan with Claude."""

from __future__ import annotations

import json

import anthropic

MODEL = "claude-opus-5-5"

SYSTEM = """You are a YouTube strategist for a new English-language channel run by one person \
who produces videos with AI tools (scripts by Claude, visuals by AI video/animation tools). \
You receive real YouTube Data API numbers for one or more niches. Base every claim on those numbers \
and say when the data is too thin to conclude something.

Keep in mind YouTube's monetization policy against mass-produced, repetitive ("inauthentic") content: \
recommend formats with recurring characters, a real narrative or real information, and an identifiable \
channel voice, not templated filler.

Write the analysis in Turkish. Write video titles, hooks and thumbnail text in English."""

TASK = """Data:
```json
{data}
```

Produce a Markdown report with these sections:
1. **Trend özeti** - what is working right now and why (cite the numbers).
2. **Fırsat** - which niche/angle a new channel can realistically break into, and which to avoid.
3. **Başlık ve thumbnail kalıpları** - patterns visible in the winning titles.
4. **{n} video fikri** - a table: English title, first-3-second hook, format (Short/long, length), \
thumbnail text, which data point it is based on.
5. **İlk 30 gün planı** - posting cadence and what to measure."""


def generate_ideas(data: dict, n_ideas: int = 10, client: anthropic.Anthropic | None = None) -> str:
    client = client or anthropic.Anthropic()
    prompt = TASK.format(data=json.dumps(data, ensure_ascii=False, default=str), n=n_ideas)
    with client.beta.messages.stream(
        model=MODEL,
        max_tokens=32000,
        system=SYSTEM,
        thinking={"type": "adaptive"},
        output_config={"effort": "high"},
        betas=["server-side-fallback-2026-07-01"],
        fallbacks="default",
        messages=[{"role": "user", "content": prompt}],
    ) as stream:
        message = stream.get_final_message()
    if message.stop_reason == "refusal":
        raise RuntimeError("Claude isteği reddetti; veriyi kontrol edip tekrar deneyin.")
    return "".join(b.text for b in message.content if b.type == "text")
