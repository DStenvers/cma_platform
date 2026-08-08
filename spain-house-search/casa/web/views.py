"""HTML rendering for the frontend.

Plain functions returning HTML strings — no template engine, so the app has zero
third-party dependencies. All dynamic text goes through `esc()`; the map and
gallery behaviour lives in static/app.js.
"""
from __future__ import annotations

from html import escape as _escape

from .. import enrich
from ..repository import FEATURE_FILTERS

NAV = [("/", "Zoeken"), ("/map", "Kaart"), ("/favorites", "Favorieten")]


def esc(value) -> str:
    return _escape("" if value is None else str(value))


def money(value) -> str:
    if value in (None, ""):
        return "—"
    return "€ " + f"{int(value):,}".replace(",", ".")


def area(value) -> str:
    return "—" if value in (None, "") else f"{int(value)} m²"


# ---------------------------------------------------------------------------
# Layout
# ---------------------------------------------------------------------------
def layout(title: str, body: str, active: str = "", head: str = "", scripts: str = "") -> str:
    nav = "".join(
        f'<a class="casa-nav__link{" casa-nav__link--active" if path == active else ""}" '
        f'href="{path}">{esc(label)}</a>'
        for path, label in NAV
    )
    return f"""<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)} · Casa en España</title>
<link rel="stylesheet" href="/static/style.css">
<link rel="preconnect" href="https://unpkg.com">
{head}
</head>
<body>
<header class="casa-header">
  <a class="casa-header__brand" href="/">🏠 Casa en España</a>
  <nav class="casa-nav">{nav}</nav>
</header>
<main class="casa-main">
{body}
</main>
<footer class="casa-footer">
  Persoonlijke huizenzoeker · data via crawler · hoogte t.o.v. zeeniveau &amp; koelte-indicatie
</footer>
{scripts}
</body>
</html>"""


# ---------------------------------------------------------------------------
# Search / index
# ---------------------------------------------------------------------------
PROPERTY_LABELS = {
    "villa": "Villa", "apartment": "Appartement", "townhouse": "Rijtjeshuis",
    "finca": "Finca", "country-house": "Landhuis", "penthouse": "Penthouse",
}


def _option(value, label, current) -> str:
    sel = " selected" if str(current) == str(value) else ""
    return f'<option value="{esc(value)}"{sel}>{esc(label)}</option>'


def filter_form(filters: dict, provinces: list[str], ptypes: list[str]) -> str:
    def val(key):
        v = filters.get(key)
        return "" if v in (None, "") else esc(v)

    prov_opts = '<option value="">Alle provincies</option>' + "".join(
        _option(p, p, filters.get("province", "")) for p in provinces
    )
    ptype_opts = '<option value="">Alle types</option>' + "".join(
        _option(p, PROPERTY_LABELS.get(p, p.title()), filters.get("property_type", "")) for p in ptypes
    )
    sort_opts = "".join(_option(v, lbl, filters.get("sort", "newest")) for v, lbl in [
        ("newest", "Nieuwste eerst"), ("price_asc", "Prijs oplopend"),
        ("price_desc", "Prijs aflopend"), ("area_desc", "Grootste woonopp."),
        ("elevation_desc", "Hoogst gelegen"),
    ])
    status_opts = "".join(_option(v, lbl, filters.get("status", "active")) for v, lbl in [
        ("active", "Te koop"), ("sold", "Verkocht/verdwenen"), ("any", "Alles"),
    ])

    checkboxes = "".join(
        f'<label class="casa-filter__check"><input type="checkbox" name="{col}" value="1"'
        f'{" checked" if filters.get(col) else ""}> {esc(label)}</label>'
        for col, label in FEATURE_FILTERS
    )

    return f"""
<form class="casa-filter" method="get" action="/">
  <div class="casa-filter__row">
    <input class="casa-filter__text" type="search" name="q" value="{val('q')}"
           placeholder="Zoek op tekst, plaats, provincie…">
  </div>
  <div class="casa-filter__grid">
    <label>Provincie<select name="province">{prov_opts}</select></label>
    <label>Type<select name="property_type">{ptype_opts}</select></label>
    <label>Plaats<input type="text" name="municipality" value="{val('municipality')}"></label>
    <label>Status<select name="status">{status_opts}</select></label>
    <label>Prijs vanaf<input type="number" name="price_min" value="{val('price_min')}" min="0" step="10000"></label>
    <label>Prijs tot<input type="number" name="price_max" value="{val('price_max')}" min="0" step="10000"></label>
    <label>Slaapkamers ≥<input type="number" name="bedrooms_min" value="{val('bedrooms_min')}" min="0"></label>
    <label>Badkamers ≥<input type="number" name="bathrooms_min" value="{val('bathrooms_min')}" min="0"></label>
    <label>Woonopp. ≥ m²<input type="number" name="built_min" value="{val('built_min')}" min="0" step="10"></label>
    <label>Perceel ≥ m²<input type="number" name="plot_min" value="{val('plot_min')}" min="0" step="50"></label>
    <label>Zwembad ≥ m²<input type="number" name="pool_size_min" value="{val('pool_size_min')}" min="0"></label>
    <label>Hoogte ≥ m<input type="number" name="elevation_min" value="{val('elevation_min')}" min="0" step="50"></label>
    <label>Hoogte ≤ m<input type="number" name="elevation_max" value="{val('elevation_max')}" min="0" step="50"></label>
    <label>Bouwjaar ≥<input type="number" name="year_min" value="{val('year_min')}" min="1800" step="1"></label>
    <label>Sorteren<select name="sort">{sort_opts}</select></label>
  </div>
  <fieldset class="casa-filter__features">
    <legend>Kenmerken</legend>
    {checkboxes}
  </fieldset>
  <div class="casa-filter__actions">
    <button type="submit" class="casa-btn casa-btn--primary">Zoeken</button>
    <a class="casa-btn" href="/">Wissen</a>
  </div>
</form>"""


