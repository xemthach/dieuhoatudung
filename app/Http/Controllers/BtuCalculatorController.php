<?php

namespace App\Http\Controllers;

use App\Models\BtuCalculation;
use App\Models\Lead;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Calculator\EquipmentTypeRecommendationService;
use App\Services\Mail\MailDispatchService;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class BtuCalculatorController extends Controller
{
    public function __construct(
        private readonly BtuCalculatorService $calculator,
        private readonly EquipmentTypeRecommendationService $equipmentRecommendations,
        private readonly MailDispatchService $mailService,
        private readonly ProductMarketingCapacityQueryAdapter $capacityQuery,
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
        $seoDescription = 'Ước tính tham khảo công suất BTU theo diện tích hoặc thể tích không gian, sau đó gợi ý sản phẩm RAC có công suất không thấp hơn nhu cầu đã tính.';
        $canonical      = route('btu-calculator.index');
        $calculatorFaqs = config('hvac.btu.faq', []);

        return view('pages.btu-calculator', compact(
            'result', 'products', 'calc',
            'seoTitle', 'seoDescription', 'canonical', 'calculatorFaqs'
        ));
    }

    /**
     * POST /cong-cu/chon-cong-suat-dieu-hoa-tu-dung
     */
    public function calculate(Request $request)
    {
        // ── Spam protection ──────────────────────────────────────────
        if ($request->filled('website_url')) {
            return redirect()->route('btu-calculator.index');
        }

        $key = 'btu_calc:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()
                ->withInput()
                ->withErrors(['area_m2' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.']);
        }
        RateLimiter::hit($key, 3600);

        $validated = $request->validate([
            'method'         => ['required', 'in:area,volume'],
            'area_m2'        => ['required', 'numeric', 'min:5', 'max:5000'],
            'ceiling_height' => ['required_if:method,volume', 'nullable', 'numeric', 'min:2', 'max:15'],
            'space_type'     => ['required', 'in:' . implode(',', array_keys(\App\Services\Calculator\BtuCalculatorService::spaceTypeLabels()))],
            'people_count'   => ['nullable', 'integer', 'min:0', 'max:5000'],
            'direct_sunlight'=> ['nullable', 'boolean'],
            'heat_equipment' => ['nullable', 'boolean'],
            'priority'       => ['nullable', 'in:gia_tot'],
            'equipment_type' => ['nullable', 'in:' . implode(',', array_keys($this->equipmentRecommendations->options()))],
            'cassette_ceiling_clearance' => ['nullable', 'in:yes,no,unknown'],
            'duct_space' => ['nullable', 'in:yes,no,unknown'],
            // Contact optional
            'full_name'      => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'regex:/^(0|\+84)[0-9]{8,10}$/'],
            'email'          => ['nullable', 'email', 'max:150'],
            'note'           => ['nullable', 'string', 'max:1000'],
            // Tracking
            'utm_source'     => ['nullable', 'string', 'max:100'],
            'utm_medium'     => ['nullable', 'string', 'max:100'],
            'utm_campaign'   => ['nullable', 'string', 'max:100'],
            // Honeypot
            'website_url'    => ['nullable', 'max:0'],
        ], [
            'area_m2.required' => 'Vui lòng nhập diện tích phòng.',
            'area_m2.numeric'  => 'Diện tích phải là số.',
            'area_m2.min'      => 'Diện tích tối thiểu 5 m².',
            'space_type.required' => 'Vui lòng chọn loại không gian.',
            'space_type.in'    => 'Loại không gian không hợp lệ.',
            'phone.regex'      => 'Số điện thoại không hợp lệ.',
        ]);

        $areaMq    = (float) $validated['area_m2'];
        $method    = $validated['method'];
        $ceilingH  = (float) ($validated['ceiling_height'] ?? 3.0);
        $spaceType = $validated['space_type'];
        $people    = (int) ($validated['people_count'] ?? 0);
        $sunlight  = (bool) ($validated['direct_sunlight'] ?? false);
        $heatEquip = (bool) ($validated['heat_equipment'] ?? false);
        $priority  = $validated['priority'] ?? '';
        $equipmentType = $validated['equipment_type'] ?? 'unsure';

        // Tính BTU
        $result = $this->calculator->calculate(
            $areaMq, $ceilingH, $spaceType, $people, $sunlight, $heatEquip, $method
        );
        $factorDescription = ($result['factor_value'] ?? '—').' '.($result['factor_unit'] ?? '');

        // Đánh giá type sau bước tính. Công thức BTU không phụ thuộc Product,
        // brand hoặc AI; lớp này chỉ lọc catalog theo type/capacity đã xác minh.
        $typeRecommendation = $this->equipmentRecommendations->recommend(
            $result['recommended_btu'],
            $equipmentType,
            [
                'cassette_ceiling_clearance' => $validated['cassette_ceiling_clearance'] ?? 'unknown',
                'duct_space' => $validated['duct_space'] ?? 'unknown',
            ],
            $priority,
        );
        $products = $typeRecommendation['products'];
        $result['equipment_recommendation'] = $typeRecommendation['summary'];
        $productIds   = $products->pluck('id')->take(8)->toArray();
        $nearestAvailableBtu = $products
            ->map(fn ($product): ?int => $this->capacityQuery->value($product))
            ->filter(fn (?int $capacity): bool => $capacity !== null && $capacity >= $result['recommended_btu'])
            ->min();
        $result['nearest_available_product_btu'] = $nearestAvailableBtu;
        $result['catalog_gap_btu'] = $nearestAvailableBtu === null
            ? null
            : $nearestAvailableBtu - $result['recommended_btu'];

        // Non-PII bridge for Calculator -> Quote. The quote endpoint reads this
        // server-side, so technical values do not need to be trusted from a URL.
        $request->session()->put('quote_calculator_context', [
            'method' => $result['method'],
            'rule_version' => $result['rule_version'],
            'area_m2' => $areaMq,
            'ceiling_height' => $ceilingH,
            'space_type' => $spaceType,
            'people_count' => $people,
            'direct_sunlight' => $sunlight,
            'heat_equipment' => $heatEquip,
            'calculated_btu' => $result['calculated_btu'] ?? $result['raw_btu'],
            'recommended_btu' => $result['recommended_btu'],
            'requested_equipment_type' => $equipmentType,
            'requested_equipment_type_label' => $typeRecommendation['summary']['requested_type_label'],
            'recommendation_status' => $typeRecommendation['summary']['status'],
            'created_at' => now()->toISOString(),
        ]);

        // Chỉ lưu lịch sử khi người dùng chủ động để lại kênh liên hệ.
        $hasContact = ! empty($validated['phone']) || ! empty($validated['email']);
        $calc = $hasContact ? BtuCalculation::create([
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
            'rule_version'        => $result['rule_version'],
            'calculation_method'  => $result['method'],
            'matched_product_ids' => $productIds,
            'full_name'           => $validated['full_name'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'note'                => $validated['note'] ?? null,
            'source_page'         => route('btu-calculator.index'),
            'ip_address'          => null,
            'user_agent'          => null,
        ]) : null;

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
                                     "Loại: {$spaceLabel} ({$factorDescription}) | " .
                                     ($validated['note'] ?? ''),
                ]);
            } catch (\Throwable $e) {
                Log::warning('BTU Calculator lead creation failed: ' . $e->getMessage());
            }
        }

        // Chỉ gửi thông báo tư vấn khi người dùng đã cung cấp kênh liên hệ.
        if ($hasContact && $calc) {
            try {
                $spaceLabel = $spaceLabel ?? (\App\Services\Calculator\BtuCalculatorService::spaceTypeLabels()[$spaceType] ?? $spaceType);
                $adminVars = array_filter([
                    'customer_name'  => $validated['full_name'] ?? null,
                    'customer_phone' => $validated['phone'] ?? null,
                    'customer_email' => $validated['email'] ?? null,
                    'need_type'      => 'BTU Calculator',
                    'area'           => $areaMq . 'm²',
                    'btu'            => number_format($result['recommended_btu']) . ' BTU (~' . $result['recommended_hp'] . ' HP)',
                    'message'        => "Loại: {$spaceLabel} ({$factorDescription}) | " .
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
                    relatedId:   $calc?->id
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
                'btu'           => app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($p, 'marketing_capacity_btu')['value'] ?? null,
                'brand'         => $p->brand?->name,
                'equipment_type'=> app(\App\Services\Product\ProductEquipmentTypeResolver::class)->resolve($p)['type']?->value,
                'equipment_type_label' => app(\App\Services\Product\ProductEquipmentTypeResolver::class)->resolve($p)['type']?->label(),
                'sale_price'    => $p->sale_price,
                'regular_price' => $p->regular_price,
                'main_image'    => $p->main_image,
            ])->values()->toArray())
            ->with('btu_calc', [   // plain array thay vì Eloquent Model
                'id'             => $calc?->id,
                'method'         => $result['method'],
                'area_m2'        => $areaMq,
                'space_type'     => $spaceType,
                'ceiling_height' => $ceilingH,
                'recommended_btu'=> $result['recommended_btu'],
                'rule_version'   => $result['rule_version'],
                'equipment_type' => $equipmentType,
            ]);
    }
}
