<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProductJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'selected_product_ids_json' => 'array',
            'current_page_ids_json' => 'array',
            'filter_json' => 'array',
            'confirm_filter_scope' => 'boolean',
            'validation_errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AiProductJobItem::class);
    }

    public function technicalLogs(): HasMany
    {
        return $this->hasMany(AiTechnicalLog::class, 'ai_job_id')
            ->where('ai_job_type', class_basename($this));
    }
}
