<?php

namespace App\Http\Controllers;

use App\Models\BtuCalculation;
use App\Models\Lead;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Mail\MailDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BtuCalculatorController extends Controller
{
    public function __construct(
        private readonly BtuCalculatorService $calculator,
        private readonly MailDispatchService  $mailService
    ) {}

    /**
     * GET /cong-cu/chon-cong-suat-dieu-hoa-tu-dung
     */
    public function index(Request $request)
    {
        $result   = null;
        $products = collect();
        $calc     = null;

        // Nếu có kết quả từ session (redirect back sau submit)
        if ($request->session()->has('btu_result')) {
            $result   = $request->session()->get('btu_result');
            $products = $request->session()->get('btu_products', []); // plain array
            $calc     = $request->session()->get('btu_calc');          // plain array
        }

        $seoTitle       = 'Công Cụ Tính Công Suất Điều Hòa Tủ Đứng - Chọn BTU Phù Hợp';
        $seoDescription = 'Tính toán công suất BTU điều hòa tủ đứng phù hợp với diện tích phòng, loại không gian. Gợi ý sản phẩm phù hợp ngay.';
        $canonical      = route('btu-calculator.index');

        return view('pages.btu-calculator', compact(
            'result', 'products', 'calc',
            'seoTitle', 'seoDescription', 'canonical'
        ));
    }

    /**
     * POST /cong-cu/chon-cong-suat-dieu-hoa-tu-dung
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'area_m2'        => ['required', 'numeric', 'min:5', 'max:5000'],
            'ceiling_height' => ['nullable', 'numeric', 'min:2', 'max:15'],
            'space_type'     => ['required', 'in:' . implode(',', array_keys(\App\Services\Calculator\BtuCalculatorService::spaceTypeLabels()))],
            'people_count'   => ['nullable', 'integer', 'min:0', 'max:5000'],
            'direct_sunlight'=> ['nullable', 'boolean'],
            'heat_equipment' => ['nullable', 'boolean'],
            'priority'       => ['nullable', 'in:tiet_kiem_dien,gia_tot,van_hanh_ben_bi,thuong_hieu_cao_cap'],
            // Contact optional
            'full_name'      => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'email', 'max:150'],
            'note'           => ['nullable', 'string', 'max:1000'],
        ], [
            'area_m2.required' => 'Vui lòng nhập diện tích phòng.',
            'area_m2.numeric'  => 'Diện tích phải là số.',
            'area_m2.min'      => 'Diện tích tối thiểu 5 m².',
            'space_type.required' => 'Vui lòng chọn loại không gian.',
            'space_type.in'    => 'Loại không gian không hợp lệ.',
        ]);

        $areaMq    = (float) $validated['area_m2'];
        $ceilingH  = (float) ($validated['ceiling_height'] ?? 3.0);
        $spaceType = $validated['space_type'];
        $people    = (int) ($validated['people_count'] ?? 0);
        $sunlight  = (bool) ($validated['direct_sunlight'] ?? false);
        $heatEquip = (bool) ($validated['heat_equipment'] ?? false);
        $priority  = $validated['priority'] ?? '';

        // Tính BTU
        $result = $this->calculator->calculate(
            $areaMq, $ceilingH, $spaceType, $people, $sunlight, $heatEquip
        );

        // Tìm sản phẩm phù hợp
        $products     = $this->calculator->matchProducts($result['recommended_btu'], $priority);
        $productIds   = $products->pluck('id')->take(8)->toArray();

        // Lưu lịch sử tính toán
        $calc = BtuCalculation::create([
            'area_m2'             => $areaMq,
            'ceiling_height'      => $ceilingH,
            'space_type'          => $spaceType,
            'people_count'        => $people ?: null,
            'direct_sunlight'     => $sunlight,
            'heat_equipment'      => $heatEquip,
            'priority'            => $priority ?: null,
            'recommended_btu'     => $result['recommended_btu'],
            'calculated_btu'      => $result['calculated_btu'] ?? $result['raw_btu'],
            'cooling_w_per_m2'    => $result['cooling_w_per_m2'] ?? null,
            'matched_product_ids' => $productIds,
            'full_name'           => $validated['full_name'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'note'                => $validated['note'] ?? null,
            'source_page'         => $request->header('referer') ?? url()->current(),
            'ip_address'          => $request->ip(),
            'user_agent'          => $request->userAgent(),
        ]);

        // Tạo lead nếu user nhập phone
        if (! empty($validated['phone'])) {
            try {
                $spaceLabel = \App\Services\Calculator\BtuCalculatorService::spaceTypeLabels()[$spaceType] ?? $spaceType;
                Lead::createConsultationLead([
                    'full_name'   => $validated['full_name'] ?? null,
                    'phone'       => $validated['phone'],
                    'email'       => $validated['email'] ?? null,
                    'source_page' => url()->current(),
                    'status'      => 'new',
                    'ip_address'  => $request->ip(),
                ], [
                    'need_type'    => 'btu_calculator',
                    'area'         => $areaMq,
                    'usage_type'   => $spaceType,
                    'capacity_btu' => $result['recommended_btu'],
                    'message'      => "BTU Calculator: " . number_format($result['recommended_btu']) . " BTU (~{$result['recommended_hp']} HP) | " .
                                     "Diện tích: {$areaMq}m² | " .
                                     "Loại: {$spaceLabel} ({$result['cooling_w_per_m2']} W/m²) | " .
                                     ($validated['note'] ?? ''),
                ]);
            } catch (\Throwable $e) {
                Log::warning('BTU Calculator lead creation failed: ' . $e->getMessage());
            }
        }

        // ── Admin mail — ALWAYS send (not dependent on phone) ────
        try {
            $spaceLabel = $spaceLabel ?? (\App\Services\Calculator\BtuCalculatorService::spaceTypeLabels()[$spaceType] ?? $spaceType);
            $adminVars = array_filter([
                'customer_name'  => $validated['full_name'] ?? null,
                'customer_phone' => $validated['phone'] ?? null,
                'customer_email' => $validated['email'] ?? null,
                'need_type'      => 'BTU Calculator',
                'area'           => $areaMq . 'm²',
                'btu'            => number_format($result['recommended_btu']) . ' BTU (~' . $result['recommended_hp'] . ' HP)',
                'message'        => "Loại: {$spaceLabel} ({$result['cooling_w_per_m2']} W/m²) | " .
                                   "Tính toán: " . number_format($result['calculated_btu']) . " BTU | " .
                                   "Đề xuất: " . number_format($result['recommended_btu']) . " BTU" .
                                   (!empty($validated['note']) ? ' | ' . $validated['note'] : ''),
                'source'         => url()->current(),
            ], fn ($v) => $v !== null && $v !== '');

            $this->mailService->sendEvent(
                event:       'lead_admin',
                vars:        $adminVars,
                adminEmail:  setting('lead.lead_notify_email', ''),
                relatedType: 'BtuCalculation',
                relatedId:   $calc->id
            );
        } catch (\Throwable $e) {
            Log::error('BTU admin mail failed: ' . $e->getMessage());
        }

        // ── Customer mail — only if email provided ────
        if (!empty($validated['email'])) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'lead_customer',
                    customerEmail: $validated['email'],
                    vars: array_filter([
                        'customer_name'  => $validated['full_name'] ?? null,
                        'customer_phone' => $validated['phone'] ?? null,
                        'need_type'      => 'Tính công suất BTU',
                        'area'           => $areaMq . 'm²',
                        'btu'            => number_format($result['recommended_btu']) . ' BTU (~' . $result['recommended_hp'] . ' HP)',
                        'message'        => $result['explanation'] ?? ('Đề xuất: ' . number_format($result['recommended_btu']) . ' BTU cho ' . $areaMq . 'm²'),
                    ], fn ($v) => $v !== null && $v !== ''),
                    relatedType: 'BtuCalculation',
                    relatedId:   $calc->id
                );
            } catch (\Throwable $e) {
                Log::error('BTU customer mail failed: ' . $e->getMessage());
            }
        }

        // Flash kết quả vào session — lưu dạng plain array, KHÔNG lưu Eloquent Model
        return redirect()
            ->route('btu-calculator.index')
            ->with('btu_result', $result)      // đã là array từ calculator service
            ->with('btu_products', $products->take(8)->map(fn($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'btu'           => $p->btu,
                'sale_price'    => $p->sale_price,
                'regular_price' => $p->regular_price,
                'main_image'    => $p->main_image,
            ])->values()->toArray())
            ->with('btu_calc', [   // plain array thay vì Eloquent Model
                'id'             => $calc->id,
                'area_m2'        => $calc->area_m2,
                'space_type'     => $calc->space_type,
                'ceiling_height' => $calc->ceiling_height,
                'recommended_btu'=> $calc->recommended_btu,
            ]);
    }
}
