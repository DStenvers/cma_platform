"""Data-access layer: turning crawled listings into rows and querying them back.

Everything that touches the database lives here so the crawler, the CLI and the
web server all share one definition of "how a listing is stored and searched".
"""
from __future__ import annotations

import json
import sqlite3
from typing import Any, Iterable

# Typed feature/attribute columns a spider may set directly on `listings`.
# Anything a spider returns outside this set is preserved in raw_json and, when
# scalar, mirrored into the searchable `listing_features` key/value table.
LISTING_FIELDS = (
    "source_ref", "status", "title", "description", "transaction_type",
    "property_type", "price", "currency", "price_per_m2",
    "address", "municipality", "province", "region", "postal_code", "country",
    "latitude", "longitude", "elevation_m",
    "bedrooms", "bathrooms", "built_area_m2", "plot_area_m2", "terrace_area_m2",
    "year_built", "floor", "total_floors", "condition", "energy_rating",
    "dual_occupancy", "garden", "garden_trees", "garden_plants", "open_kitchen",
    "pool", "pool_size_m2", "pool_private", "terrace", "balcony", "garage",
    "parking_spaces", "storage_room", "air_conditioning", "heating",
    "heating_type", "fireplace", "elevator", "furnished", "solar_panels",
    "sea_view", "mountain_view", "sea_front", "guest_toilet", "utility_room",
    "accessible", "main_photo_url",
)

# Boolean feature columns exposed as toggle filters in the UI, with labels.
FEATURE_FILTERS = [
    ("dual_occupancy", "Dubbele bewoning"),
    ("pool", "Zwembad"),
    ("garden", "Tuin"),
    ("garden_trees", "Tuin met bomen"),
    ("garden_plants", "Tuin met planten"),
    ("open_kitchen", "Open keuken"),
    ("terrace", "Terras"),
    ("balcony", "Balkon"),
    ("garage", "Garage"),
    ("storage_room", "Berging"),
    ("air_conditioning", "Airconditioning"),
    ("heating", "Verwarming"),
    ("fireplace", "Open haard"),
    ("elevator", "Lift"),
    ("furnished", "Gemeubileerd"),
    ("solar_panels", "Zonnepanelen"),
    ("sea_view", "Zeezicht"),
    ("mountain_view", "Bergzicht"),
    ("sea_front", "Eerste lijn zee"),
    ("guest_toilet", "Gastentoilet"),
    ("utility_room", "Bijkeuken"),
    ("accessible", "Rolstoeltoegankelijk"),
]


# ---------------------------------------------------------------------------
# Sources & crawl runs
# ---------------------------------------------------------------------------
def upsert_source(conn: sqlite3.Connection, slug: str, name: str, base_url: str = "") -> int:
    conn.execute(
        "INSERT INTO sources(slug, name, base_url) VALUES(?,?,?) "
        "ON CONFLICT(slug) DO UPDATE SET name=excluded.name, base_url=excluded.base_url",
        (slug, name, base_url),
    )
    row = conn.execute("SELECT id FROM sources WHERE slug=?", (slug,)).fetchone()
    return int(row["id"])


def start_run(conn: sqlite3.Connection, source_id: int) -> int:
    cur = conn.execute("INSERT INTO crawl_runs(source_id) VALUES(?)", (source_id,))
    conn.commit()
    return int(cur.lastrowid)


def finish_run(conn: sqlite3.Connection, run_id: int, **counters: Any) -> None:
    fields = ["finished_at = datetime('now')", "status = :status"]
    params: dict[str, Any] = {"status": counters.pop("status", "ok"), "id": run_id}
    for key, value in counters.items():
        fields.append(f"{key} = :{key}")
        params[key] = value
    conn.execute(f"UPDATE crawl_runs SET {', '.join(fields)} WHERE id = :id", params)
    conn.commit()


# ---------------------------------------------------------------------------
# Listing upsert
# ---------------------------------------------------------------------------
def upsert_listing(conn: sqlite3.Connection, source_id: int, data: dict[str, Any]) -> tuple[int, bool]:
    """Insert or update one listing keyed on its URL. Returns (listing_id, is_new)."""
    url = data.get("url")
    if not url:
        raise ValueError("listing has no url")

    existing = conn.execute(
        "SELECT id, price FROM listings WHERE url = ?", (url,)
    ).fetchone()

    columns = {k: data.get(k) for k in LISTING_FIELDS if k in data}
    columns["raw_json"] = json.dumps(data.get("raw", data), ensure_ascii=False, default=str)

    if existing is None:
        cols = ["source_id", "url", *columns.keys()]
        placeholders = ", ".join("?" for _ in cols)
        values = [source_id, url, *columns.values()]
        cur = conn.execute(
            f"INSERT INTO listings ({', '.join(cols)}) VALUES ({placeholders})",
            values,
        )
        listing_id = int(cur.lastrowid)
        is_new = True
    else:
        listing_id = int(existing["id"])
        is_new = False
        assignments = ["last_seen = datetime('now')", "updated_at = datetime('now')",
                       "status = 'active'", "sold_detected_at = NULL"]
        values = []
        for key, value in columns.items():
            assignments.append(f"{key} = ?")
            values.append(value)
        values.append(listing_id)
        conn.execute(
            f"UPDATE listings SET {', '.join(assignments)} WHERE id = ?", values
        )

    _save_photos(conn, listing_id, data.get("photos", []))
    _save_features(conn, listing_id, data.get("features", {}))
    _record_price(conn, listing_id, columns.get("price"),
                  None if is_new else existing["price"])
    return listing_id, is_new


