<?php

namespace App\Models;

use App\Enums\QuoteRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'                   => QuoteRequestStatus::class,
            'area_m2'                  => 'decimal:2',
            'ceiling_height'           => 'decimal:2',
            'estimated_volume_m3'      => 'decimal:2',
            'pipe_distance_m'          => 'decimal:1',
            'preferred_btu'            => 'integer',
            'calculated_btu'           => 'integer',
            'product_capacity_btu'     => 'integer',
            'number_of_rooms'          => 'integer',
            'number_of_people'         => 'integer',
            'intent_score'             => 'integer',
            'need_inverter'            => 'boolean',
            'need_three_phase'         => 'boolean',
            'open_space'               => 'boolean',
            'need_invoice'             => 'boolean',
            'need_site_survey'         => 'boolean',
            'recommended_product_ids'  => 'array',
            'preferred_brands'         => 'array',
            'selected_product_snapshot' => 'array',
        ];
    }

    /* ── Relationships ── */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* ── Static label helpers ── */

    public static function projectTypeLabels(): array
    {
        return [
            'nha_o'       => 'Nhà ở',
            'can_ho'      => 'Căn hộ',
            'van_phong'   => 'Văn phòng',
            'cua_hang'    => 'Cửa hàng',
            'showroom'    => 'Showroom',
            'nha_hang'    => 'Nhà hàng / Quán cafe',
            'hoi_truong'  => 'Hội trường',
            'nha_xuong'   => 'Nhà xưởng',
            'truong_hoc'  => 'Trường học',
            'khach_san'   => 'Khách sạn',
            'khac'        => 'Khác',
        ];
    }

    public static function budgetRangeLabels(): array
    {
        return [
            'duoi_20_trieu' => 'Dưới 20 triệu',
            '20_40_trieu'   => '20 – 40 triệu',
            '40_70_trieu'   => '40 – 70 triệu',
            'tren_70_trieu' => 'Trên 70 triệu',
            'chua_ro'       => 'Chưa rõ',
        ];
    }

    public static function installationTimeLabels(): array
    {
        return [
            'ngay'      => 'Càng sớm càng tốt',
            '3_ngay'    => 'Trong 1–3 ngày',
            '1_tuan'    => 'Trong tuần này',
            '1_thang'   => 'Trong tháng này',
            'chua_ro'   => 'Chưa xác định',
        ];
    }

    public static function sunExposureLabels(): array
    {
        return [
            'it_nang'    => 'Ít nắng',
            'nang_vua'   => 'Nắng vừa',
            'nang_nhieu' => 'Nắng nhiều / Hướng Tây',
        ];
    }

    public static function insulationLabels(): array
    {
        return [
            'tot'        => 'Tốt (tường dày, có cách nhiệt)',
            'trung_binh' => 'Trung bình',
            'kem'        => 'Kém (tường mỏng, mái tôn)',
            'chua_ro'    => 'Chưa rõ',
        ];
    }

    public static function glassAreaLabels(): array
    {
        return [
            'it_kinh'    => 'Ít kính',
            'nhieu_kinh' => 'Nhiều kính',
            'vach_kinh'  => 'Vách kính lớn',
        ];
    }

    public static function airconStatusLabels(): array
    {
        return [
            'chua_co'    => 'Chưa có điều hòa',
            'co_nhung_yeu' => 'Đã có nhưng yếu',
            'thay_cu'    => 'Thay máy cũ',
            'can_them'   => 'Cần thêm máy',
        ];
    }

    public static function installationTypeLabels(): array
    {
        return [
            'lap_moi'    => 'Lắp mới',
            'thay_cu'    => 'Thay máy cũ',
            'di_doi'     => 'Di dời máy',
            'bao_tri'    => 'Bảo trì / Kiểm tra',
        ];
    }

    public static function outdoorLocationLabels(): array
    {
        return [
            'ban_cong'   => 'Ban công',
            'mai_nha'    => 'Mái nhà',
            'tuong_ngoai'=> 'Tường ngoài',
            'san_thuong' => 'Sân thượng',
            'chua_ro'    => 'Chưa rõ',
        ];
    }

    public static function contactMethodLabels(): array
    {
        return [
            'phone' => 'Gọi điện',
            'zalo'  => 'Zalo',
            'email' => 'Email',
        ];
    }

    public static function contactTimeLabels(): array
    {
        return [
            'ngay'        => 'Gọi ngay',
            'hanh_chinh'  => 'Giờ hành chính (8h–17h)',
            'buoi_toi'    => 'Buổi tối (18h–21h)',
            'khac'        => 'Khác',
        ];
    }

    public static function needInstallLabels(): array
    {
        return [
            'tron_goi' => 'Báo giá trọn gói máy + lắp đặt',
            'chi_may'  => 'Chỉ cần báo giá máy',
            'chua_ro'  => 'Chưa rõ',
        ];
    }

    public static function btuOptions(): array
    {
        return [
            ''      => '-- Để hệ thống tư vấn --',
            '18000' => '18,000 BTU',
            '24000' => '24,000 BTU',
            '28000' => '28,000 BTU',
            '36000' => '36,000 BTU',
            '42000' => '42,000 BTU',
            '48000' => '48,000 BTU',
            '60000' => '60,000 BTU',
            '100000'=> '100,000 BTU',
        ];
    }

    /* ── Computed accessors ── */

    public function getProjectTypeLabelAttribute(): string
    {
        return static::projectTypeLabels()[$this->project_type] ?? ($this->project_type ?? '—');
    }

    public function getBudgetRangeLabelAttribute(): string
    {
        return static::budgetRangeLabels()[$this->budget_range] ?? ($this->budget_range ?? '—');
    }

    public function getInstallationTimeLabelAttribute(): string
    {
        return static::installationTimeLabels()[$this->installation_time] ?? ($this->installation_time ?? '—');
    }

    /**
     * Generate a summary string for the lead.
     */
    public function getLeadSummaryAttribute(): string
    {
        $parts = [];
        if ($this->project_type) {
            $parts[] = static::projectTypeLabels()[$this->project_type] ?? $this->project_type;
        }
        if ($this->area_m2) {
            $parts[] = $this->area_m2 . 'm²';
        }
        if ($this->calculated_btu || $this->preferred_btu) {
            $parts[] = number_format($this->calculated_btu ?? $this->preferred_btu) . ' BTU';
        }
        return implode(' · ', $parts) ?: 'Tư vấn chung';
    }

    /**
     * Calculate intent score based on data completeness.
     */
    public static function calculateIntentScore(array $data): int
    {
        $score = 0;

        // Product lead bonus
        if (!empty($data['product_id'])) $score += 40;

        // Has phone (most valuable)
        if (!empty($data['phone'])) $score += 20;

        // Has area
        if (!empty($data['area_m2'])) $score += 15;

        // Urgent timeline
        if (in_array($data['installation_time'] ?? '', ['ngay', '3_ngay'])) $score += 15;

        // Clear budget (not "chưa rõ")
        if (!empty($data['budget_range']) && $data['budget_range'] !== 'chua_ro') $score += 10;

        return min($score, 100);
    }
}
