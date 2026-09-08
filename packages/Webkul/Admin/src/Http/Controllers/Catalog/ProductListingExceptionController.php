<?php

namespace Webkul\Admin\Http\Controllers\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Product\Models\Product;

class ProductListingExceptionController extends Controller
{
    public function update(int $productId, int $exceptionId): JsonResponse
    {
        if (! bouncer()->hasPermission('catalog.products.edit')) {
            abort(403);
        }

        $data = request()->validate(['resolved' => ['required', 'boolean']]);
        $product = Product::query()->findOrFail($productId);
        $productId = $product->parent_id ?: $product->id;
        $updated = DB::table('product_listing_exceptions')
            ->where('id', $exceptionId)
            ->where('product_id', $productId)
            ->update([
                'resolved_at' => $data['resolved'] ? now() : null,
                'updated_at'  => now(),
            ]);

        abort_if(! $updated, 404);

        return response()->json([
            'message' => $data['resolved'] ? '上架异常已标记为处理完成。' : '上架异常已重新打开。',
            'data'    => ['resolved_at' => $data['resolved'] ? now()->toDateTimeString() : null],
        ]);
    }
}
