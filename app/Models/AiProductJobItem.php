<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductJobItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'warnings_json' => 'array',
            'generated_payload_json' => 'array',
            'validation_errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AiProductJob::class, 'ai_product_job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function technicalLogs()
    {
        return $this->hasMany(AiTechnicalLog::class, 'ai_job_id')
            ->where('ai_job_type', class_basename($this));
    }
}
