<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExportJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'field_groups_json'  => 'array',
            'filters_json'      => 'array',
            'selected_ids_json' => 'array',
            'total_rows'        => 'integer',
            'started_at'        => 'datetime',
            'finished_at'       => 'datetime',
            'expires_at'        => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'completed'  => 'Hoàn thành',
            'failed'     => 'Lỗi',
            default      => $this->status,
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed'
            && $this->file_path
            && !$this->isExpired();
    }
}
