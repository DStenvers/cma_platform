# 🏠 Casa en España — een betere huizenzoeker voor Spanje

Idealista heeft beperkte zoekopties. Dit project crawlt huizensites, onthoudt elke
unieke URL, bewaart **alle** informatie in een database, en biedt een frontend met
véél meer filters dan een gewone huizensite — inclusief fotogallerij, een inzoombare
kaart met foto's, hoogte t.o.v. de zeespiegel (bergen zijn koeler!) en favorieten met
notities.

Het is geschreven in **pure Python 3 (standaardbibliotheek, geen dependencies)** en
draait meteen — geen `pip install`, geen databaseserver. De data zit in één SQLite-bestand.

---

## Snel starten

```bash
cd spain-house-search

python3 -m casa initdb     # maak de database
python3 -m casa crawl      # vul hem met de demo-catalogus (werkt offline)
python3 -m casa serve      # open http://127.0.0.1:8000
```

Dat is alles. De demo-spider genereert een realistische catalogus van Spaanse
woningen (echte plaatsen, coördinaten en hoogtes) zodat de hele applicatie
end-to-end werkt zonder ook maar één externe site aan te spreken.

> De kaart en de foto's laden tegels/afbeeldingen van internet (OpenStreetMap,
> picsum.photos). Zonder internet blijft alles werken: mislukte foto's worden
> vervangen door een nette placeholder en de kaart toont een leeg grid.

---

## De vier onderdelen

### 1. Crawlen & unieke URL's onthouden
Elke woning wordt uniek geïdentificeerd door haar bron-URL (`listings.url`, UNIQUE).
Opnieuw crawlen **werkt de bestaande rij bij** — nooit een duplicaat. Spiders zijn
klein: ze leveren genormaliseerde dicts op; de rest (nette HTTP, robots.txt,
crawl-delay, opslag) zit in het framework.

```
casa/crawler/base.py        Spider-basisklasse + beleefde HTTP-client
casa/crawler/spiders/demo.py  voorbeeld-spider (offline demo-data)
casa/crawler/pipeline.py    één crawl-run: ophalen → verrijken → opslaan → sold-detectie
casa/crawler/registry.py    spiders registreren zodat de CLI ze per slug kan draaien
```

**Een echte site toevoegen** = een nieuwe spider schrijven die met
`self.http_get(url)` pagina's ophaalt en dezelfde dicts oplevert (zie de
`FIELD REFERENCE` bovenin `base.py`), en die in `registry.py` registreren. De
crawler respecteert standaard `robots.txt` en houdt een crawl-delay aan.
Controleer altijd de gebruiksvoorwaarden van een site voordat je hem crawlt —
veel portalen (waaronder Idealista) verbieden scraping. Daarom mikt de
meegeleverde spider bewust niet op een echt portaal.

### 2. Frequent bijwerken & "verkocht" detecteren
```bash
python3 -m casa update     # ververs alle bronnen (zet dit in cron / Taakplanner)
```

Elke run vergelijkt wat de bron nú toont met wat is opgeslagen. Een actieve woning
waarvan de URL niet meer opduikt, wordt op `sold` gezet (met tijdstip). Nieuwe en
gewijzigde woningen worden bijgewerkt; prijswijzigingen belanden in
`price_history`. Voorbeeld cron-regel (elke 3 uur):

```cron
0 */3 * * *  cd /pad/naar/spain-house-search && python3 -m casa update
```

### 3. De database — zoveel mogelijk zoekbare criteria
Zie [`casa/schema.sql`](casa/schema.sql). Naast de vanzelfsprekende velden (prijs,
plaats, slaapkamers, oppervlakte) zijn de expliciet gevraagde kenmerken eigen,
**zoekbare** kolommen:

