<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiBulkApplyItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_fields' => 'array', 'approved_at' => 'datetime'];
    }
}
