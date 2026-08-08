"""A synthetic, offline demo spider.

Why this exists
---------------
Real portals such as Idealista forbid scraping in their terms and actively block
bots, and shipping a scraper aimed at them would be both fragile and improper. So
the runnable example spider invents its own catalogue of believable Spanish
listings instead — real towns, real coordinates, real elevations — letting the
whole pipeline, database and frontend work end-to-end with no network at all.

To crawl a real site you write a sibling spider (see base.Spider's FIELD
REFERENCE) that fetches pages with self.http_get() and yields the same dicts;
this one is the reference implementation. It is deterministic: the catalogue is
stable across runs (so updates and favorites are meaningful), while a small
rotating slice is withheld each run to exercise sold-detection.
"""
from __future__ import annotations

import random
from typing import Iterator

from ..base import Spider

# name, province, region, lat, lon, elevation_m, coastal
TOWNS = [
    ("Marbella", "Málaga", "Andalucía", 36.5101, -4.8825, 12, True),
    ("Estepona", "Málaga", "Andalucía", 36.4272, -5.1457, 21, True),
    ("Nerja", "Málaga", "Andalucía", 36.7452, -3.8760, 20, True),
    ("Ronda", "Málaga", "Andalucía", 36.7460, -5.1611, 723, False),
    ("Granada", "Granada", "Andalucía", 37.1773, -3.5986, 738, False),
    ("Órgiva", "Granada", "Andalucía", 36.9036, -3.4249, 450, False),
    ("Cómpeta", "Málaga", "Andalucía", 36.8340, -3.9730, 638, False),
    ("Frigiliana", "Málaga", "Andalucía", 36.7936, -3.8946, 320, False),
    ("Mijas", "Málaga", "Andalucía", 36.5957, -4.6373, 428, False),
    ("Tarifa", "Cádiz", "Andalucía", 36.0143, -5.6044, 6, True),
    ("Valencia", "Valencia", "Comunidad Valenciana", 39.4699, -0.3763, 15, True),
    ("Alicante", "Alicante", "Comunidad Valenciana", 38.3452, -0.4810, 3, True),
    ("Xàbia", "Alicante", "Comunidad Valenciana", 38.7895, 0.1662, 90, True),
    ("Dénia", "Alicante", "Comunidad Valenciana", 38.8408, 0.1057, 12, True),
    ("Altea", "Alicante", "Comunidad Valenciana", 38.5990, -0.0513, 20, True),
    ("Morella", "Castellón", "Comunidad Valenciana", 40.6186, -0.1010, 1004, False),
    ("Girona", "Girona", "Cataluña", 41.9794, 2.8214, 75, False),
    ("Sitges", "Barcelona", "Cataluña", 41.2371, 1.8055, 10, True),
]

PROPERTY_TYPES = ["villa", "apartment", "townhouse", "finca", "country-house", "penthouse"]
CONDITIONS = ["new", "good", "to-reform"]
HEATING = ["gas", "electric", "heat-pump", "pellet-stove", None]


