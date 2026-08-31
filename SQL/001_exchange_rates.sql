CREATE TABLE IF NOT EXISTS exchange_rates (
    id CHAR(36) PRIMARY KEY,
    rate_date DATE NOT NULL,
    base_currency CHAR(3) NOT NULL DEFAULT 'CNY',
    quote_currency CHAR(3) NOT NULL,
    real_rate DECIMAL(20, 8) NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'FRANKFURTER',
    fallback_used BOOLEAN NOT NULL DEFAULT FALSE,
    fetched_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY exchange_rates_pair_date_unique (rate_date, base_currency, quote_currency),
    KEY exchange_rates_pair_date_index (base_currency, quote_currency, rate_date)
);

CREATE TABLE IF NOT EXISTS exchange_rate_settings (
    quote_currency CHAR(3) PRIMARY KEY,
    selling_rate DECIMAL(20, 8) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

INSERT IGNORE INTO exchange_rate_settings (quote_currency, selling_rate, created_at, updated_at)
VALUES
    ('USD', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('MYR', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('THB', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
