"""Central configuration for Casa en España.

All settings can be overridden with environment variables so the same code runs
locally, in a container, or from a cron job without edits.
"""
from __future__ import annotations

import os
from pathlib import Path

PACKAGE_DIR = Path(__file__).resolve().parent
PROJECT_DIR = PACKAGE_DIR.parent
DATA_DIR = Path(os.environ.get("CASA_DATA_DIR", PROJECT_DIR / "data"))

DB_PATH = Path(os.environ.get("CASA_DB", DATA_DIR / "casa.sqlite3"))
SCHEMA_PATH = PACKAGE_DIR / "schema.sql"

# HTTP behaviour for the crawler. Be a polite citizen by default.
USER_AGENT = os.environ.get(
    "CASA_USER_AGENT",
    "CasaEnEspana/0.1 (+personal house-hunting bot; respects robots.txt)",
)
REQUEST_TIMEOUT = float(os.environ.get("CASA_HTTP_TIMEOUT", "20"))
CRAWL_DELAY = float(os.environ.get("CASA_CRAWL_DELAY", "1.5"))  # seconds between requests
RESPECT_ROBOTS = os.environ.get("CASA_RESPECT_ROBOTS", "1") != "0"

# Elevation enrichment (metres above sea level). Open-Meteo needs no API key.
ELEVATION_API = os.environ.get(
    "CASA_ELEVATION_API",
    "https://api.open-meteo.com/v1/elevation",
)
ELEVATION_ENABLED = os.environ.get("CASA_ELEVATION", "1") != "0"

# Web server.
WEB_HOST = os.environ.get("CASA_HOST", "127.0.0.1")
WEB_PORT = int(os.environ.get("CASA_PORT", "8000"))

# Environmental lapse rate: air cools ~0.65 °C per 100 m of altitude. Used to
# turn an elevation into the "koeler in de bergen" indication on the frontend.
LAPSE_RATE_C_PER_100M = 0.65


def ensure_data_dir() -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
