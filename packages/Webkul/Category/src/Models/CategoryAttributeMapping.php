<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryAttributeMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['transform' => 'array'];
    }
}
