<?php

namespace Webkul\AdminApi\Http\Controllers\API\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Webkul\AdminApi\Http\Controllers\API\ApiController;
use Webkul\AdminApi\Services\ProductContentPolicyService;
use Webkul\Product\Models\Product;

class ContentPolicyController extends ApiController
{
    public function words(ProductContentPolicyService $policy): JsonResponse
    {
        return response()->json(['data' => $policy->forbiddenWords()]);
    }

    public function optimize(
        Request $request,
        ProductContentPolicyService $policy,
        string $sku
    ): JsonResponse {
        set_time_limit(180);

        $data = $request->validate([
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'region' => ['sometimes', 'nullable', 'string', 'max:16'],
        ]);
        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $product = $product->parent ?: $product;

        try {
            $result = $policy->optimize($product, $data['locale'] ?? null, $data['region'] ?? null);
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'message' => '商品违禁词处理失败：'.$error->getMessage(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
