-- PostgreSQL reference DDL for asynchronous product image translation jobs.
-- The Laravel migration is authoritative for application deployments.

CREATE TABLE IF NOT EXISTS product_image_translation_jobs (
    id UUID PRIMARY KEY,
    product_id INTEGER NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'queued',
    payload JSON NOT NULL,
    results JSON NULL,
    errors JSON NULL,
    error TEXT NULL,
    started_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    completed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT product_image_translation_jobs_product_id_foreign
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS product_image_translation_jobs_status_index
    ON product_image_translation_jobs (status);

CREATE INDEX IF NOT EXISTS image_translation_jobs_product_created_idx
    ON product_image_translation_jobs (product_id, created_at);
