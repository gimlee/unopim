CREATE TABLE IF NOT EXISTS product_content_revisions (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    platform VARCHAR(32) NOT NULL DEFAULT 'tiktok',
    region VARCHAR(16),
    locale VARCHAR(16) NOT NULL,
    matched_terms JSON NOT NULL,
    original_content JSON NOT NULL,
    optimized_content JSON NOT NULL,
    provider VARCHAR(255),
    model VARCHAR(255),
    method VARCHAR(32) NOT NULL DEFAULT 'ai',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS product_content_revisions_product_created_idx
    ON product_content_revisions (product_id, created_at);
