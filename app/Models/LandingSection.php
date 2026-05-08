<?php

namespace App\Models;

use App\Enums\LandingSectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'section_type' => LandingSectionType::class,
            'settings_json' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
