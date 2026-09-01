-- UnoPIM 标准类目与平台映射结构（PostgreSQL 参考脚本）
-- 正常安装/升级请执行 php artisan migrate --force；本文件用于审计和手工排障。

ALTER TABLE categories ADD COLUMN IF NOT EXISTS taxonomy_type varchar(32) NOT NULL DEFAULT 'legacy';
ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_assignable boolean NOT NULL DEFAULT true;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS status varchar(32) NOT NULL DEFAULT 'active';
ALTER TABLE categories ADD COLUMN IF NOT EXISTS replaced_by_id integer NULL REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS sort_order integer NOT NULL DEFAULT 0;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS source_platform varchar(64) NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS source_external_id varchar(255) NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS source_path text NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS source_url text NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS sync_locked boolean NOT NULL DEFAULT false;

CREATE TABLE IF NOT EXISTS category_aliases (
    id bigserial PRIMARY KEY,
    category_id integer NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    locale varchar(16) NOT NULL DEFAULT 'zh_CN',
    alias varchar(255) NOT NULL,
    normalized_alias varchar(255) NOT NULL,
    source varchar(32) NOT NULL DEFAULT 'manual',
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (category_id, locale, normalized_alias)
);

CREATE TABLE IF NOT EXISTS category_classification_rules (
    id bigserial PRIMARY KEY,
    category_id integer NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    rule_type varchar(32) NOT NULL DEFAULT 'keyword',
    field varchar(64) NOT NULL DEFAULT 'any',
    operator varchar(32) NOT NULL DEFAULT 'contains',
    value text NOT NULL,
    weight numeric(8,4) NOT NULL DEFAULT 1,
    status boolean NOT NULL DEFAULT true,
    position integer NOT NULL DEFAULT 0,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE TABLE IF NOT EXISTS platform_taxonomies (
    id bigserial PRIMARY KEY,
    code varchar(255) NOT NULL UNIQUE,
    platform varchar(64) NOT NULL,
    region varchar(16) NULL,
    locale varchar(16) NULL,
    version varchar(255) NOT NULL DEFAULT 'current',
    source_url text NULL,
    synced_at timestamptz NULL,
    status varchar(32) NOT NULL DEFAULT 'active',
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (platform, region, version)
);

CREATE TABLE IF NOT EXISTS platform_categories (
    id bigserial PRIMARY KEY,
    platform_taxonomy_id bigint NOT NULL REFERENCES platform_taxonomies(id) ON DELETE CASCADE,
    parent_id bigint NULL REFERENCES platform_categories(id) ON DELETE CASCADE,
    external_id varchar(255) NOT NULL,
    name varchar(255) NOT NULL,
    path text NOT NULL,
    is_leaf boolean NOT NULL DEFAULT true,
    enabled boolean NOT NULL DEFAULT true,
    raw_payload json NULL,
    payload_hash varchar(64) NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (platform_taxonomy_id, external_id)
);

CREATE TABLE IF NOT EXISTS category_mappings (
    id bigserial PRIMARY KEY,
    category_id integer NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    platform_category_id bigint NOT NULL REFERENCES platform_categories(id) ON DELETE CASCADE,
    mapping_type varchar(32) NOT NULL DEFAULT 'exact',
    conditions json NULL,
    priority integer NOT NULL DEFAULT 0,
    status varchar(32) NOT NULL DEFAULT 'draft',
    confidence numeric(5,4) NULL,
    reviewed_by integer NULL REFERENCES admins(id) ON DELETE SET NULL,
    reviewed_at timestamptz NULL,
    valid_from timestamptz NULL,
    valid_to timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (category_id, platform_category_id)
);

CREATE TABLE IF NOT EXISTS product_category_assignments (
    id bigserial PRIMARY KEY,
    product_id integer NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    category_id integer NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    role varchar(32) NOT NULL DEFAULT 'primary',
    status varchar(32) NOT NULL DEFAULT 'proposed',
    method varchar(32) NOT NULL DEFAULT 'manual',
    confidence numeric(5,4) NULL,
    evidence json NULL,
    taxonomy_version varchar(255) NULL,
    reviewed_by integer NULL REFERENCES admins(id) ON DELETE SET NULL,
    reviewed_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (product_id, category_id, role)
);

CREATE TABLE IF NOT EXISTS platform_category_attributes (
    id bigserial PRIMARY KEY,
    platform_category_id bigint NOT NULL REFERENCES platform_categories(id) ON DELETE CASCADE,
    external_id varchar(255) NOT NULL,
    name varchar(255) NOT NULL,
    type varchar(64) NULL,
    required boolean NOT NULL DEFAULT false,
    rules json NULL,
    raw_payload json NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (platform_category_id, external_id)
);

CREATE TABLE IF NOT EXISTS category_attribute_mappings (
    id bigserial PRIMARY KEY,
    category_id integer NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    platform_category_attribute_id bigint NOT NULL REFERENCES platform_category_attributes(id) ON DELETE CASCADE,
    source_attribute_code varchar(255) NOT NULL,
    status varchar(32) NOT NULL DEFAULT 'active',
    transform json NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    UNIQUE (category_id, platform_category_attribute_id)
);

CREATE TABLE IF NOT EXISTS taxonomy_sync_states (
    id bigserial PRIMARY KEY,
    key varchar(255) NOT NULL UNIQUE,
    version varchar(255) NOT NULL,
    metadata json NULL,
    synced_at timestamptz NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);
