"""Tests for the crawl pipeline and repository — run with:  python -m unittest -v

These pin the two contracts that matter most:
  * a URL is the unique key — re-crawling updates in place, never duplicates;
  * a listing that stops appearing on its source is flipped to 'sold'.
"""
import os
import sys
import tempfile
import unittest
from typing import Iterator

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from casa import db, repository  # noqa: E402
from casa.crawler import pipeline  # noqa: E402
from casa.crawler.base import Spider  # noqa: E402


def _listing(ref, price=200000, **extra):
    base = {
        "url": f"https://example.invalid/{ref}",
        "source_ref": ref,
        "title": f"House {ref}",
        "price": price,
        "municipality": "Ronda",
        "province": "Málaga",
        "latitude": 36.74,
        "longitude": -5.16,
        "elevation_m": 723,
        "bedrooms": 3,
        "built_area_m2": 120,
        "pool": 1,
        "pool_size_m2": 40,
        "photos": [f"https://img.invalid/{ref}-1.jpg", f"https://img.invalid/{ref}-2.jpg"],
    }
    base.update(extra)
    return base


class ListSpider(Spider):
    slug = "test"
    name = "Test Spider"
    base_url = "https://example.invalid"

    def __init__(self, listings):
        super().__init__()
        self._listings = listings

    def crawl(self) -> Iterator[dict]:
        yield from self._listings


class PipelineTest(unittest.TestCase):
    def setUp(self):
        fd, self.path = tempfile.mkstemp(suffix=".sqlite3")
        os.close(fd)
        db.init_db(self.path)
        self.conn = db.connect(self.path)

    def tearDown(self):
        self.conn.close()
        os.unlink(self.path)

    def _run(self, listings):
        return pipeline.run_spider(self.conn, ListSpider(listings),
                                   enrich_elevation=False, log=lambda *a: None)

    def test_first_run_inserts(self):
        counters = self._run([_listing("a"), _listing("b")])
        self.assertEqual(counters["new_count"], 2)
        self.assertEqual(repository.stats(self.conn)["active"], 2)

    def test_recrawl_updates_not_duplicates(self):
        self._run([_listing("a", price=200000)])
        self._run([_listing("a", price=180000)])  # same url, new price
        stats = repository.stats(self.conn)
        self.assertEqual(stats["total"], 1)
        row = repository.search_listings(self.conn, {"status": "any"})[0]
        self.assertEqual(row["price"], 180000)
        history = repository.get_price_history(self.conn, row["id"])
        self.assertEqual([h["price"] for h in history], [200000, 180000])

    def test_disappeared_listing_marked_sold(self):
        self._run([_listing("a"), _listing("b")])
        counters = self._run([_listing("a")])  # 'b' is gone
        self.assertEqual(counters["sold_count"], 1)
        stats = repository.stats(self.conn)
        self.assertEqual(stats["active"], 1)
        self.assertEqual(stats["sold"], 1)

    def test_relisted_listing_becomes_active_again(self):
        self._run([_listing("a")])
        self._run([])  # a disappears -> sold
        self.assertEqual(repository.stats(self.conn)["sold"], 1)
        self._run([_listing("a")])  # comes back
        self.assertEqual(repository.stats(self.conn)["active"], 1)
        self.assertEqual(repository.stats(self.conn)["sold"], 0)

    def test_search_filters(self):
        self._run([
            _listing("cheap", price=100000, pool=0, pool_size_m2=None),
            _listing("pricey", price=500000, pool=1, pool_size_m2=60),
        ])
        by_price = repository.search_listings(self.conn, {"price_max": 200000})
        self.assertEqual([r["source_ref"] for r in by_price], ["cheap"])
        by_pool = repository.search_listings(self.conn, {"pool": 1})
        self.assertEqual([r["source_ref"] for r in by_pool], ["pricey"])
        by_pool_size = repository.search_listings(self.conn, {"pool_size_min": 50})
        self.assertEqual([r["source_ref"] for r in by_pool_size], ["pricey"])

    def test_main_photo_autofilled(self):
        self._run([_listing("a")])
        row = repository.search_listings(self.conn, {})[0]
        self.assertEqual(row["main_photo_url"], "https://img.invalid/a-1.jpg")

    def test_price_per_m2_derived(self):
        self._run([_listing("a", price=240000, built_area_m2=120)])
        row = repository.search_listings(self.conn, {})[0]
        self.assertEqual(row["price_per_m2"], 2000)

    def test_favorites(self):
        self._run([_listing("a")])
        row = repository.search_listings(self.conn, {})[0]
        repository.set_favorite(self.conn, row["id"], "close to school")
        fav = repository.get_favorite(self.conn, row["id"])
        self.assertEqual(fav["note"], "close to school")
        only = repository.search_listings(self.conn, {"favorites_only": 1})
        self.assertEqual(len(only), 1)
        repository.remove_favorite(self.conn, row["id"])
        self.assertEqual(repository.stats(self.conn)["favorites"], 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)