def _elevation_html(row) -> str:
    elev = row["elevation_m"]
    if elev in (None, ""):
        return ""
    cooler = enrich.cooler_than_coast_c(elev)
    band = enrich.altitude_band(elev)
    cool = f" · ~{cooler}°C koeler" if cooler else ""
    return (f'<span class="casa-badge casa-badge--elev" title="Hoogte t.o.v. zeeniveau">'
            f'⛰ {int(elev)} m ({esc(band)}){cool}</span>')


def _feature_chips(row) -> str:
    chips = []
    if row["pool"]:
        size = f" {int(row['pool_size_m2'])} m²" if row["pool_size_m2"] else ""
        chips.append(f"🏊 Zwembad{size}")
    if row["garden"]:
        extra = []
        if row["garden_trees"]:
            extra.append("bomen")
        if row["garden_plants"]:
            extra.append("planten")
        chips.append("🌳 Tuin" + (f" ({', '.join(extra)})" if extra else ""))
    if row["open_kitchen"]:
        chips.append("🍳 Open keuken")
    if row["dual_occupancy"]:
        chips.append("🏠🏠 Dubbele bewoning")
    if row["sea_view"]:
        chips.append("🌊 Zeezicht")
    if row["mountain_view"]:
        chips.append("🏔 Bergzicht")
    if row["air_conditioning"]:
        chips.append("❄ Airco")
    return "".join(f'<span class="casa-chip">{esc(c)}</span>' for c in chips)


