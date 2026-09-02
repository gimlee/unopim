<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Product\Models\Product;

class ProductPlatformCategoryAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence'  => 'decimal:4',
            'evidence'    => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function platformCategory(): BelongsTo
    {
        return $this->belongsTo(PlatformCategory::class);
    }
}