def _save_photos(conn: sqlite3.Connection, listing_id: int, photos: Iterable[Any]) -> None:
    for position, photo in enumerate(photos):
        url = photo if isinstance(photo, str) else photo.get("url")
        caption = None if isinstance(photo, str) else photo.get("caption")
        if not url:
            continue
        conn.execute(
            "INSERT INTO photos(listing_id, url, position, caption) VALUES(?,?,?,?) "
            "ON CONFLICT(listing_id, url) DO UPDATE SET position=excluded.position",
            (listing_id, url, position, caption),
        )


def _save_features(conn: sqlite3.Connection, listing_id: int, features: dict[str, Any]) -> None:
    for key, value in (features or {}).items():
        conn.execute(
            "INSERT INTO listing_features(listing_id, key, value) VALUES(?,?,?) "
            "ON CONFLICT(listing_id, key) DO UPDATE SET value=excluded.value",
            (listing_id, str(key), None if value is None else str(value)),
        )


def _record_price(conn: sqlite3.Connection, listing_id: int, new_price, old_price) -> None:
    if new_price is None:
        return
    if old_price is None or int(old_price) != int(new_price):
        conn.execute(
            "INSERT INTO price_history(listing_id, price) VALUES(?,?)",
            (listing_id, int(new_price)),
        )


def mark_missing_as_sold(conn: sqlite3.Connection, source_id: int, seen_urls: set[str]) -> int:
    """Flip active listings of this source that were NOT seen this run to 'sold'.

    This is the sold-detection heart of the update process: a house that used to
    be on the site but no longer appears has almost certainly been sold or pulled.
    """
    rows = conn.execute(
        "SELECT id, url FROM listings WHERE source_id = ? AND status = 'active'",
        (source_id,),
    ).fetchall()
    stale = [row["id"] for row in rows if row["url"] not in seen_urls]
    for listing_id in stale:
        conn.execute(
            "UPDATE listings SET status='sold', sold_detected_at=datetime('now'), "
            "updated_at=datetime('now') WHERE id = ?",
            (listing_id,),
        )
    return len(stale)


# ---------------------------------------------------------------------------
# Search
# ---------------------------------------------------------------------------
def search_listings(conn: sqlite3.Connection, filters: dict[str, Any],
                    limit: int = 60, offset: int = 0) -> list[sqlite3.Row]:
    where, params = _build_where(filters)
    order = _order_clause(filters.get("sort"))
    sql = (
        "SELECT l.*, s.name AS source_name, "
        "EXISTS(SELECT 1 FROM favorites f WHERE f.listing_id = l.id) AS is_favorite "
        f"FROM listings l JOIN sources s ON s.id = l.source_id {where} "
        f"{order} LIMIT ? OFFSET ?"
    )
    return conn.execute(sql, [*params, limit, offset]).fetchall()


def count_listings(conn: sqlite3.Connection, filters: dict[str, Any]) -> int:
    where, params = _build_where(filters)
    row = conn.execute(
        f"SELECT COUNT(*) AS n FROM listings l {where}", params
    ).fetchone()
    return int(row["n"])


