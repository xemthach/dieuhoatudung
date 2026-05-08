<?php

namespace App\Services\Calculator;

use App\Models\Product;

/**
 * BtuCalculatorService
 *
 * Calculates HVAC cooling load using W/m² empirical data
 * from "AIR CONDITIONING COOLING LOAD CHECK FIGURES" table.
 *
 * Formula:
 *   Base Load (W)  = area_m² × W/m² (per space type)
 *   Base Load (BTU) = Base Load (W) × 3.412
 *   + adjustments (ceiling, sunlight, heat equipment, people)
 *   → round up to nearest standard BTU tier
 *
 * HP conversion: HP = BTU / 9,000 (HVAC convention, NOT mechanical 746W)
 */
class BtuCalculatorService
{
    // ──────────────────────────────────────────────────────────
    // W/m² Cooling Load Table (source: Excel BANG TINH TAI KINH NGHIEM)
    // ──────────────────────────────────────────────────────────

    /** @var array<string, array{w_per_m2: int, label_vi: string, label_en: string}> */
    protected array $coolingLoadTable = [
        // Residential
        'nha_o'              => ['w_per_m2' => 120,  'label_vi' => 'Căn hộ, nhà ở',                'label_en' => 'Apartments, Residence'],
        'phong_khach'        => ['w_per_m2' => 120,  'label_vi' => 'Phòng khách (nhà ở)',          'label_en' => 'Residence living room'],
        'khach_san'          => ['w_per_m2' => 120,  'label_vi' => 'Khách sạn, nhà nghỉ',          'label_en' => 'Hotel, Motel Rooms'],
        // Office
        'van_phong'          => ['w_per_m2' => 170,  'label_vi' => 'Văn phòng (viền ngoài)',       'label_en' => 'Office - General (perimeter)'],
        'van_phong_interior' => ['w_per_m2' => 100,  'label_vi' => 'Văn phòng (bên trong)',        'label_en' => 'Office - General (interior)'],
        'van_phong_private'  => ['w_per_m2' => 180,  'label_vi' => 'Văn phòng cá nhân',            'label_en' => 'Office - Private'],
        // Commercial
        'cua_hang'           => ['w_per_m2' => 165,  'label_vi' => 'Cửa hàng',                     'label_en' => 'Clothing / Shoe Stores'],
        'sieu_thi'           => ['w_per_m2' => 160,  'label_vi' => 'Siêu thị',                     'label_en' => 'Supermarkets'],
        'showroom'           => ['w_per_m2' => 900,  'label_vi' => 'Showroom',                      'label_en' => 'Showroom (high load)'],
        'ngan_hang'          => ['w_per_m2' => 175,  'label_vi' => 'Ngân hàng',                     'label_en' => 'Banks'],
        // F&B
        'nha_hang'           => ['w_per_m2' => 330,  'label_vi' => 'Nhà hàng',                      'label_en' => 'Restaurants'],
        'cafe'               => ['w_per_m2' => 350,  'label_vi' => 'Quán cà phê',                   'label_en' => 'Cafeteries'],
        'fastfood'           => ['w_per_m2' => 270,  'label_vi' => 'Thức ăn nhanh, giải khát',      'label_en' => 'Milk Bars, Fast food'],
        // Assembly
        'hoi_truong'         => ['w_per_m2' => 280,  'label_vi' => 'Hội trường, giảng đường',       'label_en' => 'Auditorium'],
        'phong_hop'          => ['w_per_m2' => 275,  'label_vi' => 'Phòng họp',                     'label_en' => 'Conference Rooms'],
        'phong_hoc'          => ['w_per_m2' => 95,   'label_vi' => 'Phòng học',                     'label_en' => 'Classroom'],
        'thu_vien'           => ['w_per_m2' => 150,  'label_vi' => 'Thư viện',                     'label_en' => 'Library'],
        'rap_hat'            => ['w_per_m2' => 280,  'label_vi' => 'Rạp hát',                      'label_en' => 'Theatres'],
        // Medical
        'benh_vien'          => ['w_per_m2' => 190,  'label_vi' => 'Bệnh viện, phòng khám',        'label_en' => 'Clinics'],
        'phong_duoc'         => ['w_per_m2' => 185,  'label_vi' => 'Văn phòng dược',               'label_en' => 'Medical Offices'],
        // Industrial
        'nha_xuong'          => ['w_per_m2' => 275,  'label_vi' => 'Nhà xưởng (CN nhẹ)',           'label_en' => 'Factory Light Manufacture'],
        'nha_xuong_nang'     => ['w_per_m2' => 490,  'label_vi' => 'Nhà xưởng (CN nặng)',          'label_en' => 'Factory Heavy Manufacture'],
        // Specialty
        'phong_may_tinh'     => ['w_per_m2' => 480,  'label_vi' => 'Phòng máy tính / Server',      'label_en' => 'Computer Room'],
        'phong_thi_nghiem'   => ['w_per_m2' => 230,  'label_vi' => 'Phòng thí nghiệm',             'label_en' => 'Laboratory'],
        'tham_my_vien'       => ['w_per_m2' => 260,  'label_vi' => 'Thẩm mỹ viện',                 'label_en' => 'Beauty shops'],
        'sanh_hanh_lang'     => ['w_per_m2' => 135,  'label_vi' => 'Sảnh, hành lang',              'label_en' => 'Mall'],
        'tang_ham'           => ['w_per_m2' => 125,  'label_vi' => 'Tầng hầm',                     'label_en' => 'Basement'],
    ];

