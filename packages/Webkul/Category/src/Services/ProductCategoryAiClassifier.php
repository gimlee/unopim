<?php

namespace Webkul\Category\Services;

use Illuminate\Support\Collection;
use RuntimeException;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\PlatformCategory;
use Webkul\MagicAI\Enums\AiProvider;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\MagicAI\Models\MagicAISystemPrompt;
use Webkul\Product\Models\Product;

class ProductCategoryAiClassifier
{
    /**
     * Ask the selected Magic AI platform to choose only from a bounded,
     * deterministic candidate set. The model never receives the whole tree and
     * can never invent an ID that is accepted by the caller.
     */
    public function classify(Product $product, MagicAIPlatform $platform, string $model): array
    {
        if ($platform->provider === AiProvider::ZhipuCodePlan->value) {
            throw new RuntimeException('智谱 Coding Plan 仅适用于官方支持的编码工具；商品 AI 分类请使用“智谱 AI（通用 API）”。');
        }

        if (! $platform->status) {
            throw new RuntimeException('所选 Magic AI 平台未启用。');
        }

        if (! in_array($model, $platform->model_list, true)) {
            throw new RuntimeException('所选模型不属于该 Magic AI 平台。');
        }

        $summary = $this->productSummary($product);
        $standardCandidates = $this->standardCandidates($summary, $product);
        $platformCandidates = $this->platformCandidates($summary, $standardCandidates);

        if ($standardCandidates->isEmpty() || $platformCandidates->isEmpty()) {
            throw new RuntimeException('没有足够的本地类目候选，无法执行 AI 分类。');
        }

        $payload = [
            'product'                 => $summary,
            'pim_standard_candidates' => $standardCandidates->map(fn (array $item): array => [
                'code' => $item['value'],
                'path' => $item['path'],
            ])->values()->all(),
            'tiktok_candidates' => $platformCandidates->map(fn (array $item): array => [
                'external_id' => $item['value'],
                'path'        => $item['path'],
            ])->values()->all(),
        ];
        $systemPrompt = MagicAISystemPrompt::query()
            ->where('purpose', 'category_classification')
            ->where('is_enabled', true)
            ->first();

        $response = magic_ai()
            ->setPlatformId($platform->id)
            ->setModel($model)
            ->setTemperature((float) ($systemPrompt?->temperature ?? 0.1))
            ->setMaxTokens((int) ($systemPrompt?->max_tokens ?? 400))
            ->setSystemPrompt($systemPrompt?->tone ?: '你是电商商品类目审核助手。只能从用户提供的候选列表中选择叶子类目，不允许创造、改写或猜测任何 ID。')
            ->setPrompt(
                '根据商品信息，从候选中分别选择一个最合适的 PIM 标准类目和 TikTok 类目。'
                ."只返回 JSON：{\"standard_category_code\":\"\",\"platform_category_external_id\":\"\",\"confidence\":0.0,\"reason\":\"\"}。\n"
                .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'text'
            )
            ->ask();

        $decoded = $this->decodeJson($response);
        $standard = $standardCandidates->firstWhere('value', (string) ($decoded['standard_category_code'] ?? ''));
        $tiktok = $platformCandidates->firstWhere('value', (string) ($decoded['platform_category_external_id'] ?? ''));

        if (! $standard || ! $tiktok) {
            throw new RuntimeException('AI 返回了候选范围以外的类目，结果已拒绝，请重试或手动选择。');
        }

        return [
            'standard'   => $standard,
            'platform'   => $tiktok,
            'confidence' => max(0, min(1, (float) ($decoded['confidence'] ?? 0))),
            'reason'     => mb_substr(trim((string) ($decoded['reason'] ?? '')), 0, 500),
            'model'      => $model,
            'provider'   => $platform->label,
        ];
    }

    protected function standardCandidates(string $summary, Product $product): Collection
    {
        $current = (array) data_get($product->values, 'categories', []);

        return Category::query()
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->where('code', '!=', 'std_uncategorized')
            ->get(['id', 'code', 'source_path', 'additional_data'])
            ->map(fn (Category $category): array => [
                'id'    => $category->id,
                'value' => $category->code,
                'path'  => $category->source_path ?: $category->name,
                'score' => $this->score($summary, $category->source_path ?: $category->name)
                    + (in_array($category->code, $current, true) ? 1000 : 0),
            ])
            ->sortByDesc('score')
            ->take(80)
            ->values();
    }

    protected function platformCandidates(string $summary, Collection $standardCandidates): Collection
    {
        $standardIds = $standardCandidates->take(20)->pluck('id');
        $mappedIds = CategoryMapping::query()
            ->whereIn('category_id', $standardIds)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereHas('platformCategory.taxonomy', fn ($query) => $query
                ->where('platform', 'tiktok')
                ->where('status', 'active'))
            ->orderByRaw("CASE status WHEN 'confirmed' THEN 0 ELSE 1 END")
            ->orderByRaw('confidence DESC NULLS LAST')
            ->pluck('platform_category_id');

        return PlatformCategory::query()
            ->where('is_leaf', true)
            ->where('enabled', true)
            ->whereHas('taxonomy', fn ($query) => $query
                ->where('platform', 'tiktok')
                ->where('status', 'active'))
            ->get(['id', 'external_id', 'path', 'name'])
            ->map(fn (PlatformCategory $category): array => [
                'id'    => $category->id,
                'value' => $category->external_id,
                'path'  => $category->path,
                'score' => $this->score($summary, $category->path)
                    + ($mappedIds->contains($category->id) ? 1000 : 0),
            ])
            ->sortByDesc('score')
            ->unique('value')
            ->take(80)
            ->values();
    }

    protected function productSummary(Product $product): string
    {
        $values = $product->resolvedValues();
        $preferred = data_get($values, 'channel_locale_specific.default.zh_CN')
            ?: data_get($values, 'channel_locale_specific.default.en_US')
            ?: [];
        $common = (array) data_get($values, 'common', []);
        $parts = [
            'SKU: '.$product->sku,
            '标题: '.strip_tags((string) ($preferred['name'] ?? '')),
            '短描述: '.strip_tags((string) ($preferred['short_description'] ?? '')),
            '来源属性: '.strip_tags((string) ($common['source_attributes'] ?? '')),
            '现有平台类目提示: '.strip_tags((string) ($common['tiktok_category_path'] ?? '')),
        ];

        return mb_substr(preg_replace('/\s+/u', ' ', implode("\n", $parts)) ?: '', 0, 7000);
    }

    protected function score(string $product, string $path): float
    {
        $product = mb_strtolower($product);
        $segments = preg_split('/\s*(?:>|\/|、|&|，|,|与|和)\s*/u', mb_strtolower($path)) ?: [];
        $score = 0.0;
        foreach ($segments as $segment) {
            $segment = trim($segment, " \t\n\r\0\x0B（）()[]【】");
            if (mb_strlen($segment) < 2) {
                continue;
            }
            if (str_contains($product, $segment)) {
                $score += 20 + mb_strlen($segment);
            }
            for ($index = 0; $index < mb_strlen($segment) - 1; $index++) {
                $bigram = mb_substr($segment, $index, 2);
                if (str_contains($product, $bigram)) {
                    $score += 1;
                }
            }
        }

        return $score;
    }

    protected function decodeJson(string $response): array
    {
        $response = trim($response);
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/su', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('AI 返回格式不是有效 JSON，请重试。');
        }

        return $decoded;
    }
}
