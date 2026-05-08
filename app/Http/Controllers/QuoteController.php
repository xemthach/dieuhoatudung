<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Mail\MailDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class QuoteController extends Controller
{
    public function __construct(
        private readonly BtuCalculatorService $calculator,
        private readonly MailDispatchService  $mailService
    ) {}

    /**
     * GET /bao-gia
     */
    public function index(Request $request)
    {
        // Nếu có product slug được pass qua query string
        $product = null;
        if ($request->query('product')) {
            $product = Product::where('slug', $request->query('product'))
                ->where('is_active', true)
                ->first();
        }

        $thanks = $request->session()->get('quote_thanks');

        return view('pages.quote', [
            'product'          => $product,
            'thanks'           => $thanks,
            'seoTitle'         => setting('cta.quote_cta_text', 'Báo Giá') . ' Điều Hòa Tủ Đứng',
            'seoDescription'   => 'Điền form nhận báo giá điều hòa tủ đứng chính hãng. Tư vấn chọn công suất BTU phù hợp, lắp đặt trọn gói.',
            'canonical'        => route('quote.index'),
        ]);
    }


    /**
     * POST /bao-gia/nhanh  (AJAX — Quick Quote from product pages)
     * Only requires: full_name + phone. Product context in hidden fields.
     */
    public function storeQuick(Request $request)
    {
        // Honeypot
        if ($request->filled('website_url')) {
            return response()->json(['success' => true]); // silent
        }

        // Rate limit
        $key = 'quote_quick:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['success' => false, 'errors' => ['phone' => ['Quá nhiều yêu cầu. Thử lại sau.']]], 429);
        }
        RateLimiter::hit($key, 3600);

        $validated = $request->validate([
            'full_name'              => ['required', 'string', 'max:100'],
            'phone'                  => ['required', 'string', 'regex:/^(0|\+84)[0-9]{8,10}$/'],
            'email'                  => ['nullable', 'email', 'max:150'],
            'message'                => ['nullable', 'string', 'max:1000'],
            'product_id'             => ['nullable', 'integer', 'exists:products,id'],
            'product_name'           => ['nullable', 'string', 'max:255'],
            'product_sku'            => ['nullable', 'string', 'max:100'],
            'product_url'            => ['nullable', 'url', 'max:500'],
            'product_brand'          => ['nullable', 'string', 'max:100'],
            'product_category'       => ['nullable', 'string', 'max:100'],
            'product_capacity_btu'   => ['nullable', 'integer'],
            'source_page'            => ['nullable', 'url', 'max:500'],
            'utm_source'             => ['nullable', 'string', 'max:100'],
            'utm_medium'             => ['nullable', 'string', 'max:100'],
            'utm_campaign'           => ['nullable', 'string', 'max:100'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'phone.required'     => 'Vui lòng nhập số điện thoại.',
            'phone.regex'        => 'Số điện thoại không hợp lệ.',
        ]);

        $productModel = $validated['product_id']
            ? Product::with(['brand', 'category'])->find($validated['product_id'])
            : null;

        // Snapshot product data
        $snapshot = null;
        if ($productModel) {
            $snapshot = [
                'id'       => $productModel->id,
                'name'     => $productModel->name,
                'sku'      => $productModel->sku,
                'slug'     => $productModel->slug,
                'brand'    => $productModel->brand?->name,
                'category' => $productModel->category?->name,
                'btu'      => $productModel->btu,
                'url'      => $validated['product_url'] ?? route('product.show', $productModel->slug),
            ];
        }

        $quote = QuoteRequest::create([
            'lead_type'                  => 'product',
            'intent_score'               => 100,
            'full_name'                  => $validated['full_name'],
            'phone'                      => $validated['phone'],
            'message'                    => $validated['message'] ?? null,
            'email'                      => $validated['email'] ?? null,
            'product_id'                 => $validated['product_id'] ?? null,
            'product_name'               => $validated['product_name'] ?? $productModel?->name,
            'product_sku'                => $validated['product_sku'] ?? $productModel?->sku,
            'product_url'                => $validated['product_url'] ?? null,
            // Prefer POST (hidden fields from modal) → fallback to DB model
            'product_brand'              => $validated['product_brand'] ?? $productModel?->brand?->name,
            'product_category'           => $validated['product_category'] ?? $productModel?->category?->name,
            'product_capacity_btu'       => $validated['product_capacity_btu'] ?? $productModel?->btu,
            'selected_product_snapshot'  => $snapshot,
            'source_page'                => $validated['source_page'] ?? url()->current(),
            'utm_source'                 => $validated['utm_source'] ?? null,
            'utm_medium'                 => $validated['utm_medium'] ?? null,
            'utm_campaign'               => $validated['utm_campaign'] ?? null,
            'status'                     => 'new',
            'ip_address'                 => $request->ip(),
            'user_agent'                 => $request->userAgent(),
        ]);

        // Build mail vars — only include fields that have real data
        // Product quote email should NOT contain HVAC technical fields
        $mailVars = array_filter([
            'quote_id'             => $quote->id,
            'lead_type'            => 'Product Quote',
            'intent_score'         => 100,
            'customer_name'        => $quote->full_name,
            'customer_phone'       => $quote->phone,
            'product_name'         => $quote->product_name,
            'product_sku'          => $quote->product_sku,
            'product_brand'        => $quote->product_brand,
            'product_category'     => $quote->product_category,
            'product_capacity_btu' => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : null,
            'product_url'          => $quote->product_url,
            'message'              => $quote->message,
            'customer_note'        => $quote->message,
            'customer_email'       => $quote->email,
            'source'               => $quote->source_page,
            'utm_source'           => $quote->utm_source,
            'utm_campaign'         => $quote->utm_campaign,
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);


        // Admin mail
        try {
            $this->mailService->sendEvent(
                event:       'quote_admin',
                vars:        $mailVars,
                adminEmail:  setting('mail_notify.quote_notify_email') ?: setting('lead.lead_notify_email', ''),
                relatedType: 'QuoteRequest',
                relatedId:   $quote->id
            );
        } catch (\Throwable $e) {
            Log::error('QuickQuote admin mail: ' . $e->getMessage());
        }

        // Create Lead
        try {
            $contactData = ['full_name' => $quote->full_name, 'phone' => $quote->phone, 'source_page' => $quote->source_page, 'status' => 'new', 'ip_address' => $request->ip()];
            $extraData   = ['quote_request_id' => $quote->id, 'need_type' => 'quick_product_quote', 'message' => 'Quick quote | ' . ($quote->product_name ?? '')];
            if ($productModel) {
                Lead::createProductLead($contactData, $productModel, $extraData);
            } else {
                Lead::createGeneralLead($contactData, $extraData);
            }
        } catch (\Throwable $e) {
            Log::error('QuickQuote Lead creation: ' . $e->getMessage());
        }

        // Customer mail — only if email provided
        if (! empty($quote->email)) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'quote_customer',
                    customerEmail: $quote->email,
                    vars:          array_filter([
                        'customer_name'        => $quote->full_name,
                        'customer_phone'       => $quote->phone,
                        'customer_email'       => $quote->email,
                        'product_name'         => $quote->product_name,
                        'product_sku'          => $quote->product_sku,
                        'product_capacity_btu' => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : null,
                        'product_url'          => $quote->product_url,
                    ], fn ($v) => $v !== null && $v !== ''),
                    relatedType:   'QuoteRequest',
                    relatedId:     $quote->id
                );
            } catch (\Throwable $e) {
                Log::error('QuickQuote customer mail: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'quote_id' => $quote->id]);
    }

    /**
     * POST /bao-gia
     */
    public function store(Request $request)
    {
        // ── Spam protection ──────────────────────────────────────────
        // Rate limiting: max 5 submissions per IP per hour
        $rateLimitKey = 'quote_submit:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return back()
                ->withInput()
                ->withErrors(['__global' => 'Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau.']);
        }

        // Honeypot: bot-only field — must be empty for real users
        if ($request->filled('website_url')) {
            // Silent redirect — don't reveal the trap
            return redirect()->route('quote.index');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $requirePhone = setting('lead.lead_required_phone', true);
        $requireEmail = setting('lead.lead_required_email', false);

        $validated = $request->validate([
            // Step 1 — Project
            'lead_type'                => ['nullable','in:product,general,consultation'],
            'project_type'             => ['nullable','in:nha_o,can_ho,van_phong,cua_hang,showroom,nha_hang,hoi_truong,nha_xuong,truong_hoc,khach_san,khac'],
            'usage_description'        => ['nullable','string','max:500'],
            'number_of_rooms'          => ['nullable','integer','min:1','max:500'],
            // Step 2 — Space
            'area_m2'                  => ['nullable','numeric','min:1','max:50000'],
            'ceiling_height'           => ['nullable','numeric','min:1','max:20'],
            'number_of_people'         => ['nullable','integer','min:0','max:5000'],
            'sun_exposure'             => ['nullable','in:it_nang,nang_vua,nang_nhieu'],
            'insulation_quality'       => ['nullable','in:tot,trung_binh,kem,chua_ro'],
            'glass_area'               => ['nullable','in:it_kinh,nhieu_kinh,vach_kinh'],
            'open_space'               => ['nullable','boolean'],
            'current_aircon_status'    => ['nullable','in:chua_co,co_nhung_yeu,thay_cu,can_them'],
            // Step 3 — Technical
            'preferred_btu'            => ['nullable','integer','min:9000'],
            'preferred_brands'         => ['nullable','array'],
            'preferred_brands.*'       => ['nullable','string','max:50'],
            'preferred_brand'          => ['nullable','string','max:255'],
            'need_inverter'            => ['nullable','boolean'],
            'need_three_phase'         => ['nullable','boolean'],
            'power_supply'             => ['nullable','in:1_pha,3_pha,chua_ro'],
            'installation_type'        => ['nullable','in:lap_moi,thay_cu,di_doi,bao_tri'],
            'pipe_distance_m'          => ['nullable','numeric','min:0','max:200'],
            'outdoor_unit_location'    => ['nullable','in:ban_cong,mai_nha,tuong_ngoai,san_thuong,chua_ro'],
            'drainage_available'       => ['nullable','in:co,khong,chua_ro'],
            'has_existing_piping'      => ['nullable','in:co,khong,chua_ro'],
            // Step 4 — Budget
            'budget_range'             => ['nullable','in:duoi_20_trieu,20_40_trieu,40_70_trieu,tren_70_trieu,chua_ro'],
            'installation_time'        => ['nullable','in:ngay,3_ngay,1_tuan,1_thang,chua_ro'],
            'need_installation_service'=> ['nullable','in:tron_goi,chi_may,chua_ro'],
            'need_invoice'             => ['nullable','boolean'],
            'need_site_survey'         => ['nullable','boolean'],
            // Step 5 — Contact
            'full_name'                => ['required','string','max:100'],
            'phone'                    => [$requirePhone ? 'required' : 'nullable','string','max:20'],
            'email'                    => [$requireEmail ? 'required' : 'nullable','nullable','email','max:150'],
            'province_city'            => ['nullable','string','max:100'],
            'address'                  => ['nullable','string','max:255'],
            'preferred_contact_method' => ['nullable','in:phone,zalo,email'],
            'preferred_contact_time'   => ['nullable','in:ngay,hanh_chinh,buoi_toi,khac'],
            'message'                  => ['nullable','string','max:2000'],
            // Hidden meta
            'product_id'               => ['nullable','integer','exists:products,id'],
            'source_page'              => ['nullable','string','max:500'],
            'landing_page'             => ['nullable','string','max:500'],
            'referrer'                 => ['nullable','string','max:500'],
            'utm_source'               => ['nullable','string','max:100'],
            'utm_medium'               => ['nullable','string','max:100'],
            'utm_campaign'             => ['nullable','string','max:100'],
            'utm_term'                 => ['nullable','string','max:100'],
            'utm_content'              => ['nullable','string','max:100'],
            'website_url'              => ['nullable','max:0'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'phone.required'     => 'Vui lòng nhập số điện thoại.',
            'email.email'        => 'Email không đúng định dạng.',
        ]);

        // ── Calculate BTU with env conditions ────────────────────────
        $calculatedBtu = $validated['preferred_btu'] ?? null;
        $suggestedRange = null;
        if (! empty($validated['area_m2'])) {
            try {
                $areaMq = (float) $validated['area_m2'];
                $height = (float) ($validated['ceiling_height'] ?? 3.0);
                $people = (int) ($validated['number_of_people'] ?? 0);
                // Base: 600–800 BTU/m2
                $baseBtu = $areaMq * 700;
                // Volume correction if ceiling > 3m
                if ($height > 3) { $baseBtu *= ($areaMq * $height) / ($areaMq * 3); }
                // Sun exposure
                if (($validated['sun_exposure'] ?? '') === 'nang_nhieu') $baseBtu *= 1.15;
                elseif (($validated['sun_exposure'] ?? '') === 'nang_vua') $baseBtu *= 1.08;
                // Glass area
                if (($validated['glass_area'] ?? '') === 'vach_kinh') $baseBtu *= 1.12;
                elseif (($validated['glass_area'] ?? '') === 'nhieu_kinh') $baseBtu *= 1.07;
                // People (300 BTU/person)
                $baseBtu += $people * 300;
                // Insulation correction
                if (($validated['insulation_quality'] ?? '') === 'kem') $baseBtu *= 1.10;
                $calculatedBtu = (int) round($baseBtu / 1000) * 1000;
                // BTU brackets for suggested range
                $brackets = [9000,12000,18000,24000,28000,36000,42000,48000,60000,100000];
                $lower = collect($brackets)->last(fn($b) => $b <= $calculatedBtu) ?? $brackets[0];
                $upper = collect($brackets)->first(fn($b) => $b >= $calculatedBtu) ?? last($brackets);
                $suggestedRange = $lower === $upper ? number_format($lower).' BTU' : number_format($lower).'-'.number_format($upper).' BTU';

                $matchedProducts = $this->calculator->matchProducts($calculatedBtu, '');
                $recommendedProductIds = $matchedProducts->pluck('id')->take(6)->toArray();
            } catch (\Throwable $e) {
                Log::warning('QuoteRequest BTU calc failed: ' . $e->getMessage());
            }
        }

        // ── Resolve product + build snapshot ─────────────────────────
        $productModel = !empty($validated['product_id'])
            ? Product::with(['brand','category'])->find($validated['product_id'])
            : null;
        $productSnapshot = $productModel ? [
            'id'       => $productModel->id,
            'name'     => $productModel->name,
            'sku'      => $productModel->sku,
            'model'    => $productModel->model_code,
            'brand'    => $productModel->brand?->name,
            'category' => $productModel->category?->name,
            'btu'      => $productModel->btu,
            'price'    => $productModel->sale_price ?? $productModel->regular_price,
            'url'      => route('product.show', $productModel->slug),
            'snapshot_at' => now()->toISOString(),
        ] : null;

        // ── Intent score ──────────────────────────────────────────────
        $intentScore = QuoteRequest::calculateIntentScore(array_merge($validated, [
            'product_id' => $productModel?->id,
        ]));
        $leadType = $productModel ? 'product' : ($validated['lead_type'] ?? 'general');

        // ── Tạo QuoteRequest ─────────────────────────────────────────
        $quote = QuoteRequest::create([
            'lead_type'                => $leadType,
            'intent_score'             => $intentScore,
            // Product metadata
            'product_id'               => $productModel?->id,
            'product_name'             => $productModel?->name,
            'product_sku'              => $productModel?->sku,
            'product_model'            => $productModel?->model_code,
            'product_brand'            => $productModel?->brand?->name,
            'product_category'         => $productModel?->category?->name,
            'product_capacity_btu'     => $productModel?->btu,
            'product_url'              => $productModel ? route('product.show', $productModel->slug) : null,
            'selected_product_snapshot'=> $productSnapshot,
            // Step 1
            'project_type'             => $validated['project_type'] ?? null,
            'usage_description'        => $validated['usage_description'] ?? null,
            'number_of_rooms'          => $validated['number_of_rooms'] ?? 1,
            // Step 2
            'area_m2'                  => $validated['area_m2'] ?? null,
            'ceiling_height'           => $validated['ceiling_height'] ?? null,
            'estimated_volume_m3'      => isset($validated['area_m2'], $validated['ceiling_height'])
                ? round((float)$validated['area_m2'] * (float)$validated['ceiling_height'], 2) : null,
            'number_of_people'         => $validated['number_of_people'] ?? null,
            'sun_exposure'             => $validated['sun_exposure'] ?? null,
            'insulation_quality'       => $validated['insulation_quality'] ?? null,
            'glass_area'               => $validated['glass_area'] ?? null,
            'open_space'               => (bool) ($validated['open_space'] ?? false),
            'current_aircon_status'    => $validated['current_aircon_status'] ?? null,
            // Step 3
            'preferred_btu'            => $validated['preferred_btu'] ?? null,
            'calculated_btu'           => $calculatedBtu,
            'suggested_capacity_range' => $suggestedRange,
            'preferred_brand'          => $validated['preferred_brand'] ?? null,
            'preferred_brands'         => $validated['preferred_brands'] ?? null,
            'need_inverter'            => (bool) ($validated['need_inverter'] ?? false),
            'need_three_phase'         => (bool) ($validated['need_three_phase'] ?? false),
            'power_supply'             => $validated['power_supply'] ?? null,
            'installation_type'        => $validated['installation_type'] ?? null,
            'pipe_distance_m'          => $validated['pipe_distance_m'] ?? null,
            'outdoor_unit_location'    => $validated['outdoor_unit_location'] ?? null,
            'drainage_available'       => $validated['drainage_available'] ?? null,
            'has_existing_piping'      => $validated['has_existing_piping'] ?? null,
            // Step 4
            'budget_range'             => $validated['budget_range'] ?? null,
            'installation_time'        => $validated['installation_time'] ?? null,
            'need_installation_service'=> $validated['need_installation_service'] ?? null,
            'need_invoice'             => (bool) ($validated['need_invoice'] ?? false),
            'need_site_survey'         => (bool) ($validated['need_site_survey'] ?? false),
            // Step 5
            'full_name'                => $validated['full_name'],
            'phone'                    => $validated['phone'] ?? null,
            'email'                    => $validated['email'] ?? null,
            'province_city'            => $validated['province_city'] ?? null,
            'address'                  => $validated['address'] ?? null,
            'preferred_contact_method' => $validated['preferred_contact_method'] ?? null,
            'preferred_contact_time'   => $validated['preferred_contact_time'] ?? null,
            'message'                  => $validated['message'] ?? null,
            // Tracking
            'source_page'              => $validated['source_page'] ?? url()->current(),
            'landing_page'             => $validated['landing_page'] ?? null,
            'referrer'                 => $validated['referrer'] ?? null,
            'utm_source'               => $validated['utm_source'] ?? null,
            'utm_medium'               => $validated['utm_medium'] ?? null,
            'utm_campaign'             => $validated['utm_campaign'] ?? null,
            'utm_term'                 => $validated['utm_term'] ?? null,
            'utm_content'              => $validated['utm_content'] ?? null,
            'recommended_product_ids'  => $recommendedProductIds ?: null,
            'status'                   => 'new',
            'ip_address'               => $request->ip(),
            'user_agent'               => $request->userAgent(),
        ]);

        // ── Build shared label map ─────────────────────────────────────
        $projectLabel     = QuoteRequest::projectTypeLabels()[$validated['project_type'] ?? ''] ?? 'Chưa rõ';
        $budgetLabel      = QuoteRequest::budgetRangeLabels()[$validated['budget_range'] ?? ''] ?? 'Chưa rõ';
        $timelineLabel    = QuoteRequest::installationTimeLabels()[$validated['installation_time'] ?? ''] ?? 'Chưa xác định';
        $sunLabel         = QuoteRequest::sunExposureLabels()[$validated['sun_exposure'] ?? ''] ?? '—';
        $insulationLabel  = QuoteRequest::insulationLabels()[$validated['insulation_quality'] ?? ''] ?? '—';
        $glassLabel       = QuoteRequest::glassAreaLabels()[$validated['glass_area'] ?? ''] ?? '—';
        $airconLabel      = QuoteRequest::airconStatusLabels()[$validated['current_aircon_status'] ?? ''] ?? '—';
        $installLabel     = QuoteRequest::installationTypeLabels()[$validated['installation_type'] ?? ''] ?? '—';
        $outdoorLabel     = QuoteRequest::outdoorLocationLabels()[$validated['outdoor_unit_location'] ?? ''] ?? '—';
        $needInstallLabel = QuoteRequest::needInstallLabels()[$validated['need_installation_service'] ?? ''] ?? '—';
        $contactLabel     = QuoteRequest::contactMethodLabels()[$validated['preferred_contact_method'] ?? ''] ?? '—';
        $contactTimeLabel = QuoteRequest::contactTimeLabels()[$validated['preferred_contact_time'] ?? ''] ?? '—';
        $brandsStr        = implode(', ', $validated['preferred_brands'] ?? []) ?: '—';

        // ── Also create typed Lead ───────────────────────────────────
        try {
            $contactData = [
                'full_name'   => $validated['full_name'],
                'phone'       => $validated['phone'] ?? null,
                'email'       => $validated['email'] ?? null,
                'source_page' => $quote->source_page,
                'status'      => 'new',
                'ip_address'  => $request->ip(),
            ];
            $extraData = [
                'quote_request_id' => $quote->id,
                'usage_type'       => $validated['project_type'] ?? null,
                'area'             => $validated['area_m2'] ?? null,
                'message'          => "Báo giá | {$projectLabel} | {$budgetLabel}" .
                    ($calculatedBtu ? ' | BTU: '.number_format($calculatedBtu) : ''),
                'need_type'        => 'quote_request',
            ];
            if ($productModel) {
                Lead::createProductLead($contactData, $productModel, $extraData);
            } else {
                Lead::createGeneralLead($contactData, $extraData);
            }
        } catch (\Throwable $e) {
            Log::error('QuoteRequest Lead creation failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
        }

        // ── Mail vars — only include fields with real data ──────────
        $brandsArr = is_array($quote->preferred_brands) ? $quote->preferred_brands : [];
        $rawMailVars = [
            'quote_id'                 => $quote->id,
            'lead_type'                => $quote->lead_type === 'product' ? 'Product Quote' : 'General Quote',
            'intent_score'             => $quote->intent_score ?? 0,
            // Customer
            'customer_name'            => $quote->full_name,
            'customer_phone'           => $quote->phone,
            'customer_email'           => $quote->email,
            'province_city'            => $quote->province_city,
            'address'                  => $quote->address,
            'preferred_contact_method' => !empty($quote->preferred_contact_method) ? (QuoteRequest::contactMethodLabels()[$quote->preferred_contact_method] ?? $quote->preferred_contact_method) : null,
            'preferred_contact_time'   => !empty($quote->preferred_contact_time) ? (QuoteRequest::contactTimeLabels()[$quote->preferred_contact_time] ?? $quote->preferred_contact_time) : null,
            // Product (only if product lead)
            'product_name'             => $quote->product_name ?? $productModel?->name,
            'product_sku'              => $quote->product_sku ?? $productModel?->sku,
            'product_model'            => $quote->product_model ?? $productModel?->model_code,
            'product_brand'            => $quote->product_brand ?? $productModel?->brand?->name,
            'product_category'         => $quote->product_category ?? $productModel?->category?->name,
            'product_capacity_btu'     => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : null,
            'product_url'              => $quote->product_url,
            // Space
            'project_type'             => !empty($quote->project_type) ? (QuoteRequest::projectTypeLabels()[$quote->project_type] ?? $quote->project_type) : null,
            'usage_description'        => $quote->usage_description,
            'number_of_rooms'          => ($quote->number_of_rooms && $quote->number_of_rooms > 1) ? $quote->number_of_rooms : null,
            'area_m2'                  => $quote->area_m2 ? $quote->area_m2 . ' m²' : null,
            'ceiling_height_m'         => $quote->ceiling_height ? $quote->ceiling_height . ' m' : null,
            'estimated_volume_m3'      => $quote->estimated_volume_m3 ? $quote->estimated_volume_m3 . ' m³' : null,
            'number_of_people'         => $quote->number_of_people ?: null,
            'sun_exposure'             => !empty($quote->sun_exposure) ? (QuoteRequest::sunExposureLabels()[$quote->sun_exposure] ?? $quote->sun_exposure) : null,
            'glass_area'               => !empty($quote->glass_area) ? (QuoteRequest::glassAreaLabels()[$quote->glass_area] ?? $quote->glass_area) : null,
            'insulation_quality'       => !empty($quote->insulation_quality) ? (QuoteRequest::insulationLabels()[$quote->insulation_quality] ?? $quote->insulation_quality) : null,
            'current_aircon_status'    => !empty($quote->current_aircon_status) ? (QuoteRequest::airconStatusLabels()[$quote->current_aircon_status] ?? $quote->current_aircon_status) : null,
            // Technical
            'desired_capacity_btu'     => $quote->preferred_btu ? number_format($quote->preferred_btu) . ' BTU' : null,
            'calculated_btu'           => $quote->calculated_btu ? number_format($quote->calculated_btu) . ' BTU' : null,
            'btu'                      => $quote->calculated_btu ? number_format($quote->calculated_btu) . ' BTU' : null,
            'suggested_capacity_range' => $quote->suggested_capacity_range,
            'preferred_brands'         => implode(', ', $brandsArr) ?: null,
            'require_inverter'         => $quote->need_inverter ? 'Co' : null,
            'require_3_phase'          => $quote->need_three_phase ? 'Co' : null,
            'power_supply'             => $quote->power_supply,
            'installation_type'        => !empty($quote->installation_type) ? (QuoteRequest::installationTypeLabels()[$quote->installation_type] ?? $quote->installation_type) : null,
            'outdoor_unit_location'    => !empty($quote->outdoor_unit_location) ? (QuoteRequest::outdoorLocationLabels()[$quote->outdoor_unit_location] ?? $quote->outdoor_unit_location) : null,
            'pipe_distance_m'          => $quote->pipe_distance_m ? $quote->pipe_distance_m . ' m' : null,
            'drainage_available'       => $quote->drainage_available,
            // Budget
            'budget_range'             => !empty($quote->budget_range) ? (QuoteRequest::budgetRangeLabels()[$quote->budget_range] ?? $quote->budget_range) : null,
            'timeline'                 => !empty($quote->installation_time) ? (QuoteRequest::installationTimeLabels()[$quote->installation_time] ?? $quote->installation_time) : null,
            'need_installation_service'=> !empty($quote->need_installation_service) ? (QuoteRequest::needInstallLabels()[$quote->need_installation_service] ?? $quote->need_installation_service) : null,
            'need_invoice'             => $quote->need_invoice ? 'Co' : null,
            'need_site_survey'         => $quote->need_site_survey ? 'Co' : null,
            // Tracking
            'source'                   => $quote->source_page,
            'utm_source'               => $quote->utm_source,
            'utm_campaign'             => $quote->utm_campaign,
            // Misc
            'customer_note'            => $quote->message,
            'message'                  => $quote->message,
        ];
        // Filter out null/empty values so email only shows fields with data
        $mailVars = array_filter($rawMailVars, fn ($v) => $v !== null && $v !== '');


        // ── Admin mail ────────────────────────────────────────────────
        try {
            $this->mailService->sendEvent(
                event:       'quote_admin',
                vars:        $mailVars,
                adminEmail:  setting('mail_notify.quote_notify_email') ?: setting('lead.lead_notify_email', ''),
                relatedType: 'QuoteRequest',
                relatedId:   $quote->id
            );
        } catch (\Throwable $e) {
            Log::error('QuoteRequest admin mail failed: ' . $e->getMessage());
        }

        // ── Customer mail ───────────────────────────────────────────
        if (! empty($quote->email)) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'quote_customer',
                    customerEmail: $quote->email,
                    vars:          array_merge($mailVars, ['hotline' => setting('contact.hotline', '')]),
                    relatedType:   'QuoteRequest',
                    relatedId:     $quote->id
                );
            } catch (\Throwable $e) {
                Log::error('QuoteRequest customer mail failed: ' . $e->getMessage());
            }
        }

        // ── Lấy suggested products để hiển thị thank you ─────────────
        $suggestedProducts = collect(); // luôn là Collection, không bao giờ là array
        if (! empty($recommendedProductIds)) {
            $suggestedProducts = Product::whereIn('id', $recommendedProductIds)
                ->where('is_active', true)
                ->take(4)
                ->get();
        } elseif ($validated['product_id'] ?? null) {
            $suggestedProducts = Product::where('id', $validated['product_id'])
                ->where('is_active', true)
                ->get();
        }

        return redirect()
            ->route('quote.index')
            ->with('quote_thanks', [
                'quote_id'          => $quote->id,
                'full_name'         => $quote->full_name,
                'phone'             => $quote->phone,
                'recommended_btu'   => $calculatedBtu,
                // Tracking data
                'lead_type'         => $productModel ? 'product' : 'general',
                'product_id'        => $productModel?->id,
                'product_name'      => $productModel?->name,
                'intent_score'      => $productModel ? Lead::SCORE_PRODUCT : Lead::SCORE_GENERAL,
                // Chuyển sang plain array để tránh lỗi serialize Eloquent trong session
                'suggested_products' => $suggestedProducts->map(fn($p) => [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'slug'         => $p->slug,
                    'btu'          => $p->btu,
                    'sale_price'   => $p->sale_price,
                    'regular_price'=> $p->regular_price,
                    'main_image'   => $p->main_image,
                ])->values()->toArray(),
            ]);
    }
}
