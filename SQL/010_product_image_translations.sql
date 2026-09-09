CREATE TABLE IF NOT EXISTS product_image_translations (
    id BIGSERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku VARCHAR(128) NOT NULL,
    region VARCHAR(16) NOT NULL,
    locale VARCHAR(16) NOT NULL,
    image_type VARCHAR(32) NOT NULL DEFAULT 'gallery',
    original_url TEXT NOT NULL,
    translated_url TEXT NOT NULL,
    local_path TEXT,
    variant_sku VARCHAR(128),
    sort_order INTEGER NOT NULL DEFAULT 0,
    metadata JSONB,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS product_image_translations_sku_idx
    ON product_image_translations (sku);
CREATE INDEX IF NOT EXISTS product_image_translations_region_idx
    ON product_image_translations (region);
CREATE INDEX IF NOT EXISTS product_image_translations_product_region_idx
    ON product_image_translations (product_id, region);
CREATE INDEX IF NOT EXISTS product_image_translations_variant_sku_idx
    ON product_image_translations (variant_sku);
