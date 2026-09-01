<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryClassificationRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'weight' => 'decimal:4'];
    }
}
