<?php

namespace Webkul\AdminApi\Http\Controllers\API\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Webkul\AdminApi\Http\Controllers\API\ApiController;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductImageTranslation;

class ProductImageTranslationApiController extends ApiController
{
    /**
     * Get image translations for a product SKU, optionally filtered by region.
     */
    public function index(Request $request, string $sku): JsonResponse
    {
        $query = ProductImageTranslation::query();

        // Support root SKU or variant SKU
        $query->where(function ($q) use ($sku) {
            $q->where('sku', $sku)
              ->orWhere('variant_sku', $sku);
        });

        if ($request->has('region') && $request->input('region')) {
            $query->where('region', strtoupper($request->input('region')));
        }

        if ($request->has('locale') && $request->input('locale')) {
            $query->where('locale', strtolower($request->input('locale')));
        }

        $records = $query->orderBy('sort_order')->orderBy('id')->get();

        $data = $records->map(function ($item) {
            return [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'sku'            => $item->sku,
                'region'         => $item->region,
                'locale'         => $item->locale,
                'image_type'     => $item->image_type,
                'original_url'   => $item->original_url,
                'translated_url' => $item->translated_url,
                'local_path'     => $item->local_path,
                'local_url'      => $item->local_path ? Storage::url($item->local_path) : null,
                'variant_sku'    => $item->variant_sku,
                'sort_order'     => $item->sort_order,
                'metadata'       => $item->metadata,
                'created_at'     => $item->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
