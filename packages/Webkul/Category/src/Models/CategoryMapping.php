<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'confidence' => 'decimal:4',
            'reviewed_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function platformCategory(): BelongsTo
    {
        return $this->belongsTo(PlatformCategory::class);
    }
}
