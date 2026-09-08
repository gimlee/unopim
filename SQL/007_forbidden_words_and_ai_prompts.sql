ALTER TABLE magic_ai_system_prompts
    ADD COLUMN IF NOT EXISTS purpose VARCHAR(64) NOT NULL DEFAULT 'general';

CREATE INDEX IF NOT EXISTS magic_ai_system_prompts_purpose_enabled_idx
    ON magic_ai_system_prompts (purpose, is_enabled);

INSERT INTO magic_ai_system_prompts
    (title, purpose, tone, max_tokens, temperature, is_enabled, created_at, updated_at)
SELECT
    'AI 商品分类',
    'category_classification',
    '你是电商商品类目审核助手。根据商品名称、描述、规格和来源类目判断商品的核心用途，只能从用户提供的候选列表中选择最匹配的叶子类目。不得创造、改写或猜测候选范围以外的类目 ID；信息不足时选择证据最充分的候选，并简要说明依据。',
    400,
    0.1,
    TRUE,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM magic_ai_system_prompts WHERE purpose = 'category_classification'
);

INSERT INTO magic_ai_system_prompts
    (title, purpose, tone, max_tokens, temperature, is_enabled, created_at, updated_at)
SELECT
    '商品描述优化（标准模板）',
    'product_description',
    '你是专业的电商商品文案编辑。只描述商品本身可验证的信息，包括功能、材质、规格、结构、兼容性和使用方式。不得出现价格、金额、币种、折扣、促销或采购数量；不得提及国家、城市、地区、产地、原产国、制造地点、发货地、供应商所在地或目标市场；不得提及任何电商平台、店铺、销售渠道、销售目的、上架、转售、批发、零售或跨境业务。使用自然段组织内容，段落之间空一行，每个句子必须使用完整标点；表达自然、通顺、人类可读，避免关键词堆砌。不得虚构参数、认证、功效或卖点，不得使用夸大和绝对化表述。保持输入内容的主要语言。',
    1200,
    0.2,
    TRUE,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM magic_ai_system_prompts WHERE purpose = 'product_description'
);

CREATE TABLE IF NOT EXISTS content_policy_forbidden_words (
    id BIGSERIAL PRIMARY KEY,
    term VARCHAR(255) NOT NULL,
    normalized_term VARCHAR(255) NOT NULL UNIQUE,
    status BOOLEAN NOT NULL DEFAULT TRUE,
    notes VARCHAR(500),
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS content_policy_words_status_term_idx
    ON content_policy_forbidden_words (status, term);

INSERT INTO content_policy_forbidden_words
    (term, normalized_term, status, notes, created_at, updated_at)
SELECT term, LOWER(term), TRUE, '系统预置电商平台词', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM (VALUES
    ('Amazon'), ('亚马逊'), ('eBay'), ('易贝'), ('AliExpress'), ('全球速卖通'), ('速卖通'),
    ('Alibaba'), ('阿里巴巴'), ('阿里巴巴国际站'), ('1688'), ('Taobao'), ('淘宝'),
    ('Tmall'), ('天猫'), ('JD.com'), ('京东'), ('京东商城'), ('Pinduoduo'), ('拼多多'),
    ('Temu'), ('SHEIN'), ('希音'), ('Shopee'), ('虾皮'), ('Lazada'), ('来赞达'),
    ('Walmart'), ('沃尔玛'), ('Etsy'), ('Rakuten'), ('乐天'), ('Mercado Libre'),
    ('美客多'), ('Coupang'), ('酷澎'), ('Tokopedia'), ('Bukalapak'), ('Flipkart'),
    ('Daraz'), ('Noon'), ('DHgate'), ('敦煌网'), ('TikTok Shop'), ('Douyin'),
    ('抖音商城'), ('Kuaishou'), ('快手小店'), ('Xiaohongshu'), ('小红书'),
    ('Vipshop'), ('唯品会'), ('Suning'), ('苏宁'), ('Gome'), ('国美'),
    ('Poizon'), ('得物'), ('Dangdang'), ('当当')
) AS defaults(term)
ON CONFLICT (normalized_term) DO NOTHING;

ALTER TABLE product_content_revisions
    ADD COLUMN IF NOT EXISTS template_id BIGINT,
    ADD COLUMN IF NOT EXISTS template_title VARCHAR(255),
    ADD COLUMN IF NOT EXISTS prompt_snapshot TEXT;
