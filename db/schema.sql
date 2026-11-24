CREATE TABLE IF NOT EXISTS abduction_reports (
    id INTEGER PRIMARY KEY,
    public_id TEXT NOT NULL UNIQUE CHECK (length(public_id) = 32),
    first_name_ct TEXT NOT NULL,
    last_name_ct TEXT NOT NULL,
    email_ct TEXT NOT NULL,
    occurred_when_ct TEXT NOT NULL,
    duration_ct TEXT NOT NULL,
    alien_count_ct TEXT NOT NULL,
    alien_description_ct TEXT NOT NULL,
    alien_actions_ct TEXT NOT NULL,
    comments_ct TEXT NOT NULL,
    fang_spotted TEXT NOT NULL CHECK (fang_spotted IN ('yes', 'no')),
    created_at INTEGER NOT NULL
) STRICT;

CREATE INDEX IF NOT EXISTS idx_abduction_reports_created_at
    ON abduction_reports (created_at DESC);

CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE COLLATE NOCASE CHECK (length(username) BETWEEN 3 AND 64),
    password_hash TEXT NOT NULL CHECK (password_hash LIKE '$argon2id$%'),
    totp_secret_ct TEXT NOT NULL,
    last_totp_step INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL
) STRICT;

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket_key TEXT PRIMARY KEY,
    window_started_at INTEGER NOT NULL,
    hits INTEGER NOT NULL CHECK (hits >= 0)
) STRICT, WITHOUT ROWID;

CREATE INDEX IF NOT EXISTS idx_rate_limit_buckets_window
    ON rate_limit_buckets (window_started_at);
