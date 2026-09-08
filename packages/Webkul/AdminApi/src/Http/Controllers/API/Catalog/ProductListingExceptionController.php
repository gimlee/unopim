<?php

namespace Webkul\AdminApi\Http\Controllers\API\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Http\Controllers\API\ApiController;
use Webkul\Product\Models\Product;

class ProductListingExceptionController extends ApiController
{
    public function store(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate([
            'platform'                     => ['sometimes', 'string', 'max:32'],
            'region'                       => ['sometimes', 'nullable', 'string', 'max:16'],
            'attempt_id'                   => ['required', 'string', 'max:64'],
            'exceptions'                   => ['required', 'array', 'min:1', 'max:100'],
            'exceptions.*.event_key'       => ['sometimes', 'nullable', 'string', 'max:1000'],
            'exceptions.*.exception_type'  => ['required', 'string', 'max:64'],
            'exceptions.*.stage'           => ['sometimes', 'nullable', 'string', 'max:128'],
            'exceptions.*.severity'        => ['sometimes', 'in:info,warning,error'],
            'exceptions.*.message'         => ['required', 'string', 'max:10000'],
            'exceptions.*.requires_manual' => ['sometimes', 'boolean'],
            'exceptions.*.blocking'        => ['sometimes', 'boolean'],
            'exceptions.*.details'         => ['sometimes', 'nullable', 'array'],
            'exceptions.*.occurred_at'     => ['sometimes', 'nullable', 'date'],
        ]);
        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $product = $product->parent ?: $product;
        $platform = strtolower(trim($data['platform'] ?? 'tiktok'));
        $region = isset($data['region']) ? strtoupper(trim((string) $data['region'])) : null;
        $now = now();
        $ids = [];

        DB::transaction(function () use ($data, $product, $platform, $region, $sku, $now, &$ids): void {
            foreach ($data['exceptions'] as $index => $exception) {
                $eventKey = trim((string) ($exception['event_key'] ?? ''));
                $eventKey = $eventKey !== ''
                    ? mb_substr(hash('sha256', $eventKey), 0, 64)
                    : hash('sha256', json_encode([$index, $exception], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string) $index);
                $identity = [
                    'product_id' => $product->id,
                    'attempt_id' => $data['attempt_id'],
                    'event_key'  => $eventKey,
                ];

                DB::table('product_listing_exceptions')->updateOrInsert($identity, [
                    'sku'             => $product->sku ?: $sku,
                    'platform'        => $platform,
                    'region'          => $region,
                    'exception_type'  => $exception['exception_type'],
                    'stage'           => $exception['stage'] ?? null,
                    'severity'        => $exception['severity'] ?? 'warning',
                    'message'         => $exception['message'],
                    'requires_manual' => (bool) ($exception['requires_manual'] ?? false),
                    'blocking'        => (bool) ($exception['blocking'] ?? false),
                    'details'         => json_encode($exception['details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'occurred_at'     => $exception['occurred_at'] ?? $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                $ids[] = DB::table('product_listing_exceptions')->where($identity)->value('id');
            }
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'ids'         => $ids,
                'count'       => count($ids),
                'product_id'  => $product->id,
                'product_sku' => $product->sku,
                'admin_url'   => route('admin.catalog.products.edit', $product->id),
            ],
        ]);
    }
}
