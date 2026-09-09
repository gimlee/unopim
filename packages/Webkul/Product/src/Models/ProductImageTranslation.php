<?php

namespace Webkul\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImageTranslation extends Model
{
    protected $table = 'product_image_translations';

    protected $fillable = [
        'product_id',
        'sku',
        'region',
        'locale',
        'image_type',
        'original_url',
        'translated_url',
        'local_path',
        'variant_sku',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
