<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiProvider extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'api_key' => 'encrypted',
        'supports_streaming' => 'boolean',
        'supports_json_mode' => 'boolean',
        'is_default' => 'boolean',
        'last_used_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
        'rate_limited_until' => 'datetime',
    ];

    public function sessions()
    {
        return $this->hasMany(AiGenerationSession::class, 'provider_id');
    }

    public function requestLogs()
    {
        return $this->hasMany(AiRequestLog::class, 'ai_provider_id');
    }
}
