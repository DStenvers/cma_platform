"""Command-line entry point.

    python -m casa initdb                 # create the database
    python -m casa crawl [--spider demo]  # one crawl run (all spiders by default)
    python -m casa update                 # alias for crawl-all: the "frequent" job
    python -m casa serve [--port 8000]    # start the web frontend
    python -m casa spiders                # list registered spiders
    python -m casa stats                  # quick database summary

`update` is what you point cron/Task Scheduler at, e.g. every few hours:
    */180 * * * *  cd /path/to/spain-house-search && python -m casa update
Each run refreshes every source, records new/changed houses, and flips
disappeared ones to 'sold'.
"""
from __future__ import annotations

import argparse
import sys

from . import config, db, repository
from .crawler import pipeline, registry


def cmd_initdb(_args) -> int:
    db.init_db()
    print(f"database ready at {config.DB_PATH}")
    return 0


def cmd_spiders(_args) -> int:
    for slug in registry.available():
        spider = registry.get(slug)
        print(f"{slug:12s} {spider.name}")
    return 0


def cmd_crawl(args) -> int:
    db.init_db()
    conn = db.connect()
    try:
        spiders = [registry.get(args.spider)] if args.spider else registry.all_spiders()
        for spider in spiders:
            pipeline.run_spider(conn, spider, enrich_elevation=not args.no_elevation)
    finally:
        conn.close()
    return 0


def cmd_stats(_args) -> int:
    db.init_db()
    conn = db.connect()
    try:
        s = repository.stats(conn)
        print(f"listings: {s['total']}  active: {s['active']}  "
              f"sold: {s['sold']}  favorites: {s['favorites']}")
    finally:
        conn.close()
    return 0


def cmd_serve(args) -> int:
    db.init_db()
    from .web.server import serve
    serve(host=args.host, port=args.port)
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="casa", description="Casa en España")
    sub = parser.add_subparsers(dest="command", required=True)

    sub.add_parser("initdb", help="create the database schema").set_defaults(func=cmd_initdb)
    sub.add_parser("spiders", help="list registered spiders").set_defaults(func=cmd_spiders)
    sub.add_parser("stats", help="print a database summary").set_defaults(func=cmd_stats)

    crawl = sub.add_parser("crawl", help="run a crawl")
    crawl.add_argument("--spider", help="only this spider slug (default: all)")
    crawl.add_argument("--no-elevation", action="store_true",
                       help="skip the elevation lookup")
    crawl.set_defaults(func=cmd_crawl)

    update = sub.add_parser("update", help="refresh all sources (for cron)")
    update.add_argument("--no-elevation", action="store_true")
    update.set_defaults(func=cmd_crawl, spider=None)

    serve = sub.add_parser("serve", help="start the web frontend")
    serve.add_argument("--host", default=config.WEB_HOST)
    serve.add_argument("--port", type=int, default=config.WEB_PORT)
    serve.set_defaults(func=cmd_serve)

    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
