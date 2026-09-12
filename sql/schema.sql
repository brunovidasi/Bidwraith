-- ebay_bidder database schema (SQLite)
-- Applied automatically at runtime by includes/db.php — no manual migration needed.

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS ebay_accounts (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    environment      TEXT NOT NULL DEFAULT 'sandbox',
    auth_token       TEXT NOT NULL,
    token_expires_at TEXT,
    ebay_username    TEXT,
    connected_at     TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS app_tokens (
    environment  TEXT PRIMARY KEY,
    access_token TEXT NOT NULL,
    expires_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS watched_auctions (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id              INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    item_id              TEXT NOT NULL,
    title                TEXT,
    max_bid              REAL NOT NULL,
    end_time             TEXT,
    snipe_seconds_before INTEGER NOT NULL DEFAULT 5,
    status               TEXT NOT NULL DEFAULT 'pending',
    last_checked_at      TEXT,
    result_message       TEXT,
    created_at           TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_watched_auctions_status_end
    ON watched_auctions (status, end_time);

CREATE TABLE IF NOT EXISTS bid_log (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    watched_auction_id  INTEGER NOT NULL REFERENCES watched_auctions(id) ON DELETE CASCADE,
    attempted_at        TEXT NOT NULL DEFAULT (datetime('now')),
    success             INTEGER NOT NULL,
    response_summary    TEXT
);
