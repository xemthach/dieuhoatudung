<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'error_report_json'   => 'array',
            'preview_data_json'   => 'array',
            'column_mapping_json' => 'array',
            'field_groups_json'   => 'array',
            'total_rows'          => 'integer',
            'success_rows'        => 'integer',
            'failed_rows'         => 'integer',
            'skipped_rows'        => 'integer',
            'created_rows'        => 'integer',
            'updated_rows'        => 'integer',
            'started_at'          => 'datetime',
            'finished_at'         => 'datetime',
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
            'validating' => 'Đang kiểm tra',
            'previewing' => 'Đang xem trước',
            'importing'  => 'Đang import',
            'completed'  => 'Hoàn thành',
            'failed'     => 'Lỗi',
            default      => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'gray',
            'validating' => 'info',
            'previewing' => 'warning',
            'importing'  => 'primary',
            'completed'  => 'success',
            'failed'     => 'danger',
            default      => 'gray',
        };
    }
}