    /** BTU/h constant: 1 W = 3.412 BTU/h */
    protected const W_TO_BTU = 3.412;

    /** HVAC HP: 1 HP ≈ 9,000 BTU/h (industry convention) */
    protected const BTU_PER_HP = 9000;

    /**
     * Standard BTU tiers for floor-standing AC units.
     */
    protected array $btuTiers = [
        9000, 12000, 18000, 24000, 28000, 36000, 48000, 50000, 60000, 100000,
    ];

    /**
     * Calculate HVAC cooling load using W/m² method.
     *
     * @return array{
     *     calculated_btu: int,
     *     recommended_btu: int,
     *     recommended_hp: float,
     *     cooling_w_per_m2: int,
     *     base_load_w: float,
     *     area_range: string,
     *     explanation: string,
     *     adjustment_breakdown: array,
     *     steps: array,
     *     note: string|null,
     * }
     */
    public function calculate(
        float  $areaMq,
        float  $ceilingH    = 3.0,
        string $spaceType   = 'van_phong',
        int    $people      = 0,
        bool   $sunlight    = false,
        bool   $heatEquip   = false,
    ): array {
        $steps      = [];
        $adjustments = [];

        // ── 1. Base load from W/m² table ─────────────────────
        $spaceData   = $this->coolingLoadTable[$spaceType] ?? $this->coolingLoadTable['van_phong'];
        $wPerM2      = $spaceData['w_per_m2'];
        $spaceLabel  = $spaceData['label_vi'];

        $baseLoadW   = $areaMq * $wPerM2;
        $baseBtu     = round($baseLoadW * self::W_TO_BTU);

        $steps[] = "Diện tích {$areaMq}m² × {$wPerM2} W/m² ({$spaceLabel}) = " . number_format($baseLoadW) . " W";
        $steps[] = number_format($baseLoadW) . " W × 3.412 = " . number_format($baseBtu) . " BTU";

        $btu = $baseBtu;

        // ── 2. Ceiling height adjustment ─────────────────────
        if ($ceilingH > 3.0) {
            $hFactor = round($ceilingH / 3.0, 2);
            $before  = $btu;
            $btu     = round($btu * $hFactor);
            $delta   = $btu - $before;
            $steps[] = "Trần cao {$ceilingH}m → hệ số ×{$hFactor} (+{$delta} BTU)";
            $adjustments['ceiling'] = ['factor' => $hFactor, 'delta_btu' => $delta];
        }

        // ── 3. Direct sunlight +10% ─────────────────────────
        if ($sunlight) {
            $before = $btu;
            $btu    = round($btu * 1.10);
            $delta  = $btu - $before;
            $steps[] = "Có nắng trực tiếp (+10% = +{$delta} BTU)";
            $adjustments['sunlight'] = ['factor' => 1.10, 'delta_btu' => $delta];
        }

        // ── 4. Heat-generating equipment +10% ───────────────
        if ($heatEquip) {
            $before = $btu;
            $btu    = round($btu * 1.10);
            $delta  = $btu - $before;
            $steps[] = "Nhiều thiết bị sinh nhiệt (+10% = +{$delta} BTU)";
            $adjustments['heat_equipment'] = ['factor' => 1.10, 'delta_btu' => $delta];
        }

        // ── 5. People load (400 BTU/person above 10) ────────
        if ($people > 10) {
            $extra = ($people - 10) * 400;
            $btu  += $extra;
            $steps[] = ($people - 10) . " người vượt mức × 400 BTU = +" . number_format($extra) . " BTU";
            $adjustments['extra_people'] = ['count' => $people - 10, 'delta_btu' => $extra];
        }

        $calculatedBtu  = (int) round($btu);
        $recommendedBtu = $this->roundUpToTier($calculatedBtu);
        $recommendedHp  = round($recommendedBtu / self::BTU_PER_HP, 1);

        // ── Area range & note ────────────────────────────────
        $areaRange = $this->btuToAreaRange($recommendedBtu);

        // Split machine warning
        $note = null;
        if ($recommendedBtu >= 100000) {
            $note = "Công suất lớn hơn 100,000 BTU — khuyến nghị chia nhiều máy hoặc dùng hệ thống VRV/VRF. Vui lòng liên hệ kỹ thuật để khảo sát.";
        }

        // ── Explanation ──────────────────────────────────────
        $explanation = "Căn cứ diện tích {$areaMq}m², {$spaceLabel} ({$wPerM2} W/m²)" .
            ($ceilingH > 3 ? ", trần cao {$ceilingH}m" : '') .
            ($sunlight ? ", có nắng trực tiếp" : '') .
            ($heatEquip ? ", nhiều thiết bị sinh nhiệt" : '') .
            ($people > 10 ? ", {$people} người thường xuyên" : '') .
            ". Công suất tính toán là " . number_format($calculatedBtu) . " BTU" .
            " — đề xuất model " . number_format($recommendedBtu) . " BTU (~{$recommendedHp} HP)" .
            " để đảm bảo làm mát hiệu quả và dự phòng tải.";

        return [
            'calculated_btu'        => $calculatedBtu,
            'recommended_btu'       => $recommendedBtu,
            'recommended_hp'        => $recommendedHp,
            'cooling_w_per_m2'      => $wPerM2,
            'base_load_w'           => $baseLoadW,
            'area_range'            => $areaRange,
            'explanation'           => $explanation,
            'adjustment_breakdown'  => $adjustments,
            'steps'                 => $steps,
            'note'                  => $note,
            // BC compat with old keys
            'raw_btu'               => $calculatedBtu,
        ];
    }

