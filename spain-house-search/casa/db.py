"""SQLite connection handling and schema bootstrap."""
from __future__ import annotations

import sqlite3
from pathlib import Path

from . import config


def connect(db_path: Path | str | None = None) -> sqlite3.Connection:
    """Open a connection with sane defaults (row objects, FK enforcement, WAL)."""
    config.ensure_data_dir()
    path = Path(db_path) if db_path else config.DB_PATH
    conn = sqlite3.connect(str(path), timeout=30)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    conn.execute("PRAGMA journal_mode = WAL")
    conn.execute("PRAGMA busy_timeout = 5000")
    return conn


def init_db(db_path: Path | str | None = None) -> None:
    """Create tables from schema.sql if they do not yet exist (idempotent)."""
    sql = config.SCHEMA_PATH.read_text(encoding="utf-8")
    conn = connect(db_path)
    try:
        conn.executescript(sql)
        conn.commit()
    finally:
        conn.close()