def _build_where(filters: dict[str, Any]) -> tuple[str, list[Any]]:
    clauses: list[str] = []
    params: list[Any] = []

    status = filters.get("status", "active")
    if status and status != "any":
        clauses.append("l.status = ?")
        params.append(status)

    text = filters.get("q")
    if text:
        clauses.append("(l.title LIKE ? OR l.description LIKE ? OR l.municipality LIKE ? OR l.province LIKE ?)")
        like = f"%{text}%"
        params += [like, like, like, like]

    for field, column in (("province", "province"), ("municipality", "municipality"),
                          ("property_type", "property_type"), ("transaction_type", "transaction_type")):
        value = filters.get(field)
        if value:
            clauses.append(f"l.{column} = ?")
            params.append(value)

    for field, column, op in (
        ("price_min", "price", ">="), ("price_max", "price", "<="),
        ("bedrooms_min", "bedrooms", ">="), ("bathrooms_min", "bathrooms", ">="),
        ("built_min", "built_area_m2", ">="), ("plot_min", "plot_area_m2", ">="),
        ("pool_size_min", "pool_size_m2", ">="),
        ("elevation_min", "elevation_m", ">="), ("elevation_max", "elevation_m", "<="),
        ("year_min", "year_built", ">="),
    ):
        value = filters.get(field)
        if value not in (None, ""):
            clauses.append(f"l.{column} {op} ?")
            params.append(value)

    # Boolean feature toggles: only constrain when explicitly requested (=1).
    for column, _label in FEATURE_FILTERS:
        if filters.get(column):
            clauses.append(f"l.{column} = 1")

    if filters.get("has_geo"):
        clauses.append("l.latitude IS NOT NULL AND l.longitude IS NOT NULL")
    if filters.get("favorites_only"):
        clauses.append("EXISTS(SELECT 1 FROM favorites f WHERE f.listing_id = l.id)")

    where = ("WHERE " + " AND ".join(clauses)) if clauses else ""
    return where, params


_SORTS = {
    "price_asc": "l.price ASC",
    "price_desc": "l.price DESC",
    "newest": "l.first_seen DESC",
    "elevation_desc": "l.elevation_m DESC",
    "area_desc": "l.built_area_m2 DESC",
}


def _order_clause(sort: str | None) -> str:
    return "ORDER BY " + _SORTS.get(sort or "newest", _SORTS["newest"])


def get_listing(conn: sqlite3.Connection, listing_id: int) -> sqlite3.Row | None:
    return conn.execute(
        "SELECT l.*, s.name AS source_name, "
        "EXISTS(SELECT 1 FROM favorites f WHERE f.listing_id = l.id) AS is_favorite "
        "FROM listings l JOIN sources s ON s.id = l.source_id WHERE l.id = ?",
        (listing_id,),
    ).fetchone()


def get_photos(conn: sqlite3.Connection, listing_id: int) -> list[sqlite3.Row]:
    return conn.execute(
        "SELECT url, caption FROM photos WHERE listing_id = ? ORDER BY position",
        (listing_id,),
    ).fetchall()


def get_features(conn: sqlite3.Connection, listing_id: int) -> list[sqlite3.Row]:
    return conn.execute(
        "SELECT key, value FROM listing_features WHERE listing_id = ? ORDER BY key",
        (listing_id,),
    ).fetchall()


def get_price_history(conn: sqlite3.Connection, listing_id: int) -> list[sqlite3.Row]:
    return conn.execute(
        "SELECT price, observed_at FROM price_history WHERE listing_id = ? ORDER BY observed_at",
        (listing_id,),
    ).fetchall()


def distinct_values(conn: sqlite3.Connection, column: str) -> list[str]:
    if column not in {"province", "municipality", "property_type"}:
        raise ValueError(f"refusing to select distinct on {column!r}")
    rows = conn.execute(
        f"SELECT DISTINCT {column} AS v FROM listings "
        f"WHERE {column} IS NOT NULL AND {column} <> '' ORDER BY {column}"
    ).fetchall()
    return [row["v"] for row in rows]


# ---------------------------------------------------------------------------
# Favorites
# ---------------------------------------------------------------------------
def set_favorite(conn: sqlite3.Connection, listing_id: int, note: str = "") -> None:
    conn.execute(
        "INSERT INTO favorites(listing_id, note) VALUES(?,?) "
        "ON CONFLICT(listing_id) DO UPDATE SET note=excluded.note, updated_at=datetime('now')",
        (listing_id, note),
    )
    conn.commit()


def remove_favorite(conn: sqlite3.Connection, listing_id: int) -> None:
    conn.execute("DELETE FROM favorites WHERE listing_id = ?", (listing_id,))
    conn.commit()


def get_favorite(conn: sqlite3.Connection, listing_id: int) -> sqlite3.Row | None:
    return conn.execute(
        "SELECT listing_id, note FROM favorites WHERE listing_id = ?", (listing_id,)
    ).fetchone()


def stats(conn: sqlite3.Connection) -> dict[str, int]:
    row = conn.execute(
        "SELECT "
        "  COUNT(*) AS total, "
        "  SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active, "
        "  SUM(CASE WHEN status='sold'   THEN 1 ELSE 0 END) AS sold "
        "FROM listings"
    ).fetchone()
    favs = conn.execute("SELECT COUNT(*) AS n FROM favorites").fetchone()["n"]
    return {
        "total": row["total"] or 0,
        "active": row["active"] or 0,
        "sold": row["sold"] or 0,
        "favorites": favs or 0,
    }