def listing_card(row) -> str:
    fav = "★" if row["is_favorite"] else "☆"
    fav_cls = " casa-card__fav--on" if row["is_favorite"] else ""
    sold = '<span class="casa-badge casa-badge--sold">Verkocht</span>' if row["status"] == "sold" else ""
    photo = row["main_photo_url"] or ""
    loc = ", ".join(filter(None, [row["municipality"], row["province"]]))
    ppm2 = f'<span class="casa-card__ppm2">{money(row["price_per_m2"])}/m²</span>' if row["price_per_m2"] else ""
    return f"""
<article class="casa-card">
  <a class="casa-card__media" href="/listing/{row['id']}">
    <img loading="lazy" src="{esc(photo)}" alt="{esc(row['title'])}"
         onerror="this.replaceWith(window.casaPlaceholder())">
    {sold}
    <button class="casa-card__fav{fav_cls}" data-id="{row['id']}"
            aria-label="Favoriet" title="Favoriet">{fav}</button>
  </a>
  <div class="casa-card__body">
    <div class="casa-card__price">{money(row['price'])} {ppm2}</div>
    <a class="casa-card__title" href="/listing/{row['id']}">{esc(row['title'])}</a>
    <div class="casa-card__loc">📍 {esc(loc)}</div>
    <div class="casa-card__specs">
      <span>🛏 {esc(row['bedrooms'] or '—')}</span>
      <span>🛁 {esc(row['bathrooms'] or '—')}</span>
      <span>📐 {area(row['built_area_m2'])}</span>
      {f"<span>🌿 {area(row['plot_area_m2'])}</span>" if row['plot_area_m2'] else ""}
    </div>
    <div class="casa-card__badges">{_elevation_html(row)}</div>
    <div class="casa-card__chips">{_feature_chips(row)}</div>
  </div>
</article>"""


