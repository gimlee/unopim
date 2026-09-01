<?php

namespace Webkul\Category\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCategoryAttribute extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'rules' => 'array', 'raw_payload' => 'array'];
    }
}
