<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Product\Models\Product;

class ProductListingHistoryController extends Controller
{
    public function index(int $productId): JsonResponse
    {
        if (! bouncer()->hasPermission('catalog.products.edit')) {
            abort(403);
        }

        $product = Product::query()->findOrFail($productId);
        $productId = $product->parent_id ?: $product->id;
        $histories = DB::table('product_listing_histories')
            ->where('product_id', $productId)
            ->orderByRaw('COALESCE(completed_at, started_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $histories,
        ]);
    }
}