class DemoSpider(Spider):
    slug = "demo"
    name = "Demo Catalogue (offline sample data)"
    base_url = "https://example.invalid/casa"

    CATALOGUE_SIZE = 54
    WITHHOLD_PER_RUN = 3  # simulate this many houses going off-market each run

    def crawl(self) -> Iterator[dict]:
        catalogue = list(self._catalogue())
        # Deterministic catalogue, but rotate which few are "sold" each call so
        # the update process has something to detect. Unseeded on purpose.
        withheld = set(random.sample(range(len(catalogue)),
                                     min(self.WITHHOLD_PER_RUN, len(catalogue))))
        for index, listing in enumerate(catalogue):
            if index not in withheld:
                yield listing

    def _catalogue(self) -> Iterator[dict]:
        rng = random.Random(1975)  # fixed seed → stable catalogue
        for i in range(self.CATALOGUE_SIZE):
            town = TOWNS[i % len(TOWNS)]
            yield self._make_listing(rng, i, town)

    def _make_listing(self, rng: random.Random, i: int, town) -> dict:
        name, province, region, base_lat, base_lon, elevation, coastal = town
        ptype = rng.choice(PROPERTY_TYPES)
        bedrooms = rng.randint(1, 6)
        bathrooms = rng.randint(1, max(1, bedrooms - 1) + 1)
        built = rng.randint(55, 420)
        has_plot = ptype in ("villa", "finca", "country-house", "townhouse")
        plot = rng.randint(200, 5000) if has_plot else None

        # Price loosely driven by size, location and altitude premium/discount.
        base_m2 = 3800 if coastal else 2100
        price = int((built * base_m2 + (plot or 0) * 40) * rng.uniform(0.8, 1.25))
        price = round(price, -3)

        pool = 1 if (has_plot and rng.random() < 0.6) or rng.random() < 0.2 else 0
        pool_size = rng.choice([24, 32, 40, 50, 60, 72]) if pool else None
        garden = 1 if has_plot and rng.random() < 0.85 else (1 if rng.random() < 0.2 else 0)

        # jitter the coordinate a little so multiple houses in a town spread out
        lat = round(base_lat + rng.uniform(-0.02, 0.02), 6)
        lon = round(base_lon + rng.uniform(-0.02, 0.02), 6)

        ref = f"{self.slug}-{i:04d}"
        url = f"{self.base_url}/{province.lower()}/{name.lower().replace('à','a').replace('ó','o').replace('é','e')}/{ref}"

        photos = [f"https://picsum.photos/seed/{ref}-{p}/1024/768" for p in range(rng.randint(4, 9))]

        listing = {
            "url": url,
            "source_ref": ref,
            "title": f"{ptype.replace('-', ' ').title()} in {name}",
            "description": self._describe(name, region, ptype, bedrooms, elevation, coastal, pool, garden),
            "transaction_type": "sale",
            "property_type": ptype,
            "price": price,
            "currency": "EUR",
            "municipality": name,
            "province": province,
            "region": region,
            "country": "ES",
            "latitude": lat,
            "longitude": lon,
            "elevation_m": elevation,  # supplied so the demo works fully offline
            "bedrooms": bedrooms,
            "bathrooms": bathrooms,
            "built_area_m2": built,
            "plot_area_m2": plot,
            "terrace_area_m2": rng.choice([0, 8, 12, 20, 35]) or None,
            "year_built": rng.randint(1968, 2024),
            "condition": rng.choice(CONDITIONS),
            "energy_rating": rng.choice(list("ABCDEFG")),
            "dual_occupancy": 1 if rng.random() < 0.22 else 0,
            "garden": garden,
            "garden_trees": 1 if garden and rng.random() < 0.7 else 0,
            "garden_plants": 1 if garden and rng.random() < 0.8 else 0,
            "open_kitchen": 1 if rng.random() < 0.5 else 0,
            "pool": pool,
            "pool_size_m2": pool_size,
            "pool_private": 1 if pool and has_plot else (0 if pool else None),
            "terrace": 1 if rng.random() < 0.75 else 0,
            "balcony": 1 if rng.random() < 0.5 else 0,
            "garage": 1 if rng.random() < 0.55 else 0,
            "parking_spaces": rng.choice([0, 1, 1, 2, 3]),
            "storage_room": 1 if rng.random() < 0.5 else 0,
            "air_conditioning": 1 if rng.random() < 0.7 else 0,
            "heating": 1 if rng.random() < 0.65 else 0,
            "heating_type": rng.choice(HEATING),
            "fireplace": 1 if (not coastal and rng.random() < 0.6) or rng.random() < 0.2 else 0,
            "elevator": 1 if ptype in ("apartment", "penthouse") and rng.random() < 0.6 else 0,
            "furnished": 1 if rng.random() < 0.45 else 0,
            "solar_panels": 1 if rng.random() < 0.3 else 0,
            "sea_view": 1 if coastal and rng.random() < 0.6 else 0,
            "mountain_view": 1 if not coastal and rng.random() < 0.7 else 0,
            "sea_front": 1 if coastal and rng.random() < 0.15 else 0,
            "guest_toilet": 1 if rng.random() < 0.5 else 0,
            "utility_room": 1 if rng.random() < 0.4 else 0,
            "accessible": 1 if rng.random() < 0.25 else 0,
            "photos": photos,
            "features": {
                "orientation": rng.choice(["south", "south-west", "east", "west", "north"]),
                "distance_to_beach_km": (round(rng.uniform(0.05, 2.0), 2) if coastal
                                         else round(rng.uniform(15, 90), 1)),
                "community_fees_eur_month": rng.choice([0, 35, 60, 90, 140, 220]),
                "plot_registered": rng.choice(["yes", "yes", "no", "unknown"]),
            },
        }
        return listing

    @staticmethod
    def _describe(name, region, ptype, bedrooms, elevation, coastal, pool, garden) -> str:
        bits = [f"{ptype.replace('-', ' ').title()} met {bedrooms} slaapkamers in {name}, {region}."]
        if coastal:
            bits.append("Op korte afstand van de kust.")
        else:
            bits.append(f"Rustig gelegen op {elevation} m hoogte — merkbaar koeler in de zomer.")
        if pool:
            bits.append("Voorzien van een privézwembad.")
        if garden:
            bits.append("Ruime tuin met mediterrane beplanting.")
        return " ".join(bits)