| Wens | Kolom(men) |
|------|-----------|
| Dubbele bewoning j/n | `dual_occupancy` |
| Tuin met bomen en planten | `garden`, `garden_trees`, `garden_plants` |
| Open keuken | `open_kitchen` |
| Zwembad + formaat | `pool`, `pool_size_m2`, `pool_private` |
| Automatisch een foto voorop | `main_photo_url` (uit de eerste foto) |
| Hoogte t.o.v. zeespiegel | `elevation_m` (+ koelte-indicatie, zie hieronder) |

Plus tientallen andere: zeezicht, bergzicht, eerste lijn zee, airco, verwarming,
open haard, garage, zonnepanelen, lift, gemeubileerd, bouwjaar, energielabel,
perceeloppervlak, terras… En omdat je nooit *alles* vooraf in kolommen vangt,
bewaart de database **alle** gecrawlde data ook nog integraal: het volledige
payload in `listings.raw_json` en losse sleutel/waarde-kenmerken in
`listing_features` (die blijven óók doorzoekbaar).

### 4. Frontend — als een huizensite, maar met meer
- **Rijke filters** in de zijbalk: prijs, kamers, oppervlakte, perceel, bouwjaar,
  zwembadformaat, hoogte-bereik, plus alle kenmerk-vinkjes.
- **Fotogallerij** op de detailpagina (hoofdfoto + klikbare thumbnails).
- **Inzoombare kaart met foto's** (`/map`): elke woning een markering, klik voor
  een mini-kaart met foto, prijs en kerngegevens. Respecteert je filters.
- **Hoogte & koelte**: elke woning toont de hoogte in meters en een indicatie hoe
  veel koeler het er is dan aan de kust (~0,65 °C per 100 m — "huizen in de bergen
  zijn veel koeler"). Filter op `Hoogte ≥ / ≤`.
- **Favorieten met notitie**: klik op ☆ bij een kaart, of schrijf op de
  detailpagina een opmerking ("dichtbij internationale school", "prijs
  onderhandelbaar"). Terug te vinden onder **Favorieten**.

---

## CLI-overzicht

```
python3 -m casa initdb              database aanmaken
python3 -m casa crawl [--spider X]  één crawl (standaard: alle spiders)
python3 -m casa update              alle bronnen verversen (voor cron)
python3 -m casa serve [--port N]    webfrontend starten
python3 -m casa spiders             geregistreerde spiders tonen
python3 -m casa stats               database-samenvatting
```

Instelbaar via omgevingsvariabelen (zie [`casa/config.py`](casa/config.py)):
`CASA_DB`, `CASA_PORT`, `CASA_CRAWL_DELAY`, `CASA_RESPECT_ROBOTS`,
`CASA_ELEVATION` (zet op `0` om de hoogte-lookup uit te zetten), enz.

## Tests

```bash
python3 -m unittest discover -s tests -v
```

Dekt de kern-contracten: URL is de unieke sleutel (bijwerken, niet dupliceren),
sold-detectie, herplaatsing, en de zoekfilters.

## Projectstructuur

```
spain-house-search/
├── casa/
│   ├── cli.py            command-line entry point (python -m casa ...)
│   ├── config.py         instellingen (env-overrides)
│   ├── db.py             SQLite-verbinding + schema-bootstrap
│   ├── schema.sql        het datamodel
│   ├── repository.py     upserts, sold-detectie, zoeken, favorieten
│   ├── enrich.py         hoogte-lookup + koelte-indicatie
│   ├── crawler/          spider-framework + pipeline + demo-spider
│   └── web/              stdlib-webserver, views, static (CSS/JS)
├── tests/                unittest-suite
└── data/                 SQLite-bestand (gegenereerd, niet in git)
```

## Ontwerpkeuzes in het kort
- **Geen dependencies**: draait overal met alleen Python 3.8+. Web via
  `http.server`, opslag via `sqlite3`, HTTP via `urllib` — allemaal stdlib.
- **URL als bron van waarheid** voor deduplicatie en sold-detectie.
- **Twee opslaglagen**: getypte kolommen voor snelle filters + `raw_json` /
  `listing_features` zodat er niets verloren gaat.
- **Beleefd crawlen**: eigen User-Agent, robots.txt, crawl-delay.
