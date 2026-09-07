CREATE TABLE IF NOT EXISTS product_category_classification_caches (
    id bigserial PRIMARY KEY,
    product_id integer NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    method varchar(16) NOT NULL,
    platform varchar(64) NOT NULL DEFAULT 'tiktok',
    standard_category_id integer NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    platform_category_id bigint NULL REFERENCES platform_categories(id) ON DELETE SET NULL,
    confidence numeric(5,4) NULL,
    status varchar(32) NOT NULL DEFAULT 'ready',
    provider varchar(255) NULL,
    model varchar(255) NULL,
    input_hash varchar(64) NULL,
    reason text NULL,
    evidence json NULL,
    generated_at timestamptz NOT NULL,
    applied_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    CONSTRAINT product_category_classification_cache_unique UNIQUE (product_id, method, platform)
);

CREATE INDEX IF NOT EXISTS product_category_classification_cache_status_idx
    ON product_category_classification_caches (product_id, status);
