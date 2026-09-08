CREATE TABLE IF NOT EXISTS product_listing_exceptions (
    id BIGSERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku VARCHAR(128) NOT NULL,
    platform VARCHAR(32) NOT NULL DEFAULT 'tiktok',
    region VARCHAR(16),
    attempt_id VARCHAR(64) NOT NULL,
    event_key VARCHAR(128) NOT NULL,
    exception_type VARCHAR(64) NOT NULL,
    stage VARCHAR(128),
    severity VARCHAR(16) NOT NULL DEFAULT 'warning',
    message TEXT NOT NULL,
    requires_manual BOOLEAN NOT NULL DEFAULT FALSE,
    blocking BOOLEAN NOT NULL DEFAULT FALSE,
    details JSON,
    occurred_at TIMESTAMP(0) WITHOUT TIME ZONE,
    resolved_at TIMESTAMP(0) WITHOUT TIME ZONE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT listing_exceptions_attempt_event_unique UNIQUE (product_id, attempt_id, event_key)
);

CREATE INDEX IF NOT EXISTS product_listing_exceptions_sku_idx
    ON product_listing_exceptions (sku);
CREATE INDEX IF NOT EXISTS product_listing_exceptions_type_idx
    ON product_listing_exceptions (exception_type);
CREATE INDEX IF NOT EXISTS product_listing_exceptions_resolved_idx
    ON product_listing_exceptions (resolved_at);
CREATE INDEX IF NOT EXISTS listing_exceptions_product_resolved_idx
    ON product_listing_exceptions (product_id, resolved_at);
