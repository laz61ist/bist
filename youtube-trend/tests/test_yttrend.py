from datetime import datetime, timedelta, timezone

import pytest

from yttrend import analysis, cli
from yttrend.metrics import Video, parse_duration
from yttrend.store import Store
from yttrend.youtube import YouTubeClient

NOW = datetime(2026, 10, 1, tzinfo=timezone.utc)


def make(id="v", views=10_000, subs=1_000, days=2, dur=45, title="Cute Bear Story", likes=500, comments=50):
    return Video(id=id, title=title, channel_id=f"c-{id}", channel_title=f"Ch {id}",
                 published_at=NOW - timedelta(days=days), duration_s=dur, views=views,
                 likes=likes, comments=comments, subscribers=subs)


@pytest.mark.parametrize("iso,sec", [("PT45S", 45), ("PT1M5S", 65), ("PT1H2M3S", 3723), ("P1DT1S", 86401), ("", 0), ("bad", 0)])
def test_parse_duration(iso, sec):
    assert parse_duration(iso) == sec


def test_video_metrics():
    v = make(views=10_000, subs=1_000, days=2)
    assert v.views_per_day(NOW) == 5_000
    assert v.outlier == 10
    assert v.engagement == pytest.approx(0.055)
    assert v.is_short
    assert not make(dur=600).is_short
    assert make(subs=None).outlier is None


def test_new_upload_rate_is_floored_at_one_hour():
    v = make(views=1000, days=0)
    assert v.views_per_day(NOW) == pytest.approx(24_000)


def test_niche_summary_rewards_small_channel_breakouts():
    small = [make(id=str(i), subs=5_000) for i in range(5)]
    big = [make(id=str(i), subs=5_000_000) for i in range(5)]
    s_small = analysis.niche_summary(small, NOW)
    s_big = analysis.niche_summary(big, NOW)
    assert s_small["small_channel_share"] == 1.0 and s_big["small_channel_share"] == 0.0
    assert s_small["opportunity"] > s_big["opportunity"]
    assert analysis.niche_summary([], NOW) == {"videos": 0, "opportunity": 0.0}


def test_top_outliers_and_patterns():
    vids = [make(id="a", views=100, subs=1000), make(id="b", views=90_000, subs=1000, title="TOP 5 Bears? 🐻"),
            make(id="c", views=900_000, subs=900_000)]
    assert [v.id for v in analysis.top_outliers(vids, 2)] == ["b", "c"]
    assert [v.id for v in analysis.top_outliers(vids, 5, small_only=True)] == ["b", "a"]
    p = analysis.title_patterns(vids)
    assert ("bears", 1) in p["top_words"] and p["question_pct"] == 33 and p["emoji_pct"] == 33


def test_store_growth(tmp_path):
    st = Store(tmp_path / "t.db")
    v = make(id="x", views=1000)
    st.save([v], "kids", at=NOW)
    v.views = 3000
    st.save([v], "kids", at=NOW + timedelta(hours=12))
    [row] = st.growth("kids")
    assert row["views_gained"] == 2000 and row["gain_per_day"] == 4000


class FakeResp:
    def __init__(self, data, status=200):
        self._data, self.status_code, self.text = data, status, ""

    def json(self):
        return self._data

    def raise_for_status(self):
        pass


class FakeSession:
    def __init__(self):
        self.calls = []

    def get(self, url, params, timeout):
        endpoint = url.rsplit("/", 1)[1]
        self.calls.append(endpoint)
        if endpoint == "search":
            return FakeResp({"items": [{"id": {"videoId": "v1"}}, {"id": {"videoId": "v2"}}]})
        if endpoint == "videos":
            return FakeResp({"items": [
                {"id": vid, "snippet": {"title": f"Video {vid}", "channelId": "ch1", "channelTitle": "Ch",
                                        "publishedAt": "2026-09-30T00:00:00Z", "categoryId": "1"},
                 "statistics": {"viewCount": "5000", "likeCount": "100"},
                 "contentDetails": {"duration": "PT50S"}} for vid in params["id"].split(",")]})
        if endpoint == "channels":
            return FakeResp({"items": [{"id": "ch1", "statistics": {"subscriberCount": "2000"}}]})
        raise AssertionError(endpoint)


def test_collect_niche_with_fake_api():
    yt = YouTubeClient("key", session=FakeSession())
    videos = cli.collect_niche(yt, "kids", "US", 30, None)
    assert [v.id for v in videos] == ["v1", "v2"]
    assert videos[0].subscribers == 2000 and videos[0].duration_s == 50
    assert yt.quota_used == 102


def test_niche_command_writes_report(tmp_path, monkeypatch):
    monkeypatch.setattr(cli, "OUTPUT", tmp_path / "out")
    monkeypatch.setattr(cli, "DB", tmp_path / "db.sqlite")
    yt = YouTubeClient("key", session=FakeSession())
    args = type("A", (), {"query": "kids stories", "region": "US", "days": 30, "duration": None, "ai": False, "ideas": 10})
    cli.cmd_niche(yt, args)
    [md] = list((tmp_path / "out").glob("*.md"))
    text = md.read_text()
    assert "Niş analizi - kids stories" in text and "Video v1" in text
