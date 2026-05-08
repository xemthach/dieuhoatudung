<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BtuCalculation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'area_m2'           => 'decimal:2',
            'ceiling_height'    => 'decimal:2',
            'people_count'      => 'integer',
            'direct_sunlight'   => 'boolean',
            'heat_equipment'    => 'boolean',
            'recommended_btu'   => 'integer',
            'matched_product_ids' => 'array',
        ];
    }

    /** Space type labels — delegates to BtuCalculatorService for canonical W/m² data */
    public static function spaceTypeLabels(): array
    {
        return \App\Services\Calculator\BtuCalculatorService::spaceTypeLabels();
    }

    /** Priority labels */
    public static function priorityLabels(): array
    {
        return [
            'tiet_kiem_dien'    => 'Tiết kiệm điện',
            'gia_tot'           => 'Giá tốt nhất',
            'van_hanh_ben_bi'   => 'Vận hành bền bỉ',
            'thuong_hieu_cao_cap' => 'Thương hiệu cao cấp',
        ];
    }

    /** Resolved space type label */
    public function getSpaceTypeLabelAttribute(): string
    {
        return static::spaceTypeLabels()[$this->space_type] ?? $this->space_type;
    }

    /** Resolved priority label */
    public function getPriorityLabelAttribute(): string
    {
        return static::priorityLabels()[$this->priority] ?? ($this->priority ?? '—');
    }
}
