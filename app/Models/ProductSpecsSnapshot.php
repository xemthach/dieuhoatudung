<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecsSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
        ];
    }
}
