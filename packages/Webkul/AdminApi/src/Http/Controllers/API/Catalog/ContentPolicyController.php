<?php

namespace Webkul\AdminApi\Http\Controllers\API\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'words'   => ['required', 'array'],
            'words.*' => ['required', 'string', 'max:255'],
        ]);

        $added = 0;
        $unchanged = 0;
        $now = now();

        foreach ($data['words'] as $rawWord) {
            $term = trim((string) $rawWord);
            if ($term === '') {
                continue;
            }
            $normalized = mb_strtolower($term);

            $exists = DB::table('content_policy_forbidden_words')
                ->where('normalized_term', $normalized)
                ->exists();

            if (! $exists) {
                DB::table('content_policy_forbidden_words')->insert([
                    'term'            => $term,
                    'normalized_term' => $normalized,
                    'status'          => true,
                    'notes'           => '系统受管违禁词',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                $added++;
            } else {
                $unchanged++;
            }
        }

        $total = DB::table('content_policy_forbidden_words')->where('status', true)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'added'     => $added,
                'unchanged' => $unchanged,
                'total'     => $total,
            ],
        ]);
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
