<?php

namespace Webkul\AdminApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\MagicAI\Models\MagicAISystemPrompt;
use Webkul\Product\Models\Product;

class ProductContentPolicyService
{
    /** @return array<int, string> */
    public function forbiddenWords(): array
    {
        if (Schema::hasTable('content_policy_forbidden_words')) {
            return DB::table('content_policy_forbidden_words')
                ->where('status', true)
                ->orderBy('id')
                ->pluck('term')
                ->map(fn ($word): string => trim((string) $word))
                ->filter()
                ->values()
                ->all();
        }

        $configured = core()->getConfigData('general.content_policy.settings.forbidden_words');
        $raw = is_string($configured) && trim($configured) !== ''
            ? preg_split('/[\r\n,，]+/u', $configured)
            : config('content_policy.default_forbidden_words', []);
        $seen = [];

        foreach ((array) $raw as $word) {
            $word = trim((string) $word);

            if ($word === '') {
                continue;
            }

            $seen[mb_strtolower($word)] = $word;
        }

        return array_values($seen);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, string>
     */
    public function scan(array $content): array
    {
        $visible = collect($content)
            ->filter(fn (mixed $value): bool => is_scalar($value) || $value instanceof \Stringable)
            ->map(fn (mixed $value): string => (string) $value)
            ->implode("\n");
        $visible = html_entity_decode(strip_tags($visible), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect($this->forbiddenWords())
            ->filter(fn (string $word): bool => preg_match($this->pattern($word), $visible) === 1)
            ->values()
            ->all();
    }

    /**
     * Scan and, only when needed, rewrite the selected localized product copy.
     * Every mutation is journalled before the product is updated.
     *
     * @return array<string, mixed>
     */
    public function optimize(Product $product, ?string $requestedLocale, ?string $region): array
    {
        [$channel, $locale, $original] = $this->localizedContent($product, $requestedLocale);
        $matched = $this->scan($original);

        if ($matched === []) {
            return [
                'changed'       => false,
                'matched_terms' => [],
                'locale'        => $locale,
                'channel'       => $channel,
                'content'       => $original,
            ];
        }

        [$platform, $model] = $this->textPlatform();
        $template = $this->descriptionTemplate();
        $aiError = null;
        $method = 'ai';

        try {
            $rewritten = $this->rewriteWithAi($original, $matched, $locale, $platform, $model, $template);
            $optimized = $this->normalizeRewrite($original, $rewritten);
        } catch (Throwable $error) {
            // Listing must not remain blocked solely because a configured AI
            // endpoint is temporarily unavailable. Preserve every other word
            // and only neutralize the exact managed terms; record the fallback
            // honestly so the result is never presented as AI-generated copy.
            report($error);
            $aiError = mb_substr($error->getMessage(), 0, 1000);
            $method = 'ai_fallback';
            $safeDescription = $this->sanitizeDescriptionPolicy($original['description']);
            $safeShortDescription = $this->formatReadableCopy(
                $this->sanitizeDescriptionPolicy($original['short_description'])
            ) ?: $this->formatReadableCopy($safeDescription);

            if ($safeShortDescription === '' || $this->formatReadableCopy($safeDescription) === '') {
                throw new RuntimeException('AI 不可用，且原文移除价格、地区、平台及销售信息后没有足够的安全商品描述。', previous: $error);
            }

            $optimized = [
                'name'              => $this->neutralize($original['name']),
                'short_description' => $safeShortDescription,
                'description'       => $safeDescription,
            ];
        }

        // The model is asked to remove every term, but enforce the policy
        // deterministically as a final guard before saving or listing.
        if ($this->scan($optimized) !== []) {
            $optimized['name'] = $this->neutralize($optimized['name']);
            $optimized['short_description'] = $this->formatReadableCopy($this->sanitizeDescriptionPolicy($optimized['short_description']));
            $optimized['description'] = $this->sanitizeDescriptionPolicy($optimized['description']);
        }

        $remaining = $this->scan($optimized);
        if ($remaining !== []) {
            throw new RuntimeException('AI 优化后仍包含违禁词：'.implode('、', $remaining));
        }

        $descriptionViolations = $this->descriptionPolicyViolations([
            'short_description' => $optimized['short_description'],
            'description'       => $optimized['description'],
        ]);

        if ($descriptionViolations !== []) {
            throw new RuntimeException('商品描述优化后仍包含：'.implode('、', $descriptionViolations));
        }

        $revisionId = $this->persistRevision(
            $product,
            $channel,
            $locale,
            $region,
            $matched,
            $original,
            $optimized,
            $platform,
            $model,
            $method,
            $template,
        );

        return [
            'changed'        => true,
            'revision_id'    => $revisionId,
            'matched_terms'  => $matched,
            'locale'         => $locale,
            'channel'        => $channel,
            'provider'       => $platform->label,
            'model'          => $model,
            'method'         => $method,
            'template_id'    => $template->id,
            'template_title' => $template->title,
            'ai_error'       => $aiError,
            'original'       => $original,
            'content'        => $optimized,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function descriptionTemplates(): array
    {
        return MagicAISystemPrompt::query()
            ->where('purpose', 'product_description')
            ->orderByDesc('is_enabled')
            ->orderBy('title')
            ->get()
            ->map(fn (MagicAISystemPrompt $prompt): array => [
                'id'          => $prompt->id,
                'title'       => $prompt->title,
                'content'     => $prompt->tone,
                'max_tokens'  => $prompt->max_tokens,
                'temperature' => $prompt->temperature,
                'is_default'  => (bool) $prompt->is_enabled,
            ])
            ->all();
    }

    /**
     * Optimize only Short Description with a user-selected managed template.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function optimizeShortDescription(
        Product $product,
        ?string $requestedLocale,
        ?string $requestedChannel,
        int $templateId,
        array $source = []
    ): array {
        [$channel, $locale, $stored] = $this->localizedContent($product, $requestedLocale, $requestedChannel);
        $original = array_merge($stored, array_intersect_key($source, $stored));
        $original = collect($original)->map(fn ($value): string => (string) $value)->all();
        $template = $this->descriptionTemplate($templateId);
        [$platform, $model] = $this->textPlatform();

        $shortText = trim(strip_tags((string) ($original['short_description'] ?? '')));
        $descHtml = (string) ($original['description'] ?? '');
        $descText = trim(html_entity_decode(strip_tags($descHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // 提取原 description 中的全部图片 <img>
        $images = [];
        preg_match_all('/<img\b[^>]*>/iu', $descHtml, $matches);
        foreach ($matches[0] ?? [] as $img) {
            $images[] = '<p>'.$img.'</p>';
        }

        // 综合简短描述与详细描述中的文字信息（若描述中仅有图片无文字，则仅以简短描述为输入）
        $sourceTextParts = array_values(array_filter([$shortText, $descText], fn ($t) => $t !== ''));
        $combinedText = implode("\n\n", array_unique($sourceTextParts));

        $payload = [
            'locale'     => $locale,
            'product'    => [
                'name'                 => trim(strip_tags($original['name'])),
                'short_description'    => $shortText,
                'description'          => $descText,
                'combined_description' => $combinedText,
                'attributes'           => data_get($product->values ?: [], 'common.source_attributes'),
            ],
        ];
        $shortDescription = $this->requestShortDescription($payload, $platform, $model, $template);

        $formattedParagraphs = $this->formatRichTextParagraphs($shortDescription);

        $optimized = $original;
        $optimized['short_description'] = $formattedParagraphs;
        // 将优化后的文本段落置于最前，原有详情图片保留在后部
        $optimized['description'] = $formattedParagraphs . implode('', $images);

        $revisionId = $this->persistRevision(
            $product,
            $channel,
            $locale,
            null,
            $this->scan($original),
            $original,
            $optimized,
            $platform,
            $model,
            'ai_description',
            $template,
            ['short_description', 'description'],
        );

        return [
            'changed'           => true,
            'revision_id'       => $revisionId,
            'short_description' => $optimized['short_description'],
            'description'       => $optimized['description'],
            'locale'            => $locale,
            'channel'           => $channel,
            'provider'          => $platform->label,
            'model'             => $model,
            'method'            => 'ai_description',
            'template_id'       => $template->id,
            'template_title'    => $template->title,
            'original'          => $original,
            'content'           => $optimized,
            'created_at'        => now()->toDateTimeString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function nameTemplates(): array
    {
        return MagicAISystemPrompt::query()
            ->where('purpose', 'product_name')
            ->orderByDesc('is_enabled')
            ->orderBy('title')
            ->get()
            ->map(fn (MagicAISystemPrompt $prompt): array => [
                'id'          => $prompt->id,
                'title'       => $prompt->title,
                'content'     => $prompt->tone,
                'max_tokens'  => $prompt->max_tokens,
                'temperature' => $prompt->temperature,
                'is_default'  => (bool) $prompt->is_enabled,
            ])
            ->all();
    }

    /**
     * Optimize only Product Name with a user-selected managed template.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function optimizeProductName(
        Product $product,
        ?string $requestedLocale,
        ?string $requestedChannel,
        int $templateId,
        array $source = []
    ): array {
        [$channel, $locale, $stored] = $this->localizedContent($product, $requestedLocale, $requestedChannel);
        $original = array_merge($stored, array_intersect_key($source, $stored));
        $original = collect($original)->map(fn ($value): string => (string) $value)->all();
        $template = $this->nameTemplate($templateId);
        [$platform, $model] = $this->textPlatform();
        $payload = [
            'locale'     => $locale,
            'product'    => [
                'name'              => trim(strip_tags($original['name'])),
                'short_description' => trim(strip_tags($original['short_description'])),
                'description'       => trim(html_entity_decode(strip_tags($original['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'attributes'        => data_get($product->values ?: [], 'common.source_attributes'),
            ],
        ];
        $optimizedName = $this->requestProductName($payload, $platform, $model, $template);

        $optimized = $original;
        $optimized['name'] = $optimizedName;

        $revisionId = $this->persistRevision(
            $product,
            $channel,
            $locale,
            null,
            $this->scan($original),
            $original,
            $optimized,
            $platform,
            $model,
            'ai_name',
            $template,
            ['name'],
        );

        return [
            'changed'        => true,
            'revision_id'    => $revisionId,
            'name'           => $optimized['name'],
            'locale'         => $locale,
            'channel'        => $channel,
            'provider'       => $platform->label,
            'model'          => $model,
            'method'         => 'ai_name',
            'template_id'    => $template->id,
            'template_title' => $template->title,
            'original'       => $original,
            'content'        => $optimized,
            'created_at'     => now()->toDateTimeString(),
        ];
    }

    /** @return array{0: string, 1: string, 2: array<string, string>} */
    protected function localizedContent(Product $product, ?string $requestedLocale, ?string $requestedChannel = null): array
    {
        $channels = (array) data_get($product->values ?: [], 'channel_locale_specific', []);
        $channel = $requestedChannel && array_key_exists($requestedChannel, $channels)
            ? $requestedChannel
            : (array_key_exists('default', $channels) ? 'default' : (string) array_key_first($channels));
        $localeValues = (array) ($channels[$channel] ?? []);
        $locale = collect([$requestedLocale, 'zh_CN', 'en_US', array_key_first($localeValues)])
            ->filter()
            ->first(fn (string $candidate): bool => is_array($localeValues[$candidate] ?? null));

        if (! $channel || ! $locale) {
            throw new RuntimeException('商品没有可用于违禁词检查的本地化描述。');
        }

        $values = (array) $localeValues[$locale];

        return [$channel, $locale, [
            'name'              => (string) ($values['name'] ?? ''),
            'short_description' => (string) ($values['short_description'] ?? ''),
            'description'       => (string) ($values['description'] ?? ''),
        ]];
    }

    /** @return array{0: MagicAIPlatform, 1: string} */
    protected function textPlatform(): array
    {
        $platform = MagicAIPlatform::query()
            ->where('status', true)
            ->where('provider', '!=', AiProvider::ZhipuCodePlan->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->first(fn (MagicAIPlatform $candidate): bool => $candidate->model_list !== []);

        if (! $platform) {
            throw new RuntimeException('没有可用的 Magic AI 通用文本平台。');
        }

        $model = collect($platform->model_list)
            ->first(fn (string $name): bool => str_contains(strtolower($name), 'flash'))
            ?: $platform->model_list[0];

        return [$platform, $model];
    }

    /**
     * @param  array<string, string>  $original
     * @param  array<int, string>  $matched
     * @return array<string, mixed>
     */
    protected function rewriteWithAi(
        array $original,
        array $matched,
        string $locale,
        MagicAIPlatform $platform,
        string $model,
        MagicAISystemPrompt $template
    ): array {
        $visible = [
            'name'              => trim(strip_tags($original['name'])),
            'short_description' => trim(strip_tags($original['short_description'])),
            'description'       => trim(html_entity_decode(strip_tags($original['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ];
        $payload = [
            'locale'          => $locale,
            'forbidden_words' => $matched,
            'product'         => $visible,
        ];
        $feedback = '';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = magic_ai()
                ->setPlatformId($platform->id)
                ->setModel($model)
                ->setTemperature((float) $template->temperature)
                ->setMaxTokens((int) $template->max_tokens)
                ->setSystemPrompt($this->descriptionSystemPrompt($template))
                ->setPrompt(
                    '改写商品文案，确保 forbidden_words 中的词不再出现。只返回 JSON：'
                    .'{"name":"","short_description":"","description":""}。description 返回纯文本并使用空行分隔自然段。'
                    .$feedback."\n"
                    .mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 30000),
                    'text'
                )
                ->ask();
            $decoded = $this->decodeJson($response);
            $decoded['short_description'] = $this->formatReadableCopy((string) ($decoded['short_description'] ?? ''));
            $decoded['description'] = $this->formatReadableCopy((string) ($decoded['description'] ?? ''));
            $violations = $this->descriptionPolicyViolations([
                'short_description' => $decoded['short_description'],
                'description'       => $decoded['description'],
            ]);

            if ($violations === []) {
                return $decoded;
            }

            $feedback = '\n上一版仍然包含'.implode('、', $violations).'；本次必须删除相关句子，不得换一种说法继续保留。';
        }

        throw new RuntimeException('AI 优化后的商品描述仍包含：'.implode('、', $violations));
    }

    protected function descriptionTemplate(?int $id = null): MagicAISystemPrompt
    {
        $query = MagicAISystemPrompt::query()->where('purpose', 'product_description');
        $template = $id
            ? (clone $query)->whereKey($id)->first()
            : (clone $query)->where('is_enabled', true)->first();
        $template ??= $query->orderBy('id')->first();

        if (! $template) {
            throw new RuntimeException('没有可用的商品描述优化提示词，请先在 Magic AI → System Prompts 中创建。');
        }

        return $template;
    }

    protected function descriptionSystemPrompt(MagicAISystemPrompt $template): string
    {
        return trim($template->tone)."\n\n必须遵守：只描述商品自身可验证的信息；不得出现任何价格、金额、币种、折扣或促销信息；不得出现国家、城市、产地、发货地、目标市场或销售地区；不得出现任何电商平台、店铺、销售渠道、上架、批发、转售或销售目的信息。使用自然段组织内容，段落之间用空行分隔；每个句子必须有完整标点，表达自然、通顺、人类可读，不得堆砌关键词或虚构事实。";
    }

    protected function nameTemplate(?int $id = null): MagicAISystemPrompt
    {
        $query = MagicAISystemPrompt::query()->where('purpose', 'product_name');
        $template = $id
            ? (clone $query)->whereKey($id)->first()
            : (clone $query)->where('is_enabled', true)->first();
        $template ??= $query->orderBy('id')->first();

        if (! $template) {
            throw new RuntimeException('没有可用的商品名称优化提示词，请先在 Magic AI → System Prompts 中创建。');
        }

        return $template;
    }

    protected function nameSystemPrompt(MagicAISystemPrompt $template): string
    {
        return trim($template->tone)."\n\n必须遵守："
            ."1. 【绝对禁止货源表述】：严禁提及“厂家”、“工厂”、“源头工厂”、“直销”、“厂家直销”、“一手货源”、“批发”、“代发”、“一件代发”、“代工”、“加工”、“定制”等任何形式的货源、供应或批发表述；\n"
            ."2. 【特性功能联想与深度拓展】：必须充分挖掘并联想商品的实用特性与扩张功能、适用场景、痛点解决、多用途搭配及核心优势，突出产品为消费者带来的实际价值与品质体验；\n"
            ."3. 【禁止价格与平台信息】：严禁出现任何价格、金额、币种、折扣、促销词；严禁提及任何电商平台名称（如淘宝、1688、拼多多、天猫、京东、亚马逊、Shopee、Lazada、TikTok等）或店铺、上架等内部信息；\n"
            ."4. 【真实客观与合规】：严禁虚构不存在的参数或功效，严禁使用国家广告法禁用的极限词；\n"
            ."5. 【格式与长度要求】：仅输出单行商品名称文本，不得换行，长度严格控制在 60 至 120 个字符以内。";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function requestShortDescription(
        array $payload,
        MagicAIPlatform $platform,
        string $model,
        MagicAISystemPrompt $template
    ): string {
        $feedback = '';
        $violations = [];
        $locale = (string) ($payload['locale'] ?? 'zh_CN');
        $language = $this->descriptionLanguageInstruction($locale);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = magic_ai()
                ->setPlatformId($platform->id)
                ->setModel($model)
                ->setTemperature((float) $template->temperature)
                ->setMaxTokens((int) $template->max_tokens)
                ->setSystemPrompt($this->descriptionSystemPrompt($template))
                ->setPrompt(
                    '请优化商品描述（综合参考简短描述与详细描述中的文字信息）。只返回 JSON：{"short_description":""}。不得返回 Markdown 或解释。'
                    ."目标语言：{$language}。全部内容必须使用目标语言，不得改用英文或其他语言。"
                    .'生成 2 至 4 个自然段，每段 1 至 2 个完整句子；JSON 字符串内使用 \n\n 分隔段落。'
                    .'每句话使用完整标点。'.$feedback."\n"
                    .mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 30000),
                    'text'
                )
                ->ask();
            $decoded = $this->decodeJson($response);
            $shortDescription = $this->formatReadableCopy((string) ($decoded['short_description'] ?? ''));

            if ($shortDescription === '') {
                $violations = ['空内容'];
            } else {
                $violations = array_values(array_unique(array_merge(
                    $this->descriptionPolicyViolations(['short_description' => $shortDescription]),
                    $this->descriptionPresentationViolations($shortDescription, $locale),
                )));
            }

            if ($violations === []) {
                return $shortDescription;
            }

            $feedback = '\n上一版仍存在以下问题：'.implode('、', $violations).'。请严格按目标语言和段落要求重新生成。';
        }

        throw new RuntimeException('AI 优化后的 Short Description 仍不符合要求：'.implode('、', $violations));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function requestProductName(
        array $payload,
        MagicAIPlatform $platform,
        string $model,
        MagicAISystemPrompt $template
    ): string {
        $feedback = '';
        $violations = [];
        $locale = (string) ($payload['locale'] ?? 'zh_CN');
        $language = $this->descriptionLanguageInstruction($locale);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = magic_ai()
                ->setPlatformId($platform->id)
                ->setModel($model)
                ->setTemperature((float) $template->temperature)
                ->setMaxTokens((int) $template->max_tokens)
                ->setSystemPrompt($this->nameSystemPrompt($template))
                ->setPrompt(
                    '请优化商品名称（Title/Name）。只返回 JSON：{"name":""}。不得返回 Markdown 或解释。'
                    ."目标语言：{$language}。全部内容必须使用目标语言，不得改用英文或其他语言。"
                    .'生成单行吸引消费者的电商商品标题，深度联想与拓展特性的扩张功能，长度在 60 至 120 个字符之间。'
                    .'【严禁提及厂家、工厂、直销、一手货源、批发、代发等词汇】。'.$feedback."\n"
                    .mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 30000),
                    'text'
                )
                ->ask();
            $decoded = $this->decodeJson($response);
            $name = trim(strip_tags((string) ($decoded['name'] ?? '')));
            $name = preg_replace('/[\r\n]+/u', ' ', $name) ?? $name;
            $name = trim($name, " \t\n\r\0\x0B\"'“”‘’");

            $violations = $this->namePolicyViolations($name, $locale);

            if ($violations === []) {
                return $name;
            }

            $feedback = '\n上一版存在以下违规问题：'.implode('、', $violations).'。请严格排除厂家直销等违规词，拓展功能特性，重新生成合规商品名称。';
        }

        throw new RuntimeException('AI 优化后的商品名称仍不符合要求：'.implode('、', $violations));
    }

    /** @return array<int, string> */
    public function namePolicyViolations(string $name, string $locale = 'zh_CN'): array
    {
        $visible = trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $violations = [];

        if ($visible === '') {
            return ['空名称'];
        }

        if (preg_match('/[\r\n]/u', $name)) {
            $violations[] = '包含换行符（必须为单行标题）';
        }

        $hasKnownPlatform = collect(config('content_policy.default_forbidden_words', []))
            ->contains(fn (string $word): bool => preg_match($this->pattern($word), $visible) === 1);
        if ($hasKnownPlatform || $this->scan(['name' => $visible]) !== [] || preg_match('/(?:电商|购物|销售|线上)平台|销售渠道|线上商城|网店|店铺|marketplace|e[ -]?commerce platform|online (?:platform|store|shop)|1688|淘宝|天猫|京东|拼多多|亚马逊|虾皮|来赞达/iu', $visible)) {
            $violations[] = '电商平台或销售渠道信息';
        }

        if (preg_match('/(?:价格|售价|单价|零售价|批发价|促销价|优惠价|折扣价|到手价|price|priced|pricing|cost)\s*[:：]?\s*(?:[A-Z]{3}|RM|RMB|[$¥￥€£])?\s*\d|[$¥￥€£]\s*\d|\b(?:RM|MYR|THB|USD|CNY|RMB|EUR|GBP)\b\s*[:：]?\s*\d|\d+(?:[.,]\d+)?\s*(?:元|人民币|美元|美金|马币|令吉|泰铢|บาท|\b(?:RM|MYR|THB|USD|CNY|RMB|EUR|GBP)\b)/iu', $visible)) {
            $violations[] = '价格、金额或币种信息';
        }

        if (preg_match('/(?:产地|原产地|原产国|制造地|生产地|发货地|供应商所在地|销售地区|销售区域|目标市场|销往|面向.{0,12}(?:市场|地区)|(?:适合|适用于).{0,12}(?:市场|地区))|\b(?:Malaysia|Thailand|China|Vietnam|Indonesia|Singapore|United States|USA|MY|TH|CN|VN|ID|SG)\b|马来西亚|泰国|中国|越南|印度尼西亚|印尼|新加坡|美国/iu', $visible)) {
            $violations[] = '国家、地区、产地或目标市场信息';
        }

        if (preg_match('/(?:厂家|工厂|源头工[厂单]|直销|厂[家直]直销|一手货源|批[发发]|支持一件代发|一件代发|一件起批|代工|代发|加工|定制货源|源头好货|源头直供|厂价)/iu', $visible)) {
            $violations[] = '厂家、直销、货源或批发表述';
        }

        if (str_starts_with(strtolower($locale), 'zh') && preg_match_all('/\p{Han}/u', $visible) < 6) {
            $violations[] = '未使用简体中文';
        }

        if (mb_strlen($visible) < 10) {
            $violations[] = '名称过短（少于 10 个字符）';
        }

        return array_values(array_unique($violations));
    }

    /** @param array<string, mixed> $content */
    protected function descriptionPolicyViolations(array $content): array
    {
        $visible = collect($content)
            ->filter(fn (mixed $value): bool => is_scalar($value) || $value instanceof \Stringable)
            ->map(fn (mixed $value): string => html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->implode("\n");
        $violations = [];
        $hasKnownPlatform = collect(config('content_policy.default_forbidden_words', []))
            ->contains(fn (string $word): bool => preg_match($this->pattern($word), $visible) === 1);

        if ($hasKnownPlatform || $this->scan($content) !== [] || preg_match('/(?:电商|购物|销售|线上)平台|销售渠道|线上商城|网店|店铺|marketplace|e[ -]?commerce platform|online (?:platform|store|shop)/iu', $visible)) {
            $violations[] = '电商平台或销售渠道信息';
        }

        if (preg_match('/(?:价格|售价|单价|零售价|批发价|促销价|优惠价|折扣价|到手价|price|priced|pricing|cost)\s*[:：]?\s*(?:[A-Z]{3}|RM|RMB|[$¥￥€£])?\s*\d|[$¥￥€£]\s*\d|\b(?:RM|MYR|THB|USD|CNY|RMB|EUR|GBP)\b\s*[:：]?\s*\d|\d+(?:[.,]\d+)?\s*(?:元|人民币|美元|美金|马币|令吉|泰铢|บาท|\b(?:RM|MYR|THB|USD|CNY|RMB|EUR|GBP)\b)/iu', $visible)) {
            $violations[] = '价格、金额或币种信息';
        }

        if (preg_match('/(?:产地|原产地|原产国|制造地|生产地|发货地|供应商所在地|销售地区|销售区域|目标市场|销往|面向.{0,12}(?:市场|地区)|(?:适合|适用于).{0,12}(?:市场|地区))|\b(?:Malaysia|Thailand|China|Vietnam|Indonesia|Singapore|United States|USA|MY|TH|CN|VN|ID|SG)\b|马来西亚|泰国|中国|越南|印度尼西亚|印尼|新加坡|美国/iu', $visible)) {
            $violations[] = '国家、地区、产地或目标市场信息';
        }

        if (preg_match('/(?:适合|适用于|用于|面向).{0,24}(?:销售|转售|批发|零售|上架)|(?:销售|上架|转售|批发|零售)(?:用途|目的|渠道|平台|店铺|市场)|(?:sell|selling|resale|wholesale|retail|listing).{0,30}(?:platform|marketplace|store|shop|purpose|channel)/iu', $visible)) {
            $violations[] = '销售目的或上架信息';
        }

        return array_values(array_unique($violations));
    }

    protected function formatReadableCopy(string $value): string
    {
        $text = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $value) ?? $value;
        $text = preg_replace('/<\/\s*(?:p|div|li|h[1-6])\s*>/iu', "\n\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split('/\n+/u', $text) ?: [];

        if (count(array_filter($paragraphs, 'trim')) === 1) {
            $sentences = preg_split('/(?<=[。！？!?])\s*|(?<=\.)\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($sentences) > 1) {
                $paragraphs = $sentences;
            }
        }

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => preg_replace('/[\p{Z}\t]+/u', ' ', trim($paragraph)) ?? trim($paragraph))
            ->filter()
            ->map(function (string $paragraph): string {
                if (preg_match('/[。！？.!?；;：:]$/u', $paragraph)) {
                    return $paragraph;
                }

                return $paragraph.(preg_match('/[\x{3400}-\x{9fff}]/u', $paragraph) ? '。' : '.');
            })
            ->implode("\n\n");
    }

    protected function formatRichTextParagraphs(string $value): string
    {
        return collect(explode("\n\n", $this->formatReadableCopy($value)))
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->map(fn (string $paragraph): string => '<p>'.htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>')
            ->implode('');
    }

    /** @return array<int, string> */
    protected function descriptionPresentationViolations(string $value, string $locale): array
    {
        $violations = [];
        $paragraphs = preg_split('/\n{2,}/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($paragraphs) < 2) {
            $violations[] = '缺少自然段';
        }

        if (str_starts_with(strtolower($locale), 'zh') && preg_match_all('/\p{Han}/u', $value) < 6) {
            $violations[] = '未使用简体中文';
        }

        return $violations;
    }

    protected function descriptionLanguageInstruction(string $locale): string
    {
        $normalized = strtolower($locale);

        return match (true) {
            str_starts_with($normalized, 'zh') => '简体中文',
            str_starts_with($normalized, 'ms') => '马来语',
            str_starts_with($normalized, 'th') => '泰语',
            str_starts_with($normalized, 'en') => '英语',
            default                            => $locale,
        };
    }

    /**
     * @param  array<int, string>  $matched
     * @param  array<string, string>  $original
     * @param  array<string, string>  $optimized
     */
    protected function persistRevision(
        Product $product,
        string $channel,
        string $locale,
        ?string $region,
        array $matched,
        array $original,
        array $optimized,
        MagicAIPlatform $platform,
        string $model,
        string $method,
        MagicAISystemPrompt $template,
        ?array $fieldsToPersist = null,
    ): int {
        return DB::transaction(function () use ($product, $channel, $locale, $region, $matched, $original, $optimized, $platform, $model, $method, $template, $fieldsToPersist): int {
            $values = $product->values ?: [];
            $localized = (array) data_get($values, "channel_locale_specific.$channel.$locale", []);
            $persisted = $fieldsToPersist
                ? array_intersect_key($optimized, array_flip($fieldsToPersist))
                : $optimized;
            data_set($values, "channel_locale_specific.$channel.$locale", array_merge($localized, $persisted));

            if (isset($persisted['name']) && ! empty($persisted['name'])) {
                $currentOriginalName = data_get($values, 'common.original_name');
                if (empty($currentOriginalName) && ! empty($original['name'])) {
                    data_set($values, 'common.original_name', $original['name']);
                }

                $allChannels = (array) data_get($values, 'channel_locale_specific', []);
                foreach ($allChannels as $ch => $locales) {
                    if (! is_array($locales)) {
                        continue;
                    }
                    foreach ($locales as $loc => $fields) {
                        if ($ch === $channel && $loc === $locale) {
                            continue;
                        }
                        $otherName = $fields['name'] ?? null;
                        if ($otherName === ($original['name'] ?? null) || empty($otherName)) {
                            data_set($values, "channel_locale_specific.$ch.$loc.name", $persisted['name']);
                        }
                    }
                }
            }

            $product->values = $values;
            $product->save();

            return (int) DB::table('product_content_revisions')->insertGetId([
                'product_id'        => $product->id,
                'platform'          => 'tiktok',
                'region'            => $region ? strtoupper($region) : null,
                'locale'            => $locale,
                'matched_terms'     => json_encode($matched, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'original_content'  => json_encode($original, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'optimized_content' => json_encode($optimized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider'          => $platform->label,
                'model'             => $model,
                'method'            => $method,
                'template_id'       => $template->id,
                'template_title'    => $template->title,
                'prompt_snapshot'   => $this->descriptionSystemPrompt($template),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });
    }

    /**
     * @param  array<string, string>  $original
     * @param  array<string, mixed>  $rewritten
     * @return array<string, string>
     */
    protected function normalizeRewrite(array $original, array $rewritten): array
    {
        $description = $this->formatReadableCopy((string) ($rewritten['description'] ?? ''));
        $shortDescription = $this->formatReadableCopy((string) ($rewritten['short_description'] ?? ''));
        $images = [];
        preg_match_all('/<img\b[^>]*>/iu', $original['description'], $matches);
        foreach ($matches[0] ?? [] as $image) {
            $images[] = '<p>'.$image.'</p>';
        }

        $descriptionHtml = collect(explode("\n\n", $description))
            ->filter()
            ->map(fn (string $paragraph): string => '<p>'.htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>')
            ->implode('');

        return [
            'name'              => trim((string) ($rewritten['name'] ?? '')) ?: $original['name'],
            'short_description' => $shortDescription ?: $original['short_description'],
            'description'       => ($description !== ''
                ? $descriptionHtml
                : $original['description']).implode('', $images),
        ];
    }

    protected function sanitizeDescriptionPolicy(string $value): string
    {
        $segments = preg_split('/(<[^>]+>)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];

        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, '<')) {
                continue;
            }

            $clauses = preg_split('/(?<=[，,。！？.!?；;\n])/u', $segment, -1, PREG_SPLIT_NO_EMPTY) ?: [$segment];
            $segments[$index] = collect($clauses)
                ->reject(fn (string $clause): bool => $this->descriptionPolicyViolations(['text' => $clause]) !== [])
                ->implode('');
        }

        return trim(implode('', $segments));
    }

    protected function neutralize(string $value): string
    {
        $segments = preg_split('/(<[^>]+>)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];

        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, '<')) {
                continue;
            }

            $replacement = preg_match('/[\x{4e00}-\x{9fff}]/u', $segment)
                ? '商品'
                : 'product';

            foreach ($this->forbiddenWords() as $word) {
                $segment = preg_replace($this->pattern($word), $replacement, $segment) ?? $segment;
            }

            $quoted = preg_quote($replacement, '/');
            $segment = preg_replace(
                '/(?:'.$quoted.'(?:\s*[、,，\/]|\s+(?:and|or)|\s*[和与及])\s*)+'.$quoted.'/iu',
                $replacement,
                $segment
            ) ?? $segment;

            $segments[$index] = $segment;
        }

        return implode('', $segments);
    }

    protected function pattern(string $word): string
    {
        $escaped = preg_quote($word, '/');
        $latinOrNumber = preg_match('/^[\p{Latin}\p{N}. _-]+$/u', $word) === 1;

        return $latinOrNumber
            ? '/(?<![\p{L}\p{N}])'.$escaped.'(?![\p{L}\p{N}])/iu'
            : '/'.$escaped.'/iu';
    }

    /** @return array<string, mixed> */
    protected function decodeJson(string $response): array
    {
        $decoded = json_decode(trim($response), true);

        if (! is_array($decoded) && preg_match('/\{.*\}/su', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('AI 返回的商品文案不是有效 JSON。');
        }

        return $decoded;
    }
}
