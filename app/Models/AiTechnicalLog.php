<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTechnicalLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
        ];
    }
}
