-- Casa en España — database schema
--
-- Design notes
-- ------------
-- * Every listing is uniquely identified by its source URL (`listings.url`,
--   UNIQUE). Re-crawling the same URL updates the row in place; it never
--   duplicates. This is the "remember the unique urls" requirement.
-- * "Store all information" is satisfied on two levels: the frequently-searched
--   attributes get their own typed columns (fast WHERE clauses, indexes), while
--   the complete crawled payload is kept verbatim in `listings.raw_json` and the
--   open-ended `listing_features` key/value table. Nothing scraped is thrown away.
-- * Sold detection works by comparing what a crawl run saw against what is stored:
--   an active listing whose URL stops appearing on its source (and is confirmed
--   gone) flips to status = 'sold'/'removed'. See repository.mark_missing_as_sold.

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------------
-- Sources: one row per website we crawl.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sources (
    id            INTEGER PRIMARY KEY,
    slug          TEXT NOT NULL UNIQUE,      -- matches a spider name, e.g. 'demo'
    name          TEXT NOT NULL,
    base_url      TEXT,
    enabled       INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------------------------------------------------------------------------
-- Crawl runs: audit log for the "frequent update process".
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crawl_runs (
    id            INTEGER PRIMARY KEY,
    source_id     INTEGER REFERENCES sources(id) ON DELETE CASCADE,
    started_at    TEXT NOT NULL DEFAULT (datetime('now')),
    finished_at   TEXT,
    status        TEXT NOT NULL DEFAULT 'running',  -- running | ok | error
    seen_count    INTEGER NOT NULL DEFAULT 0,   -- urls encountered this run
    new_count     INTEGER NOT NULL DEFAULT 0,   -- listings inserted
    updated_count INTEGER NOT NULL DEFAULT 0,   -- listings updated
    sold_count    INTEGER NOT NULL DEFAULT 0,   -- listings flipped to sold
    error         TEXT
);

-- ---------------------------------------------------------------------------
-- Listings: the core table. Typed columns for searchable criteria + raw payload.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listings (
    id                 INTEGER PRIMARY KEY,
    source_id          INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
    source_ref         TEXT,                       -- the site's own listing id
    url                TEXT NOT NULL UNIQUE,       -- the unique key we remember

    -- lifecycle
    status             TEXT NOT NULL DEFAULT 'active',   -- active | sold | removed
    first_seen         TEXT NOT NULL DEFAULT (datetime('now')),
    last_seen          TEXT NOT NULL DEFAULT (datetime('now')),
    sold_detected_at   TEXT,

    -- headline
    title              TEXT,
    description        TEXT,
    transaction_type   TEXT DEFAULT 'sale',        -- sale | rent
    property_type      TEXT,                        -- apartment | villa | townhouse | finca | ...

    -- money
    price              INTEGER,                     -- in whole euros
    currency           TEXT DEFAULT 'EUR',
    price_per_m2       INTEGER,

    -- where
    address            TEXT,
    municipality       TEXT,
    province           TEXT,
    region             TEXT,                        -- comunidad autónoma
    postal_code        TEXT,
    country            TEXT DEFAULT 'ES',
    latitude           REAL,
    longitude          REAL,
    elevation_m        INTEGER,                     -- metres above sea level

    -- size & layout
    bedrooms           INTEGER,
    bathrooms          INTEGER,
    built_area_m2      INTEGER,
    plot_area_m2       INTEGER,
    terrace_area_m2    INTEGER,
    year_built         INTEGER,
    floor              INTEGER,
    total_floors       INTEGER,
    condition          TEXT,                        -- new | good | to-reform
    energy_rating      TEXT,                        -- A..G

    -- searchable feature flags (the "zoveel mogelijk kenmerken" request)
    dual_occupancy     INTEGER DEFAULT 0,   -- dubbele bewoning j/n
    garden             INTEGER DEFAULT 0,
    garden_trees       INTEGER DEFAULT 0,   -- tuin met bomen
    garden_plants      INTEGER DEFAULT 0,   -- ... en planten
    open_kitchen       INTEGER DEFAULT 0,   -- open keuken
    pool               INTEGER DEFAULT 0,   -- zwembad
    pool_size_m2       INTEGER,             -- ... en het formaat ervan
    pool_private       INTEGER,             -- 1 private, 0 communal
    terrace            INTEGER DEFAULT 0,
    balcony            INTEGER DEFAULT 0,
    garage             INTEGER DEFAULT 0,
    parking_spaces     INTEGER,
    storage_room       INTEGER DEFAULT 0,
    air_conditioning   INTEGER DEFAULT 0,
    heating            INTEGER DEFAULT 0,
    heating_type       TEXT,
    fireplace          INTEGER DEFAULT 0,
    elevator           INTEGER DEFAULT 0,
    furnished          INTEGER DEFAULT 0,
    solar_panels       INTEGER DEFAULT 0,
    sea_view           INTEGER DEFAULT 0,
    mountain_view      INTEGER DEFAULT 0,
    sea_front          INTEGER DEFAULT 0,
    guest_toilet       INTEGER DEFAULT 0,
    utility_room       INTEGER DEFAULT 0,
    accessible         INTEGER DEFAULT 0,   -- wheelchair friendly

    -- media
    main_photo_url     TEXT,                -- "automatisch een foto voorop"

    -- everything else, verbatim
    raw_json           TEXT,

    created_at         TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at         TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_listings_status       ON listings(status);
CREATE INDEX IF NOT EXISTS idx_listings_price        ON listings(price);
CREATE INDEX IF NOT EXISTS idx_listings_province     ON listings(province);
CREATE INDEX IF NOT EXISTS idx_listings_municipality ON listings(municipality);
CREATE INDEX IF NOT EXISTS idx_listings_bedrooms     ON listings(bedrooms);
CREATE INDEX IF NOT EXISTS idx_listings_elevation    ON listings(elevation_m);
CREATE INDEX IF NOT EXISTS idx_listings_pool         ON listings(pool);
CREATE INDEX IF NOT EXISTS idx_listings_geo          ON listings(latitude, longitude);

-- ---------------------------------------------------------------------------
-- Photos: the gallery. position 0 is the main/front photo.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photos (
    id          INTEGER PRIMARY KEY,
    listing_id  INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    url         TEXT NOT NULL,
    position    INTEGER NOT NULL DEFAULT 0,
    caption     TEXT,
    UNIQUE(listing_id, url)
);
CREATE INDEX IF NOT EXISTS idx_photos_listing ON photos(listing_id, position);

-- ---------------------------------------------------------------------------
-- Open-ended feature bag: anything a spider extracts that has no typed column.
-- Keeps the schema honest — new criteria are searchable before they earn a column.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listing_features (
    id          INTEGER PRIMARY KEY,
    listing_id  INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    key         TEXT NOT NULL,
    value       TEXT,
    UNIQUE(listing_id, key)
);
CREATE INDEX IF NOT EXISTS idx_features_key ON listing_features(key, value);

-- ---------------------------------------------------------------------------
-- Price history: every observed price change (feeds price-drop & sold signals).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_history (
    id          INTEGER PRIMARY KEY,
    listing_id  INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    price       INTEGER,
    observed_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_price_history_listing ON price_history(listing_id, observed_at);

-- ---------------------------------------------------------------------------
-- Favorites with a free-text note (the "favorieten met opmerking" request).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id          INTEGER PRIMARY KEY,
    listing_id  INTEGER NOT NULL UNIQUE REFERENCES listings(id) ON DELETE CASCADE,
    note        TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
