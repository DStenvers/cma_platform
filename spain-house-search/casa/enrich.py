"""Enrichment helpers that run after a listing is scraped.

Currently: elevation lookup (metres above sea level) from latitude/longitude,
plus the derived "how much cooler than the coast" figure that the frontend uses
to flag mountain houses.
"""
from __future__ import annotations

import json
import urllib.parse
import urllib.request

from . import config


def fetch_elevation(latitude: float, longitude: float) -> int | None:
    """Return metres above sea level for a coordinate, or None on any failure.

    Best-effort and fail-open: no network, a slow endpoint or a bad response
    never breaks a crawl — the listing is simply stored without elevation and can
    be enriched on a later run.
    """
    if not config.ELEVATION_ENABLED or latitude is None or longitude is None:
        return None
    query = urllib.parse.urlencode({"latitude": latitude, "longitude": longitude})
    url = f"{config.ELEVATION_API}?{query}"
    req = urllib.request.Request(url, headers={"User-Agent": config.USER_AGENT})
    try:
        with urllib.request.urlopen(req, timeout=config.REQUEST_TIMEOUT) as resp:
            payload = json.loads(resp.read().decode("utf-8"))
    except Exception:
        return None
    elevations = payload.get("elevation")
    if isinstance(elevations, list) and elevations:
        try:
            return int(round(float(elevations[0])))
        except (TypeError, ValueError):
            return None
    return None


def cooler_than_coast_c(elevation_m: int | None) -> float | None:
    """Approximate °C an inland/mountain house sits below sea-level air.

    Uses the standard environmental lapse rate (~0.65 °C per 100 m). Indicative
    only — a quick answer to "huizen in de bergen zijn veel koeler".
    """
    if not elevation_m or elevation_m <= 0:
        return None
    return round(elevation_m / 100.0 * config.LAPSE_RATE_C_PER_100M, 1)


def altitude_band(elevation_m: int | None) -> str:
    """A coarse label for the altitude, used for badges/filtering hints."""
    if elevation_m is None:
        return "onbekend"
    if elevation_m < 50:
        return "kust"
    if elevation_m < 300:
        return "laagland"
    if elevation_m < 700:
        return "heuvels"
    if elevation_m < 1200:
        return "bergen"
    return "hooggebergte"
