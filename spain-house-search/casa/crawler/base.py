"""Base machinery every spider builds on.

A spider's only job is to yield normalised listing dicts (see FIELD REFERENCE
below). This module gives it a polite HTTP client — shared session, honest
User-Agent, per-host robots.txt enforcement and a configurable crawl delay — so
individual spiders stay small and can't accidentally hammer a site.

FIELD REFERENCE — keys a spider may put in a listing dict
---------------------------------------------------------
Required:  url
Common:    title, description, price, property_type, transaction_type,
           municipality, province, region, postal_code, latitude, longitude,
           bedrooms, bathrooms, built_area_m2, plot_area_m2, year_built,
           and any boolean feature column (pool, garden, open_kitchen, ...).
Media:     photos = [url, ...] or [{"url":..,"caption":..}, ...]
           main_photo_url is auto-filled from photos[0] if omitted.
Extra:     features = {key: value, ...}  — free-form, stored searchably
           raw = {...}                   — full original payload, stored verbatim
Elevation is filled in automatically from latitude/longitude — spiders need not.
"""
from __future__ import annotations

import time
import urllib.request
import urllib.robotparser
from typing import Iterator
from urllib.parse import urlsplit

from .. import config


class Spider:
    """Subclass this, set slug/name/base_url, implement crawl()."""

    slug: str = ""
    name: str = ""
    base_url: str = ""

    def __init__(self) -> None:
        if not self.slug:
            raise ValueError(f"{type(self).__name__} must define a slug")
        self._last_request_at = 0.0
        self._robots: dict[str, urllib.robotparser.RobotFileParser] = {}

    # -- to be implemented by concrete spiders --------------------------------
    def crawl(self) -> Iterator[dict]:
        """Yield listing dicts. Override in a subclass."""
        raise NotImplementedError

    # -- polite HTTP ----------------------------------------------------------
    def http_get(self, url: str) -> str:
        """Fetch a URL as text, honouring robots.txt and the crawl delay."""
        if config.RESPECT_ROBOTS and not self._allowed(url):
            raise PermissionError(f"robots.txt disallows {url}")
        self._throttle()
        req = urllib.request.Request(url, headers={"User-Agent": config.USER_AGENT})
        with urllib.request.urlopen(req, timeout=config.REQUEST_TIMEOUT) as resp:
            charset = resp.headers.get_content_charset() or "utf-8"
            return resp.read().decode(charset, errors="replace")

    def _throttle(self) -> None:
        elapsed = time.monotonic() - self._last_request_at
        wait = config.CRAWL_DELAY - elapsed
        if wait > 0:
            time.sleep(wait)
        self._last_request_at = time.monotonic()

    def _allowed(self, url: str) -> bool:
        parts = urlsplit(url)
        host = f"{parts.scheme}://{parts.netloc}"
        parser = self._robots.get(host)
        if parser is None:
            parser = urllib.robotparser.RobotFileParser()
            parser.set_url(f"{host}/robots.txt")
            try:
                parser.read()
            except Exception:
                # If robots.txt can't be read, default to allow but stay polite
                # via the crawl delay. Sites that want to block will 403 anyway.
                parser = None
            self._robots[host] = parser  # type: ignore[assignment]
        return True if parser is None else parser.can_fetch(config.USER_AGENT, url)
