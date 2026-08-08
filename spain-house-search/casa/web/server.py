"""A tiny stdlib web server for the frontend.

Routes
------
GET  /                 search page (filters + result grid)
GET  /map              full-screen zoomable map of the current selection
GET  /listing/<id>     detail page: gallery, all specs, favorite-with-note, map
GET  /favorites        favorites with editable notes
GET  /api/geo          GeoJSON-ish JSON for the map (honours the same filters)
POST /favorite         add/update a favorite (form-encoded)
POST /unfavorite       remove a favorite
GET  /static/<file>    CSS/JS assets
"""
from __future__ import annotations

import json
import mimetypes
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from .. import config, db, repository
from . import views

STATIC_DIR = Path(__file__).resolve().parent / "static"
PER_PAGE = 24

# Filter params and how to coerce them from the query string.
_INT_FILTERS = {
    "price_min", "price_max", "bedrooms_min", "bathrooms_min", "built_min",
    "plot_min", "pool_size_min", "elevation_min", "elevation_max", "year_min",
}
_STR_FILTERS = {"q", "province", "municipality", "property_type", "transaction_type",
                "status", "sort"}
_BOOL_FILTERS = {col for col, _ in repository.FEATURE_FILTERS} | {"favorites_only", "has_geo"}


def parse_filters(query: dict[str, list[str]]) -> dict:
    filters: dict = {}
    for key in _STR_FILTERS:
        if query.get(key):
            filters[key] = query[key][0].strip()
    for key in _INT_FILTERS:
        if query.get(key) and query[key][0].strip() != "":
            try:
                filters[key] = int(float(query[key][0]))
            except ValueError:
                pass
    for key in _BOOL_FILTERS:
        if query.get(key) and query[key][0] in ("1", "on", "true"):
            filters[key] = 1
    filters.setdefault("status", "active")
    return filters


class Handler(BaseHTTPRequestHandler):
    server_version = "CasaEnEspana/0.1"

    # -- helpers --------------------------------------------------------------
    def _send(self, body: str | bytes, status: int = 200, ctype: str = "text/html; charset=utf-8"):
        data = body.encode("utf-8") if isinstance(body, str) else body
        self.send_response(status)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(data)

    def _redirect(self, location: str):
        self.send_response(303)
        self.send_header("Location", location)
        self.end_headers()

    def _read_form(self) -> dict[str, list[str]]:
        length = int(self.headers.get("Content-Length", 0) or 0)
        raw = self.rfile.read(length).decode("utf-8") if length else ""
        return parse_qs(raw, keep_blank_values=True)

    def log_message(self, fmt, *args):  # quieter console
        pass

    # -- dispatch -------------------------------------------------------------
    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path
        query = parse_qs(parsed.query, keep_blank_values=True)
        try:
            if path == "/":
                return self._page_index(query)
            if path == "/map":
                return self._send(views.render_map(parse_filters(query)))
            if path == "/favorites":
                return self._page_favorites()
            if path.startswith("/listing/"):
                return self._page_listing(path.rsplit("/", 1)[-1])
            if path == "/api/geo":
                return self._api_geo(query)
            if path.startswith("/static/"):
                return self._static(path)
            return self._send("<h1>404</h1>", status=404)
        except Exception as exc:  # noqa: BLE001
            return self._send(f"<h1>500</h1><pre>{views.esc(exc)}</pre>", status=500)

    do_HEAD = do_GET

    def do_POST(self):
        path = urlparse(self.path).path
        form = self._read_form()
        listing_id = (form.get("listing_id") or ["0"])[0]
        nxt = (form.get("next") or [f"/listing/{listing_id}"])[0]
        conn = db.connect()
        try:
            if path == "/favorite":
                note = (form.get("note") or [""])[0]
                repository.set_favorite(conn, int(listing_id), note)
            elif path == "/unfavorite":
                repository.remove_favorite(conn, int(listing_id))
            else:
                return self._send("<h1>404</h1>", status=404)
        finally:
            conn.close()
        return self._redirect(nxt)

    # -- pages ----------------------------------------------------------------
    def _page_index(self, query):
        filters = parse_filters(query)
        page = max(1, int((query.get("page") or ["1"])[0] or 1))
        conn = db.connect()
        try:
            total = repository.count_listings(conn, filters)
            rows = repository.search_listings(
                conn, filters, limit=PER_PAGE, offset=(page - 1) * PER_PAGE)
            html = views.render_index(
                stats=repository.stats(conn), filters=filters,
                provinces=repository.distinct_values(conn, "province"),
                ptypes=repository.distinct_values(conn, "property_type"),
                rows=rows, total=total, page=page, per_page=PER_PAGE)
        finally:
            conn.close()
        return self._send(html)

    def _page_listing(self, raw_id):
        try:
            listing_id = int(raw_id)
        except ValueError:
            return self._send("<h1>404</h1>", status=404)
        conn = db.connect()
        try:
            row = repository.get_listing(conn, listing_id)
            if row is None:
                return self._send("<h1>404</h1>", status=404)
            html = views.render_detail(
                row=row,
                photos=repository.get_photos(conn, listing_id),
                features=repository.get_features(conn, listing_id),
                price_history=repository.get_price_history(conn, listing_id),
                fav=repository.get_favorite(conn, listing_id))
        finally:
            conn.close()
        return self._send(html)

    def _page_favorites(self):
        conn = db.connect()
        try:
            rows = repository.search_listings(
                conn, {"favorites_only": 1, "status": "any"}, limit=500)
            enriched = []
            for r in rows:
                fav = repository.get_favorite(conn, r["id"])
                d = dict(r)
                d["note"] = fav["note"] if fav else ""
                enriched.append(d)
            html = views.render_favorites(enriched)
        finally:
            conn.close()
        return self._send(html)

    def _api_geo(self, query):
        filters = parse_filters(query)
        filters["has_geo"] = 1
        conn = db.connect()
        try:
            rows = repository.search_listings(conn, filters, limit=2000)
        finally:
            conn.close()
        points = [{
            "id": r["id"],
            "lat": r["latitude"],
            "lon": r["longitude"],
            "title": r["title"],
            "price": r["price"],
            "photo": r["main_photo_url"],
            "beds": r["bedrooms"],
            "area": r["built_area_m2"],
            "elevation": r["elevation_m"],
            "status": r["status"],
        } for r in rows]
        return self._send(json.dumps(points), ctype="application/json; charset=utf-8")

    def _static(self, path):
        name = path[len("/static/"):]
        target = (STATIC_DIR / name).resolve()
        if not str(target).startswith(str(STATIC_DIR)) or not target.is_file():
            return self._send("not found", status=404, ctype="text/plain")
        ctype = mimetypes.guess_type(str(target))[0] or "application/octet-stream"
        self._send(target.read_bytes(), ctype=ctype)


def serve(host: str = config.WEB_HOST, port: int = config.WEB_PORT) -> None:
    httpd = ThreadingHTTPServer((host, port), Handler)
    print(f"Casa en España draait op http://{host}:{port}  (Ctrl+C om te stoppen)")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nGestopt.")
    finally:
        httpd.server_close()
