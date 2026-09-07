<?php

namespace Webkul\Category\Services;

use Illuminate\Support\Collection;
use RuntimeException;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\CategoryMapping;
use Webkul\Category\Models\CategorySourceMapping;
use Webkul\Product\Models\Product;

class ProductCategoryRuleClassifier
{
    public function classify(Product $product): array
    {
        $values = $product->resolvedValues();
        $common = (array) data_get($values, 'common', []);
        $localized = (array) data_get($values, 'channel_locale_specific.default', []);
        $text = mb_strtolower(preg_replace('/\s+/u', ' ', implode(' ', array_filter([
            $product->sku,
            data_get($localized, 'zh_CN.name'),
            data_get($localized, 'en_US.name'),
            data_get($localized, 'zh_CN.short_description'),
            data_get($localized, 'en_US.short_description'),
            $common['source_attributes'] ?? null,
            $common['source_category_path'] ?? null,
        ]))) ?: '');
        $sourcePath = trim((string) ($common['source_category_path'] ?? ''));

        $exact = $this->sourceMappedCategory($sourcePath);
        $ranked = $exact
            ? collect([['category' => $exact, 'score' => 1000.0, 'evidence' => ["1688 类目精确映射：{$sourcePath}"]]])
            : $this->rankCandidates($text);
        $winner = $ranked->first();
        if (! $winner) {
            throw new RuntimeException('规则分类没有命中可用的 PIM 标准叶子类目；请补充类目别名或分类规则。');
        }

        /** @var Category $category */
        $category = $winner['category'];
        $runnerUp = $ranked->get(1);
        $ambiguous = $runnerUp && abs((float) $winner['score'] - (float) $runnerUp['score']) < 0.05;
        $confidence = $exact
            ? 1.0
            : min(0.99, round(0.55 + (float) $winner['score'] * 0.08, 2));
        if ($ambiguous) {
            $confidence = min($confidence, 0.69);
        }
        $platform = $this->mappedPlatformCategory($category->id);
        $evidence = array_values(array_unique($winner['evidence']));
        if ($ambiguous) {
            $evidence[] = '与次高候选分数接近，请人工检查';
        }

        return [
            'standard' => [
                'id'    => $category->id,
                'value' => $category->code,
                'path'  => $category->source_path ?: $category->name,
            ],
            'platform' => $platform ? [
                'id'    => $platform->id,
                'value' => $platform->external_id,
                'path'  => $platform->path,
            ] : null,
            'confidence' => $confidence,
            'reason'     => mb_substr(implode('；', $evidence) ?: '命中本地规则', 0, 500),
            'evidence'   => $evidence,
            'provider'   => 'local-rules',
            'model'      => null,
        ];
    }

    protected function sourceMappedCategory(string $sourcePath): ?Category
    {
        if ($sourcePath === '') {
            return null;
        }

        $source = Category::query()
            ->where('taxonomy_type', 'source')
            ->where('source_platform', '1688')
            ->where('source_path', $sourcePath)
            ->first();
        if (! $source) {
            return null;
        }

        return CategorySourceMapping::query()
            ->with('category')
            ->where('source_category_id', $source->id)
            ->where('source_platform', '1688')
            ->where('status', 'confirmed')
            ->orderByDesc('confidence')
            ->first()?->category;
    }

    protected function rankCandidates(string $text): Collection
    {
        return Category::query()
            ->with(['aliases', 'classificationRules' => fn ($query) => $query->where('status', true)->orderBy('position')])
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->where('code', '!=', 'std_uncategorized')
            ->get()
            ->map(function (Category $category) use ($text): ?array {
                $score = 0.0;
                $evidence = [];
                $terms = $category->aliases->pluck('alias')
                    ->push($category->name)
                    ->merge(preg_split('/\s*(?:>|\/|、|&|，|,|与|和)\s*/u', (string) $category->source_path) ?: [])
                    ->filter(fn ($term) => mb_strlen(trim((string) $term)) >= 2)
                    ->unique();
                foreach ($terms as $term) {
                    $needle = mb_strtolower(trim((string) $term));
                    if (str_contains($text, $needle)) {
                        $score += 1.8 + min(mb_strlen($needle), 12) * 0.03;
                        $evidence[] = "关键词：{$term}";
                    }
                }
                foreach ($category->classificationRules as $rule) {
                    $needle = mb_strtolower(trim((string) $rule->value));
                    if ($needle === '') {
                        continue;
                    }
                    $matched = match ($rule->operator) {
                        'equals' => trim($text) === $needle,
                        'regex'  => @preg_match('/'.$rule->value.'/iu', $text) === 1,
                        default  => str_contains($text, $needle),
                    };
                    if ($matched) {
                        $score += (float) $rule->weight;
                        $evidence[] = "规则：{$rule->field} {$rule->operator} {$rule->value}";
                    }
                }

                return $score > 0 ? compact('category', 'score', 'evidence') : null;
            })
            ->filter()
            ->sortByDesc('score')
            ->values();
    }

    protected function mappedPlatformCategory(int $categoryId): mixed
    {
        return CategoryMapping::query()
            ->with('platformCategory.taxonomy')
            ->where('category_id', $categoryId)
            ->where('status', 'confirmed')
            ->whereHas('platformCategory', fn ($query) => $query->where('enabled', true)->where('is_leaf', true))
            ->whereHas('platformCategory.taxonomy', fn ($query) => $query
                ->where('platform', 'tiktok')
                ->where('status', 'active'))
            ->orderByDesc('confidence')
            ->first()?->platformCategory;
    }
}
