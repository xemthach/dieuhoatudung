<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGenerationSession extends Model
{
    protected $guarded = [];

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
