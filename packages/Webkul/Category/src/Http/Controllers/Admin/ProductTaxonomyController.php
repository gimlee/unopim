<?php

namespace Webkul\Category\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Webkul\Category\Models\Category;
use Webkul\Category\Models\PlatformCategory;
use Webkul\Category\Models\ProductCategoryAssignment;
use Webkul\Category\Models\ProductPlatformCategoryAssignment;
use Webkul\Category\Services\ProductCategoryAiClassifier;
use Webkul\MagicAI\Models\MagicAIPlatform;
use Webkul\Product\Models\Product;

class ProductTaxonomyController extends Controller
{
    public function update(Request $request, int $productId): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);

        $data = $request->validate([
            'standard_category_code'        => ['required', 'string', 'exists:categories,code'],
            'platform'                      => ['required', Rule::in(['tiktok'])],
            'platform_category_external_id' => ['nullable', 'string', 'max:255'],
            'method'                        => ['nullable', Rule::in(['manual', 'ai_reviewed'])],
            'confidence'                    => ['nullable', 'numeric', 'between:0,1'],
            'reason'                        => ['nullable', 'string', 'max:500'],
        ]);

        $product = $this->taxonomyProduct($productId);
        $category = Category::query()
            ->where('code', $data['standard_category_code'])
            ->where('taxonomy_type', 'standard')
            ->where('is_assignable', true)
            ->where('status', 'active')
            ->first();
        if (! $category || $category->code === 'std_uncategorized') {
            throw ValidationException::withMessages([
                'standard_category_code' => '请选择有效的 PIM 标准叶子类目。',
            ]);
        }

        $platformCategory = null;
        if (! empty($data['platform_category_external_id'])) {
            $platformCategory = PlatformCategory::query()
                ->where('external_id', $data['platform_category_external_id'])
                ->where('is_leaf', true)
                ->where('enabled', true)
                ->whereHas('taxonomy', fn ($query) => $query
                    ->where('platform', $data['platform'])
                    ->where('status', 'active'))
                ->whereHas('taxonomy', fn ($query) => $query->where('region', 'MY'))
                ->first();
            if (! $platformCategory) {
                throw ValidationException::withMessages([
                    'platform_category_external_id' => '请选择有效、可用的 TikTok 叶子类目。',
                ]);
            }
        }

        $method = $data['method'] ?? 'manual';
        DB::transaction(function () use ($product, $category, $platformCategory, $data, $method): void {
            $assignment = ProductCategoryAssignment::updateOrCreate(
                [
                    'product_id'  => $product->id,
                    'category_id' => $category->id,
                    'role'        => 'primary',
                ],
                [
                    'status'           => 'confirmed',
                    'method'           => $method,
                    'confidence'       => $method === 'manual' ? 1 : ($data['confidence'] ?? null),
                    'evidence'         => array_values(array_filter([$data['reason'] ?? null])),
                    'taxonomy_version' => config('taxonomy.standard_version', 'current'),
                    'reviewed_by'      => auth()->guard('admin')->id(),
                    'reviewed_at'      => now(),
                ]
            );
            ProductCategoryAssignment::query()
                ->where('product_id', $product->id)
                ->where('role', 'primary')
                ->where('id', '!=', $assignment->id)
                ->update([
                    'status'      => 'rejected',
                    'reviewed_by' => auth()->guard('admin')->id(),
                    'reviewed_at' => now(),
                ]);

            $values = $product->values ?: [];
            $values['categories'] = [$category->code];
            $product->values = $values;
            $product->save();

            if ($platformCategory) {
                ProductPlatformCategoryAssignment::updateOrCreate(
                    ['product_id' => $product->id, 'platform' => $data['platform']],
                    [
                        'platform_category_id' => $platformCategory->id,
                        'status'               => 'confirmed',
                        'method'               => $method,
                        'confidence'           => $method === 'manual' ? 1 : ($data['confidence'] ?? null),
                        'evidence'             => array_values(array_filter([$data['reason'] ?? null])),
                        'reviewed_by'          => auth()->guard('admin')->id(),
                        'reviewed_at'          => now(),
                    ]
                );
            } else {
                ProductPlatformCategoryAssignment::query()
                    ->where('product_id', $product->id)
                    ->where('platform', $data['platform'])
                    ->delete();
            }
        });

        return response()->json([
            'message'                => '商品类目已保存。',
            'standard_category_code' => $category->code,
            'standard_path'          => $category->source_path ?: $category->name,
            'platform_path'          => $platformCategory?->path,
        ]);
    }

    public function aiSuggest(
        Request $request,
        ProductCategoryAiClassifier $classifier,
        int $productId
    ): JsonResponse {
        abort_unless(bouncer()->hasPermission('catalog.products.edit'), 403);

        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:magic_ai_platforms,id'],
            'model'       => ['required', 'string', 'max:255'],
        ]);

        $product = $this->taxonomyProduct($productId);
        $platform = MagicAIPlatform::findOrFail($data['platform_id']);
        $result = $classifier->classify($product, $platform, $data['model']);

        return response()->json(['data' => $result]);
    }

    protected function taxonomyProduct(int $productId): Product
    {
        $product = Product::with('parent')->findOrFail($productId);

        return $product->parent ?: $product;
    }
}
