<?php

namespace Webkul\Category\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductCategoryClassificationCache;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\Product\Models\Product;

class ProductCategoryClassificationManager
{
    public function remember(Product $product, string $method, array $result): ProductCategoryClassificationCache
    {
        $standard = Category::query()
            ->where('code', data_get($result, 'standard.value'))
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->firstOrFail();
        $platform = PlatformCategory::query()
            ->where('external_id', data_get($result, 'platform.value'))
            ->where('enabled', true)
            ->where('is_leaf', true)
            ->whereHas('taxonomy', fn ($query) => $query->where('platform', 'tiktok')->where('status', 'active'))
            ->first();

        return ProductCategoryClassificationCache::updateOrCreate(
            ['product_id' => $product->id, 'method' => $method, 'platform' => 'tiktok'],
            [
                'standard_category_id' => $standard->id,
                'platform_category_id' => $platform?->id,
                'confidence'           => $result['confidence'] ?? null,
                'status'               => 'ready',
                'provider'             => $result['provider'] ?? null,
                'model'                => $result['model'] ?? null,
                'input_hash'           => hash('sha256', json_encode($product->resolvedValues(), JSON_UNESCAPED_UNICODE)),
                'reason'               => $result['reason'] ?? null,
                'evidence'             => $result['evidence'] ?? array_values(array_filter([$result['reason'] ?? null])),
                'generated_at'         => now(),
                'applied_at'           => null,
            ]
        )->load(['standardCategory', 'platformCategory']);
    }

    public function apply(Product $product, string $method, ?int $reviewedBy): ProductCategoryClassificationCache
    {
        $cache = ProductCategoryClassificationCache::with(['standardCategory', 'platformCategory'])
            ->where('product_id', $product->id)
            ->where('method', $method)
            ->where('platform', 'tiktok')
            ->first();
        if (! $cache) {
            throw ValidationException::withMessages(['method' => '该分类方式还没有缓存结果，请先执行分类。']);
        }
        $category = $cache->standardCategory;
        if (! $category || ! $category->is_assignable || $category->status !== 'active') {
            throw ValidationException::withMessages(['method' => '缓存的 PIM 标准类目已不可用，请清除缓存后重新分类。']);
        }
        $assignmentMethod = $method === 'ai' ? 'ai' : 'rule';

        DB::transaction(function () use ($product, $cache, $category, $assignmentMethod, $reviewedBy): void {
            $assignment = ProductCategoryAssignment::updateOrCreate(
                ['product_id' => $product->id, 'category_id' => $category->id, 'role' => 'primary'],
                [
                    'status'           => 'confirmed',
                    'method'           => $assignmentMethod,
                    'confidence'       => $cache->confidence,
                    'evidence'         => $cache->evidence ?: array_values(array_filter([$cache->reason])),
                    'taxonomy_version' => config('taxonomy.standard_version', 'current'),
                    'reviewed_by'      => $reviewedBy,
                    'reviewed_at'      => now(),
                ]
            );
            ProductCategoryAssignment::query()
                ->where('product_id', $product->id)
                ->where('role', 'primary')
                ->where('id', '!=', $assignment->id)
                ->update(['status' => 'rejected', 'reviewed_by' => $reviewedBy, 'reviewed_at' => now()]);

            $values = $product->values ?: [];
            $values['categories'] = [$category->code];
            $values['common'] = array_merge((array) ($values['common'] ?? []), [
                'category_classification_status'     => 'confirmed',
                'category_classification_method'     => $assignmentMethod,
                'category_classification_confidence' => (string) ($cache->confidence ?? ''),
                'category_classification_evidence'   => json_encode([
                    'path'     => $category->source_path ?: $category->name,
                    'reason'   => $cache->reason,
                    'evidence' => $cache->evidence,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $product->values = $values;
            $product->save();

            if ($cache->platformCategory) {
                ProductPlatformCategoryAssignment::updateOrCreate(
                    ['product_id' => $product->id, 'platform' => 'tiktok'],
                    [
                        'platform_category_id' => $cache->platformCategory->id,
                        'status'               => 'confirmed',
                        'method'               => $assignmentMethod,
                        'confidence'           => $cache->confidence,
                        'evidence'             => $cache->evidence ?: array_values(array_filter([$cache->reason])),
                        'reviewed_by'          => $reviewedBy,
                        'reviewed_at'          => now(),
                    ]
                );
            } else {
                ProductPlatformCategoryAssignment::query()
                    ->where('product_id', $product->id)
                    ->where('platform', 'tiktok')
                    ->delete();
            }

            $cache->update(['status' => 'applied', 'applied_at' => now()]);
        });

        return $cache->fresh(['standardCategory', 'platformCategory']);
    }

    public function forget(Product $product, string $method): void
    {
        ProductCategoryClassificationCache::query()
            ->where('product_id', $product->id)
            ->where('method', $method)
            ->where('platform', 'tiktok')
            ->delete();
    }

    public function present(ProductCategoryClassificationCache $cache): array
    {
        return [
            'method'       => $cache->method,
            'status'       => $cache->status,
            'confidence'   => (float) ($cache->confidence ?? 0),
            'reason'       => $cache->reason,
            'provider'     => $cache->provider,
            'model'        => $cache->model,
            'generated_at' => $cache->generated_at?->toIso8601String(),
            'applied_at'   => $cache->applied_at?->toIso8601String(),
            'standard' => [
                'value' => $cache->standardCategory?->code,
                'path'  => $cache->standardCategory?->source_path ?: $cache->standardCategory?->name,
            ],
            'platform' => $cache->platformCategory ? [
                'value' => $cache->platformCategory->external_id,
                'path'  => $cache->platformCategory->path,
            ] : null,
        ];
    }
}
