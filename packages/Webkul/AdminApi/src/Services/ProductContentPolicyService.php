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
            $optimized = collect($original)
                ->map(fn (string $value): string => $this->neutralize($value))
                ->all();
        }

        // The model is asked to remove every term, but enforce the policy
        // deterministically as a final guard before saving or listing.
        if ($this->scan($optimized) !== []) {
            $optimized = collect($optimized)
                ->map(fn (string $value): string => $this->neutralize($value))
                ->all();
        }

        $remaining = $this->scan($optimized);
        if ($remaining !== []) {
            throw new RuntimeException('AI 优化后仍包含违禁词：'.implode('、', $remaining));
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
        $payload = [
            'locale'     => $locale,
            'product'    => [
                'name'              => trim(strip_tags($original['name'])),
                'short_description' => trim(strip_tags($original['short_description'])),
                'description'       => trim(html_entity_decode(strip_tags($original['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'attributes'        => data_get($product->values ?: [], 'common.source_attributes'),
            ],
        ];
        $response = magic_ai()
            ->setPlatformId($platform->id)
            ->setModel($model)
            ->setTemperature((float) $template->temperature)
            ->setMaxTokens((int) $template->max_tokens)
            ->setSystemPrompt($this->descriptionSystemPrompt($template))
            ->setPrompt(
                '请优化 Short Description。只返回 JSON：{"short_description":""}。不得返回 Markdown 或解释。'."\n"
                .mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 30000),
                'text'
            )
            ->ask();
        $decoded = $this->decodeJson($response);
        $shortDescription = trim(strip_tags((string) ($decoded['short_description'] ?? '')));

        if ($shortDescription === '') {
            throw new RuntimeException('AI 没有返回有效的 Short Description。');
        }

        $optimized = $original;
        $optimized['short_description'] = $this->neutralize($shortDescription);
        $remaining = $this->scan(['short_description' => $optimized['short_description']]);

        if ($remaining !== []) {
            throw new RuntimeException('AI 优化后的 Short Description 仍包含违禁词：'.implode('、', $remaining));
        }

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
            ['short_description'],
        );

        return [
            'changed'           => true,
            'revision_id'       => $revisionId,
            'short_description' => $optimized['short_description'],
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
        $response = magic_ai()
            ->setPlatformId($platform->id)
            ->setModel($model)
            ->setTemperature((float) $template->temperature)
            ->setMaxTokens((int) $template->max_tokens)
            ->setSystemPrompt($this->descriptionSystemPrompt($template))
            ->setPrompt(
                '改写商品文案，确保 forbidden_words 中的词不再出现。只返回 JSON：'
                .'{"name":"","short_description":"","description":""}。description 返回纯文本。\n'
                .mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 30000),
                'text'
            )
            ->ask();

        return $this->decodeJson($response);
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
        return trim($template->tone)."\n\n必须遵守：只描述商品自身信息；不得提及产地、销售目的、销售平台或销售渠道；句子完整通顺；不得虚构事实。";
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
                'prompt_snapshot'   => $template->tone,
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
        $description = trim(strip_tags((string) ($rewritten['description'] ?? '')));
        $images = [];
        preg_match_all('/<img\b[^>]*>/iu', $original['description'], $matches);
        foreach ($matches[0] ?? [] as $image) {
            $images[] = '<p>'.$image.'</p>';
        }

        return [
            'name'              => trim((string) ($rewritten['name'] ?? '')) ?: $original['name'],
            'short_description' => trim((string) ($rewritten['short_description'] ?? '')) ?: $original['short_description'],
            'description'       => ($description !== ''
                ? '<p>'.nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>'
                : $original['description']).implode('', $images),
        ];
    }

    protected function neutralize(string $value): string
    {
        $segments = preg_split('/(<[^>]+>)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$value];

        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, '<')) {
                continue;
            }

            $replacement = preg_match('/[\x{4e00}-\x{9fff}]/u', $segment)
                ? '线上销售渠道'
                : 'online marketplace';

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
