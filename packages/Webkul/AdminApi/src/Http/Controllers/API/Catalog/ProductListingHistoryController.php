<?php

namespace Webkul\AdminApi\Http\Controllers\API\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\AdminApi\Http\Controllers\API\ApiController;
use Webkul\Product\Models\Product;

class ProductListingHistoryController extends ApiController
{
    public function store(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate([
            'attempt_id'   => ['required', 'string', 'max:64'],
            'sku'          => ['sometimes', 'string', 'max:128'],
            'platform'     => ['required', 'string', 'max:32'],
            'region'       => ['required', 'string', 'max:16'],
            'listing_type' => ['required', 'in:draft,review'],
            'status'       => ['required', 'string', 'max:64'],
            'published'    => ['sometimes', 'boolean', 'declined'],
            'draft_url'    => ['sometimes', 'nullable', 'string', 'max:10000'],
            'seller_url'   => ['sometimes', 'nullable', 'string', 'max:10000'],
            'error'        => ['sometimes', 'nullable', 'string', 'max:20000'],
            'started_at'   => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'created_at'   => ['sometimes', 'nullable', 'date'],
        ]);
        $requestedProduct = Product::query()->where('sku', $sku)->firstOrFail();
        $product = $requestedProduct->parent ?: $requestedProduct;
        $now = now();
        $identity = [
            'product_id' => $product->id,
            'platform'   => strtolower(trim($data['platform'])),
            'attempt_id' => trim($data['attempt_id']),
        ];
        $values = [
            'sku'          => $product->sku,
            'region'       => strtoupper(trim($data['region'])),
            'listing_type' => $data['listing_type'],
            'status'       => trim($data['status']),
            'published'    => false,
            'draft_url'    => $data['draft_url'] ?? null,
            'seller_url'   => $data['seller_url'] ?? null,
            'error'        => $data['error'] ?? null,
            'started_at'   => $data['started_at'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'updated_at'   => $now,
        ];

        $historyId = DB::transaction(function () use ($identity, $values, $data, $now): int {
            $query = DB::table('product_listing_histories')->where($identity);
            $historyId = $query->value('id');

            if ($historyId) {
                $query->update($values);

                return (int) $historyId;
            }

            return (int) DB::table('product_listing_histories')->insertGetId($identity + $values + [
                'created_at' => $data['created_at'] ?? $now,
            ]);
        });
        $history = DB::table('product_listing_histories')->find($historyId);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $historyId,
                'history'     => $history,
                'product_id'  => $product->id,
                'product_sku' => $product->sku,
                'admin_url'   => route('admin.catalog.products.edit', $product->id),
            ],
        ]);
    }
}
