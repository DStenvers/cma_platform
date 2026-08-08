"""Run a spider and fold its output into the database.

One call to `run_spider` is one crawl run: it fetches listings, enriches them,
upserts each keyed on URL, and — crucially — flips any previously-active listing
of that source that did NOT turn up this time to 'sold'. That closing step is
what keeps the database honest about which houses are still on the market.
"""
from __future__ import annotations

import sqlite3

from .. import enrich, repository
from .base import Spider


def run_spider(conn: sqlite3.Connection, spider: Spider, *,
               enrich_elevation: bool = True, log=print) -> dict:
    source_id = repository.upsert_source(conn, spider.slug, spider.name, spider.base_url)
    run_id = repository.start_run(conn, source_id)

    seen: set[str] = set()
    new_count = updated_count = 0
    try:
        for listing in spider.crawl():
            url = listing.get("url")
            if not url or url in seen:
                continue
            seen.add(url)

            _fill_main_photo(listing)
            if enrich_elevation and listing.get("elevation_m") is None:
                listing["elevation_m"] = enrich.fetch_elevation(
                    listing.get("latitude"), listing.get("longitude")
                )
            _derive_price_per_m2(listing)

            _listing_id, is_new = repository.upsert_listing(conn, source_id, listing)
            new_count += int(is_new)
            updated_count += int(not is_new)

        conn.commit()
        sold_count = repository.mark_missing_as_sold(conn, source_id, seen)
        conn.commit()

        counters = dict(seen_count=len(seen), new_count=new_count,
                        updated_count=updated_count, sold_count=sold_count, status="ok")
        repository.finish_run(conn, run_id, **counters)
        log(f"[{spider.slug}] seen={len(seen)} new={new_count} "
            f"updated={updated_count} sold={sold_count}")
        return counters
    except Exception as exc:  # noqa: BLE001 - record the failure, then re-raise
        conn.rollback()
        repository.finish_run(conn, run_id, status="error", error=str(exc),
                              seen_count=len(seen))
        raise


def _fill_main_photo(listing: dict) -> None:
    if listing.get("main_photo_url"):
        return
    photos = listing.get("photos") or []
    if photos:
        first = photos[0]
        listing["main_photo_url"] = first if isinstance(first, str) else first.get("url")


def _derive_price_per_m2(listing: dict) -> None:
    if listing.get("price_per_m2"):
        return
    price = listing.get("price")
    area = listing.get("built_area_m2")
    if price and area:
        listing["price_per_m2"] = int(round(price / area))
