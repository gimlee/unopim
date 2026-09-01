<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformTaxonomy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(PlatformCategory::class);
    }
}
