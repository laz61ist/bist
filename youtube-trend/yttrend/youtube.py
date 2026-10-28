"""Thin YouTube Data API v3 client with quota accounting."""

from __future__ import annotations

from datetime import datetime, timedelta, timezone

import requests

from .metrics import Video, parse_duration

API_URL = "https://www.googleapis.com/youtube/v3"

# Unit cost per call (https://developers.google.com/youtube/v3/determine_quota_cost)
COST = {"search": 100, "videos": 1, "channels": 1, "videoCategories": 1}
DAILY_QUOTA = 10_000


class QuotaExceeded(RuntimeError):
    pass


class YouTubeClient:
    def __init__(self, api_key: str, session: requests.Session | None = None):
        if not api_key:
            raise ValueError("YOUTUBE_API_KEY tanımlı değil (.env dosyasına ekleyin)")
        self.api_key = api_key
        self.session = session or requests.Session()
        self.quota_used = 0

    def _get(self, endpoint: str, **params) -> dict:
        params = {k: v for k, v in params.items() if v is not None}
        params["key"] = self.api_key
        resp = self.session.get(f"{API_URL}/{endpoint}", params=params, timeout=30)
        self.quota_used += COST.get(endpoint, 1)
        if resp.status_code == 403 and "quota" in resp.text.lower():
            raise QuotaExceeded("Günlük YouTube API kotası doldu (10.000 birim). Yarın tekrar deneyin.")
        resp.raise_for_status()
        return resp.json()

    def categories(self, region: str = "US") -> dict[str, str]:
        data = self._get("videoCategories", part="snippet", regionCode=region)
        return {item["id"]: item["snippet"]["title"] for item in data.get("items", [])}

    def most_popular(self, region: str = "US", category_id: str | None = None, limit: int = 50) -> list[Video]:
        videos: list[Video] = []
        page_token = None
        while len(videos) < limit:
            data = self._get(
                "videos",
                part="snippet,statistics,contentDetails",
                chart="mostPopular",
                regionCode=region,
                videoCategoryId=category_id,
                maxResults=min(50, limit - len(videos)),
                pageToken=page_token,
            )
            videos += [_to_video(item) for item in data.get("items", [])]
            page_token = data.get("nextPageToken")
            if not page_token:
                break
        return videos

    def search_ids(
        self,
        query: str,
        region: str = "US",
        days: int = 30,
        order: str = "viewCount",
        duration: str | None = None,
        limit: int = 50,
    ) -> list[str]:
        """Video ids for a query published in the last `days`. Costs 100 units per page."""
        published_after = (datetime.now(timezone.utc) - timedelta(days=days)).strftime("%Y-%m-%dT%H:%M:%SZ")
        data = self._get(
            "search",
            part="id",
            q=query,
            type="video",
            order=order,
            regionCode=region,
            relevanceLanguage="en",
            publishedAfter=published_after,
            videoDuration=duration,
            maxResults=min(50, limit),
        )
        return [item["id"]["videoId"] for item in data.get("items", [])]

    def videos(self, ids: list[str]) -> list[Video]:
        out: list[Video] = []
        for i in range(0, len(ids), 50):
            data = self._get("videos", part="snippet,statistics,contentDetails", id=",".join(ids[i : i + 50]))
            out += [_to_video(item) for item in data.get("items", [])]
        return out

    def attach_subscribers(self, videos: list[Video]) -> None:
        ids = sorted({v.channel_id for v in videos})
        subs: dict[str, int | None] = {}
        for i in range(0, len(ids), 50):
            data = self._get("channels", part="statistics", id=",".join(ids[i : i + 50]))
            for item in data.get("items", []):
                stats = item.get("statistics", {})
                subs[item["id"]] = None if stats.get("hiddenSubscriberCount") else int(stats.get("subscriberCount", 0))
        for v in videos:
            v.subscribers = subs.get(v.channel_id)


def _to_video(item: dict) -> Video:
    sn, st = item.get("snippet", {}), item.get("statistics", {})
    return Video(
        id=item["id"],
        title=sn.get("title", ""),
        channel_id=sn.get("channelId", ""),
        channel_title=sn.get("channelTitle", ""),
        published_at=datetime.fromisoformat(sn["publishedAt"].replace("Z", "+00:00")),
        duration_s=parse_duration(item.get("contentDetails", {}).get("duration", "")),
        views=int(st.get("viewCount", 0)),
        likes=int(st.get("likeCount", 0)),
        comments=int(st.get("commentCount", 0)),
        tags=sn.get("tags", []),
        category_id=sn.get("categoryId", ""),
    )