    /**
     * Get full cooling load table for UI select options.
     *
     * @return array<string, string>  [key => "Label (xxx W/m²)"]
     */
    public static function spaceTypeOptions(): array
    {
        $svc = new self();
        $options = [];
        foreach ($svc->coolingLoadTable as $key => $data) {
            $options[$key] = $data['label_vi'] . ' (' . $data['w_per_m2'] . ' W/m²)';
        }
        return $options;
    }

    /**
     * Get simple label map for admin display.
     *
     * @return array<string, string>
     */
    public static function spaceTypeLabels(): array
    {
        $svc = new self();
        $labels = [];
        foreach ($svc->coolingLoadTable as $key => $data) {
            $labels[$key] = $data['label_vi'];
        }
        return $labels;
    }

    /**
     * Find matching products for recommended BTU.
     */
    public function matchProducts(int $recommendedBtu, string $priority = ''): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereNotNull('btu')
            ->where('btu', '>', 0);

        // Exclude out of stock
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'stock_status')) {
            $query->where(function ($q) {
                $q->whereNull('stock_status')
                  ->orWhere('stock_status', '!=', 'out_of_stock');
            });
        }

        // Products in BTU range (±1 tier)
        $lowerBound = $this->prevTier($recommendedBtu);
        $upperBound = $this->nextTier($recommendedBtu);

        $products = $query->whereBetween('btu', [$lowerBound, $upperBound])->get();

        // Widen if less than 4
        if ($products->count() < 4) {
            $products = Product::query()
                ->where('is_active', true)
                ->whereNotNull('btu')
                ->where('btu', '>', 0)
                ->whereBetween('btu', [
                    max(0, $recommendedBtu - 12000),
                    $recommendedBtu + 12000,
                ])->get();
        }

        // Sort by priority
        return match ($priority) {
            'tiet_kiem_dien'      => $products->sortBy('energy_rating')->values(),
            'gia_tot'             => $products->sortBy('sale_price')->values(),
            'thuong_hieu_cao_cap' => $products->sortByDesc('regular_price')->values(),
            default               => $products->sortBy(fn($p) => abs($p->btu - $recommendedBtu))->values(),
        };
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    protected function roundUpToTier(float $btu): int
    {
        foreach ($this->btuTiers as $tier) {
            if ($btu <= $tier) return $tier;
        }
        // Over max tier — return actual rounded up to 1000
        return (int) (ceil($btu / 1000) * 1000);
    }

    protected function prevTier(int $btu): int
    {
        $prev = $this->btuTiers[0];
        foreach ($this->btuTiers as $tier) {
            if ($tier >= $btu) return $prev;
            $prev = $tier;
        }
        return $prev;
    }

    protected function nextTier(int $btu): int
    {
        foreach ($this->btuTiers as $tier) {
            if ($tier > $btu) return $tier;
        }
        return end($this->btuTiers);
    }

    protected function btuToAreaRange(int $btu): string
    {
        // Based on 170 W/m² (general office) baseline: area ≈ BTU / (170 × 3.412)
        $map = [
            9000   => '10 – 15 m²',
            12000  => '15 – 20 m²',
            18000  => '20 – 30 m²',
            24000  => '25 – 40 m²',
            28000  => '35 – 48 m²',
            36000  => '45 – 62 m²',
            48000  => '60 – 83 m²',
            50000  => '65 – 86 m²',
            60000  => '80 – 103 m²',
            100000 => '100 m² trở lên',
        ];
        return $map[$btu] ?? (round($btu / (170 * self::W_TO_BTU)) . ' m² trở lên');
    }
}