def render_index(*, stats, filters, provinces, ptypes, rows, total, page, per_page) -> str:
    cards = "".join(listing_card(r) for r in rows) or \
        '<p class="casa-empty">Geen woningen gevonden met deze criteria.</p>'
    pages = max(1, (total + per_page - 1) // per_page)
    pager = _pager(filters, page, pages)
    summary = (f'<span class="casa-page__strong">{total}</span> woningen · {stats["active"]} te koop · '
               f"{stats['sold']} verkocht · {stats['favorites']} favorieten")
    body = f"""
<div class="casa-layout">
  <aside class="casa-sidebar">{filter_form(filters, provinces, ptypes)}</aside>
  <section class="casa-results">
    <div class="casa-results__head">
      <p class="casa-results__summary">{summary}</p>
      <a class="casa-btn casa-btn--ghost" href="/map?{_qs(filters)}">🗺 Toon op kaart</a>
    </div>
    <div class="casa-grid">{cards}</div>
    {pager}
  </section>
</div>"""
    return layout("Zoeken", body, active="/", scripts='<script src="/static/app.js"></script>')


def _pager(filters, page, pages) -> str:
    if pages <= 1:
        return ""
    def link(p, label, disabled=False):
        if disabled:
            return f'<span class="casa-pager__item casa-pager__item--off">{label}</span>'
        q = _qs({**filters, "page": p})
        return f'<a class="casa-pager__item" href="/?{q}">{label}</a>'
    return (f'<nav class="casa-pager">{link(page-1, "‹ Vorige", page <= 1)}'
            f'<span class="casa-pager__status">Pagina {page} / {pages}</span>'
            f'{link(page+1, "Volgende ›", page >= pages)}</nav>')


# ---------------------------------------------------------------------------
# Detail
# ---------------------------------------------------------------------------
def render_detail(*, row, photos, features, price_history, fav) -> str:
    gallery = _gallery(photos, row)
    specs = _spec_table(row)
    feats = "".join(
        f"<tr><th>{esc(f['key'].replace('_', ' '))}</th><td>{esc(f['value'])}</td></tr>"
        for f in features
    )
    fav_note = esc(fav["note"]) if fav else ""
    on = " casa-btn--primary" if fav else ""
    elev = _elevation_html(row)
    hist = _price_history(price_history)
    coords = ""
    if row["latitude"] and row["longitude"]:
        coords = (f'<div id="casa-detail-map" class="casa-detail__map" '
                  f'data-lat="{row["latitude"]}" data-lon="{row["longitude"]}" '
                  f'data-title="{esc(row["title"])}"></div>')
    source_link = f'<a href="{esc(row["url"])}" target="_blank" rel="noopener">Bekijk originele advertentie ↗</a>'
    sold = '<span class="casa-badge casa-badge--sold">Verkocht / van de markt</span>' if row["status"] == "sold" else ""

    body = f"""
<a class="casa-back" href="/">‹ Terug naar zoeken</a>
<article class="casa-detail">
  <header class="casa-detail__head">
    <div>
      <h1 class="casa-detail__title">{esc(row['title'])} {sold}</h1>
      <p class="casa-detail__loc">📍 {esc(', '.join(filter(None, [row['address'], row['municipality'], row['province'], row['region']])))}</p>
    </div>
    <div class="casa-detail__price">{money(row['price'])}
      <span class="casa-detail__ppm2">{money(row['price_per_m2'])}/m²</span>
    </div>
  </header>
  {gallery}
  <div class="casa-detail__grid">
    <section class="casa-detail__main">
      <div class="casa-detail__badges">{elev}<span class="casa-chip">{esc(PROPERTY_LABELS.get(row['property_type'], row['property_type'] or ''))}</span></div>
      <div class="casa-detail__chips">{_feature_chips(row)}</div>
      <h2>Omschrijving</h2>
      <p class="casa-detail__desc">{esc(row['description'])}</p>
      <h2>Kenmerken</h2>
      <table class="casa-spec">{specs}</table>
      {f'<h2>Extra kenmerken</h2><table class="casa-spec">{feats}</table>' if feats else ''}
      {hist}
      <p class="casa-detail__source">{source_link} · bron: {esc(row['source_name'])}</p>
    </section>
    <aside class="casa-detail__aside">
      {coords}
      <form class="casa-favform" method="post" action="/favorite">
        <input type="hidden" name="listing_id" value="{row['id']}">
        <h3>Favoriet met notitie</h3>
        <textarea name="note" rows="5" placeholder="Bijv. dichtbij internationale school, mooi uitzicht, prijs onderhandelbaar…">{fav_note}</textarea>
        <div class="casa-favform__actions">
          <button class="casa-btn{on}" type="submit">{'Notitie opslaan' if fav else 'Bewaar favoriet'}</button>
          {f'<button class="casa-btn casa-btn--danger" formaction="/unfavorite" type="submit">Verwijderen</button>' if fav else ''}
        </div>
      </form>
    </aside>
  </div>
</article>"""
    head = ('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">')
    scripts = ('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>'
               '<script src="/static/app.js"></script>')
    return layout(row["title"] or "Woning", body, head=head, scripts=scripts)


def _gallery(photos, row) -> str:
    if not photos:
        return '<div class="casa-gallery casa-gallery--empty">Geen foto\'s</div>'
    main = photos[0]["url"]
    thumbs = "".join(
        f'<button class="casa-gallery__thumb" data-src="{esc(p["url"])}">'
        f'<img loading="lazy" src="{esc(p["url"])}" alt="{esc(p["caption"] or row["title"])}"'
        f' onerror="this.replaceWith(window.casaPlaceholder())"></button>'
        for p in photos
    )
    return f"""
<div class="casa-gallery" id="casa-gallery">
  <div class="casa-gallery__main">
    <img id="casa-gallery-main" src="{esc(main)}" alt="{esc(row['title'])}"
         onerror="this.replaceWith(window.casaPlaceholder())">
  </div>
  <div class="casa-gallery__thumbs">{thumbs}</div>
</div>"""


def _spec_row(label, value) -> str:
    if value in (None, "", 0) and value != 0:
        pass
    return f"<tr><th>{esc(label)}</th><td>{esc(value) if value not in (None, '') else '—'}</td></tr>"


def _yesno(v) -> str:
    return "Ja" if v else "Nee"


def _spec_table(row) -> str:
    rows = [
        ("Type", PROPERTY_LABELS.get(row["property_type"], row["property_type"])),
        ("Slaapkamers", row["bedrooms"]),
        ("Badkamers", row["bathrooms"]),
        ("Woonoppervlak", area(row["built_area_m2"])),
        ("Perceel", area(row["plot_area_m2"]) if row["plot_area_m2"] else "—"),
        ("Terras", area(row["terrace_area_m2"]) if row["terrace_area_m2"] else "—"),
        ("Bouwjaar", row["year_built"]),
        ("Staat", row["condition"]),
        ("Energielabel", row["energy_rating"]),
        ("Hoogte (zeeniveau)", f"{int(row['elevation_m'])} m" if row["elevation_m"] is not None else "—"),
        ("Dubbele bewoning", _yesno(row["dual_occupancy"])),
        ("Open keuken", _yesno(row["open_kitchen"])),
        ("Zwembad", (f"Ja ({int(row['pool_size_m2'])} m²)" if row["pool_size_m2"] else "Ja") if row["pool"] else "Nee"),
        ("Tuin", _yesno(row["garden"])),
        ("Bomen in tuin", _yesno(row["garden_trees"])),
        ("Planten in tuin", _yesno(row["garden_plants"])),
        ("Verwarming", (row["heating_type"] or "Ja") if row["heating"] else "Nee"),
        ("Airconditioning", _yesno(row["air_conditioning"])),
        ("Open haard", _yesno(row["fireplace"])),
        ("Garage", _yesno(row["garage"])),
        ("Parkeerplaatsen", row["parking_spaces"]),
        ("Zonnepanelen", _yesno(row["solar_panels"])),
        ("Zeezicht", _yesno(row["sea_view"])),
        ("Bergzicht", _yesno(row["mountain_view"])),
        ("Eerste lijn zee", _yesno(row["sea_front"])),
        ("Gemeubileerd", _yesno(row["furnished"])),
        ("Lift", _yesno(row["elevator"])),
        ("Rolstoeltoegankelijk", _yesno(row["accessible"])),
    ]
    return "".join(_spec_row(lbl, v) for lbl, v in rows)


def _price_history(history) -> str:
    if len(history) <= 1:
        return ""
    items = "".join(
        f"<li>{esc(h['observed_at'][:10])}: {money(h['price'])}</li>" for h in history
    )
    return f'<h2>Prijsverloop</h2><ul class="casa-history">{items}</ul>'


# ---------------------------------------------------------------------------
# Map & favorites
# ---------------------------------------------------------------------------
def render_map(filters: dict) -> str:
    body = f"""
<div class="casa-mapview">
  <div class="casa-mapview__bar">
    <a class="casa-btn casa-btn--ghost" href="/?{_qs(filters)}">‹ Terug naar lijst</a>
    <span class="casa-mapview__hint">Klik op een markering voor foto en details. Kaart inzoombaar.</span>
  </div>
  <div id="casa-map" class="casa-map" data-endpoint="/api/geo?{_qs(filters)}"></div>
</div>"""
    head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">'
    scripts = ('<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>'
               '<script src="/static/app.js"></script>')
    return layout("Kaart", body, active="/map", head=head, scripts=scripts)


def render_favorites(rows) -> str:
    if not rows:
        cards = '<p class="casa-empty">Nog geen favorieten. Klik op ☆ bij een woning.</p>'
    else:
        cards = "".join(
            f"""<article class="casa-favcard">
  <a class="casa-favcard__media" href="/listing/{r['id']}">
    <img loading="lazy" src="{esc(r['main_photo_url'] or '')}" alt="{esc(r['title'])}"
         onerror="this.replaceWith(window.casaPlaceholder())">
  </a>
  <div class="casa-favcard__body">
    <div class="casa-card__price">{money(r['price'])}</div>
    <a class="casa-card__title" href="/listing/{r['id']}">{esc(r['title'])}</a>
    <div class="casa-card__loc">📍 {esc(', '.join(filter(None, [r['municipality'], r['province']])))}</div>
    <form class="casa-favform casa-favform--inline" method="post" action="/favorite">
      <input type="hidden" name="listing_id" value="{r['id']}">
      <input type="hidden" name="next" value="/favorites">
      <textarea name="note" rows="2" placeholder="Notitie…">{esc(r['note'])}</textarea>
      <div class="casa-favform__actions">
        <button class="casa-btn casa-btn--primary" type="submit">Opslaan</button>
        <button class="casa-btn casa-btn--danger" formaction="/unfavorite" type="submit">Verwijder</button>
      </div>
    </form>
  </div>
</article>""" for r in rows
        )
    body = f'<h1 class="casa-page-title">Favorieten</h1><div class="casa-favgrid">{cards}</div>'
    return layout("Favorieten", body, active="/favorites",
                  scripts='<script src="/static/app.js"></script>')


# ---------------------------------------------------------------------------
# Query-string helper
# ---------------------------------------------------------------------------
def _qs(filters: dict) -> str:
    from urllib.parse import urlencode
    clean = {k: v for k, v in filters.items() if v not in (None, "", 0) or k == "status"}
    return urlencode(clean)
