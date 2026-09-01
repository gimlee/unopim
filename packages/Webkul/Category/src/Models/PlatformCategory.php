<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_leaf' => 'boolean', 'enabled' => 'boolean', 'raw_payload' => 'array'];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(PlatformTaxonomy::class, 'platform_taxonomy_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(CategoryMapping::class);
    }
}
