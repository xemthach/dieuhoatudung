<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuickQuoteRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteRequest;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Mail\MailDispatchService;
use App\Services\Marketing\GoogleAdsOfflineConversionService;
use App\Services\Quote\QuoteSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class QuoteController extends Controller
{
    public function __construct(
        private readonly BtuCalculatorService $calculator,
        private readonly MailDispatchService $mailService,
        private readonly QuoteSubmissionService $submissions,
    ) {}

    /**
     * GET /bao-gia
     */
    public function index(Request $request)
    {
        $product = null;
        if ($request->query('product')) {
            $product = Product::where('slug', $request->query('product'))
                ->where('is_active', true)
                ->first();
        }

        $calculatorContext = $request->query('source') === 'calculator'
            ? $request->session()->get('quote_calculator_context')
            : null;
        $calculatorContext = is_array($calculatorContext) ? $calculatorContext : null;

        $brand = $request->query('brand')
            ? Brand::query()->where('slug', $request->query('brand'))->first()
            : null;
        $category = $request->query('category')
            ? ProductCategory::query()->where('slug', $request->query('category'))->first()
            : null;

        $entryContext = $product
            ? 'product'
            : ($calculatorContext
                ? 'calculator'
                : (($brand || $category)
                    ? 'category'
                    : ($request->hasAny(['utm_source', 'utm_campaign', 'gclid', 'gbraid', 'wbraid']) ? 'campaign' : 'direct')));

        $thanks = $request->session()->get('quote_thanks');

        return view('pages.quote', [
            'product'          => $product,
            'calculatorContext'=> $calculatorContext,
            'brandContext'     => $brand?->only(['id', 'name', 'slug']),
            'categoryContext'  => $category?->only(['id', 'name', 'slug']),
            'entryContext'     => $entryContext,
            'submissionToken'  => old('submission_token', (string) Str::uuid()),
            'thanks'           => $thanks,
            'seoTitle'         => setting('cta.quote_cta_text', 'Báo Giá') . ' Điều Hòa Tủ Đứng',
            'seoDescription'   => 'Điền form nhận báo giá điều hòa tủ đứng. Tư vấn chọn công suất BTU theo dữ liệu khảo sát và điều kiện công trình.',
            'canonical'        => route('quote.index'),
        ]);
    }


    /**
     * POST /bao-gia/nhanh  (AJAX — Quick Quote from product pages)
     * Only requires: full_name + phone. Product context in hidden fields.
     */
    public function storeQuick(StoreQuickQuoteRequest $request)
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

        $validated = $request->validated();

        $productModel = ! empty($validated['product_id'])
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
                'btu'      => app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($productModel, 'marketing_capacity_btu')['value'] ?? null,
                'url'      => $validated['product_url'] ?? route('product.show', $productModel->slug),
            ];
        }

        $submission = $this->submissions->create([
            'submission_token'           => $validated['submission_token'],
            'entry_context'              => 'product',
            'provided_fields'            => array_values(array_keys($validated)),
            'lead_type'                  => 'product',
            'intent_score'               => 100,
            'full_name'                  => $validated['full_name'],
            'phone'                      => $validated['phone'],
            'message'                    => $validated['message'] ?? null,
            'email'                      => $validated['email'] ?? null,
            'province_city'              => $validated['province_city'] ?? null,
            'product_id'                 => $validated['product_id'] ?? null,
            'product_name'               => $validated['product_name'] ?? $productModel?->name,
            'product_sku'                => $validated['product_sku'] ?? $productModel?->sku,
            'product_url'                => $validated['product_url'] ?? null,
            // Prefer POST (hidden fields from modal) → fallback to DB model
            'product_brand'              => $validated['product_brand'] ?? $productModel?->brand?->name,
            'product_category'           => $validated['product_category'] ?? $productModel?->category?->name,
            'product_capacity_btu'       => $validated['product_capacity_btu'] ?? ($productModel ? app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($productModel, 'marketing_capacity_btu')['value'] : null),
            'selected_product_snapshot'  => $snapshot,
            'source_page'                => $validated['source_page'] ?? url()->current(),
            'utm_source'                 => $validated['utm_source'] ?? null,
            'utm_medium'                 => $validated['utm_medium'] ?? null,
            'utm_campaign'               => $validated['utm_campaign'] ?? null,
            'gclid'                      => $validated['gclid'] ?? null,
            'gbraid'                     => $validated['gbraid'] ?? null,
            'wbraid'                     => $validated['wbraid'] ?? null,
            'status'                     => 'new',
            'ip_address'                 => $request->ip(),
            'user_agent'                 => $request->userAgent(),
        ], [
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'source_page' => $validated['source_page'] ?? url()->current(),
            'status' => 'new',
            'ip_address' => $request->ip(),
        ], [
            'need_type' => 'quick_product_quote',
            'message' => 'Quick quote | '.($productModel?->name ?? ''),
        ], $productModel);

        /** @var QuoteRequest $quote */
        $quote = $submission['quote'];

        if (! $submission['created']) {
            return response()->json(['success' => true, 'quote_id' => $quote->id, 'duplicate' => true]);
        }

        $this->recordGoogleAdsOfflineConversion($quote, $request, 'quick_quote');

        // ── Build mail vars with proper fallbacks ──────────────────────
        $mailVars = [
            'quote_id'             => $quote->id,
            'lead_type'            => 'Product Quote',
            'intent_score'         => 100,
            'customer_name'        => $quote->full_name,
            'customer_phone'       => $quote->phone,
            'customer_email'       => $quote->email ?: 'Chưa cung cấp',
            'province_city'        => $quote->province_city ?: 'Chưa cung cấp',
            'address'              => 'Chưa cung cấp',
            'product_name'         => $quote->product_name ?: 'Chưa chọn sản phẩm',
            'product_sku'          => $quote->product_sku ?: '—',
            'product_brand'        => $quote->product_brand ?: '—',
            'product_category'     => $quote->product_category ?: '—',
            'product_capacity_btu' => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : 'Chưa xác định',
            'product_url'          => $quote->product_url ?: '',
            'project_type'         => 'Chưa cung cấp',
            'area_m2'              => 'Chưa cung cấp',
            'btu'                  => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : 'Chưa xác định',
            'budget_range'         => 'Chưa cung cấp',
            'message'              => $quote->message ?: 'Không có ghi chú',
            'customer_note'        => $quote->message ?: 'Không có ghi chú',
            'source'               => $quote->source_page ?: url()->current(),
            'utm_source'           => $quote->utm_source ?: '',
            'utm_campaign'         => $quote->utm_campaign ?: '',
        ];

        // Keep quote mail diagnostics free of customer PII.
        Log::debug('QuickQuote mail payload prepared', [
            'quote_id'   => $quote->id,
            'var_keys'   => array_keys($mailVars),
            'empty_keys' => array_keys(array_filter($mailVars, fn ($v) => empty($v))),
        ]);

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
            Log::error('QuickQuote admin mail failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
        }

        // Customer mail — only if email provided
        if (! empty($quote->email)) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'quote_customer',
                    customerEmail: $quote->email,
                    vars:          [
                        'quote_id'             => $quote->id,
                        'customer_name'        => $quote->full_name,
                        'customer_phone'       => $quote->phone,
                        'customer_email'       => $quote->email,
                        'province_city'        => $quote->province_city ?: 'Chưa cung cấp',
                        'product_name'         => $quote->product_name ?: 'Chưa chọn sản phẩm',
                        'product_sku'          => $quote->product_sku ?: '—',
                        'product_capacity_btu' => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : 'Chưa xác định',
                        'product_url'          => $quote->product_url ?: '',
                        'project_type'         => 'Chưa cung cấp',
                        'btu'                  => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : 'Chưa xác định',
                        'message'              => $quote->message ?: '',
                    ],
                    relatedType:   'QuoteRequest',
                    relatedId:     $quote->id
                );
            } catch (\Throwable $e) {
                Log::error('QuickQuote customer mail failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'quote_id' => $quote->id]);
    }

    /**
     * POST /bao-gia
     */
    public function store(StoreQuoteRequest $request)
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

        $validated = $request->validated();

        // ── Calculate BTU using BtuCalculatorService (single source of truth) ──
        $calculatorContext = $request->calculatorContext();
        $calculatedBtu = $calculatorContext['recommended_btu'] ?? $validated['preferred_btu'] ?? null;
        $suggestedRange = null;
        $recommendedProductIds = [];
        if ($calculatorContext && $calculatedBtu) {
            $suggestedRange = number_format((int) $calculatedBtu).' BTU';
            $recommendedProductIds = $this->calculator
                ->matchProducts((int) $calculatedBtu, '')
                ->pluck('id')
                ->take(6)
                ->toArray();
        } elseif (! empty($validated['area_m2']) && ! empty($validated['project_type'])) {
            try {
                $areaMq   = (float) $validated['area_m2'];
                $height   = (float) ($validated['ceiling_height'] ?? 3.0);
                $people   = (int) ($validated['number_of_people'] ?? 0);
                $sunlight = ($validated['sun_exposure'] ?? '') === 'nang_nhieu';
                $heatEquip = false; // quote form doesn't have this field

                // Map project_type → calculator space_type
                $spaceMap = [
                    'nha_o' => 'nha_o', 'can_ho' => 'nha_o', 'van_phong' => 'van_phong',
                    'cua_hang' => 'cua_hang', 'showroom' => 'showroom', 'nha_hang' => 'nha_hang',
                    'hoi_truong' => 'hoi_truong', 'nha_xuong' => 'nha_xuong',
                    'truong_hoc' => 'phong_hoc', 'khach_san' => 'khach_san', 'khac' => 'van_phong',
                ];
                if (! isset($spaceMap[$validated['project_type']])) {
                    throw new \DomainException('Không đủ loại không gian để tính BTU tự động.');
                }
                $spaceType = $spaceMap[$validated['project_type']];

                $result = $this->calculator->calculate($areaMq, $height, $spaceType, $people, $sunlight, $heatEquip);
                $calculatedBtu = $result['recommended_btu'];
                $suggestedRange = $result['area_range'] ? number_format($result['recommended_btu']) . ' BTU' : null;

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
            'btu'      => app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($productModel, 'marketing_capacity_btu')['value'] ?? null,
            'price'    => $productModel->sale_price ?? $productModel->regular_price,
            'url'      => route('product.show', $productModel->slug),
            'snapshot_at' => now()->toISOString(),
        ] : null;

        // ── Intent score ──────────────────────────────────────────────
        $intentScore = QuoteRequest::calculateIntentScore(array_merge($validated, [
            'product_id' => $productModel?->id,
        ]));
        $leadType = $productModel ? 'product' : ($validated['lead_type'] ?? 'general');

        $projectLabel = QuoteRequest::projectTypeLabels()[$validated['project_type'] ?? ''] ?? 'Chưa rõ';
        $budgetLabel = QuoteRequest::budgetRangeLabels()[$validated['budget_range'] ?? ''] ?? 'Chưa rõ';

        // QuoteRequest and its CRM Lead are one idempotent persistence unit.
        $submission = $this->submissions->create([
            'submission_token'          => $validated['submission_token'],
            'entry_context'             => $validated['entry_context'],
            'provided_fields'            => array_values(array_keys($validated)),
            'lead_type'                => $leadType,
            'intent_score'             => $intentScore,
            // Product metadata
            'product_id'               => $productModel?->id,
            'product_name'             => $productModel?->name,
            'product_sku'              => $productModel?->sku,
            'product_model'            => $productModel?->model_code,
            'product_brand'            => $productModel?->brand?->name,
            'product_category'         => $productModel?->category?->name,
            'product_capacity_btu'     => $productModel ? (app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($productModel, 'marketing_capacity_btu')['value'] ?? null) : null,
            'product_url'              => $productModel ? route('product.show', $productModel->slug) : null,
            'selected_product_snapshot'=> $productSnapshot,
            'calculator_context'       => $calculatorContext,
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
            'gclid'                    => $validated['gclid'] ?? null,
            'gbraid'                   => $validated['gbraid'] ?? null,
            'wbraid'                   => $validated['wbraid'] ?? null,
            'recommended_product_ids'  => $recommendedProductIds ?: null,
            'status'                   => 'new',
            'ip_address'               => $request->ip(),
            'user_agent'               => $request->userAgent(),
        ], [
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'source_page' => $validated['source_page'] ?? url()->current(),
            'status' => 'new',
            'ip_address' => $request->ip(),
        ], [
            'usage_type' => $validated['project_type'] ?? null,
            'area' => $validated['area_m2'] ?? null,
            'message' => "Báo giá | {$projectLabel} | {$budgetLabel}".
                ($calculatedBtu ? ' | BTU: '.number_format((int) $calculatedBtu) : ''),
            'need_type' => 'quote_request',
        ], $productModel);

        /** @var QuoteRequest $quote */
        $quote = $submission['quote'];

        if (! $submission['created']) {
            return redirect()->route('quote.index')->with('quote_thanks', [
                'quote_id' => $quote->id,
                'full_name' => $quote->full_name,
                'phone' => $quote->phone,
                'recommended_btu' => $quote->calculated_btu,
                'lead_type' => $quote->lead_type,
                'product_id' => $quote->product_id,
                'product_name' => $quote->product_name,
                'intent_score' => $quote->intent_score,
                'suggested_products' => [],
            ]);
        }

        if ($validated['entry_context'] === 'calculator') {
            $request->session()->forget('quote_calculator_context');
        }

        $this->recordGoogleAdsOfflineConversion($quote, $request, 'submit_quote');

        // ── Build shared label map ─────────────────────────────────────
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

        // ── Build mail vars with fallbacks (never leave blanks) ─────
        $brandsArr = is_array($quote->preferred_brands) ? $quote->preferred_brands : [];
        $mailVars = [
            'quote_id'                 => $quote->id,
            'lead_type'                => $quote->lead_type === 'product' ? 'Product Quote' : 'General Quote',
            'intent_score'             => $quote->intent_score ?? 0,
            // Customer
            'customer_name'            => $quote->full_name,
            'customer_phone'           => $quote->phone,
            'customer_email'           => $quote->email ?: 'Chưa cung cấp',
            'province_city'            => $quote->province_city ?: 'Chưa cung cấp',
            'address'                  => $quote->address ?: 'Chưa cung cấp',
            'preferred_contact_method' => !empty($quote->preferred_contact_method) ? (QuoteRequest::contactMethodLabels()[$quote->preferred_contact_method] ?? $quote->preferred_contact_method) : 'Chưa chọn',
            'preferred_contact_time'   => !empty($quote->preferred_contact_time) ? (QuoteRequest::contactTimeLabels()[$quote->preferred_contact_time] ?? $quote->preferred_contact_time) : 'Chưa chọn',
            // Product
            'product_name'             => $quote->product_name ?? $productModel?->name ?? 'Chưa chọn sản phẩm',
            'product_sku'              => $quote->product_sku ?? $productModel?->sku ?? '—',
            'product_model'            => $quote->product_model ?? $productModel?->model_code ?? '—',
            'product_brand'            => $quote->product_brand ?? $productModel?->brand?->name ?? '—',
            'product_category'         => $quote->product_category ?? $productModel?->category?->name ?? '—',
            'product_capacity_btu'     => $quote->product_capacity_btu ? number_format($quote->product_capacity_btu) . ' BTU' : 'Chưa xác định',
            'product_url'              => $quote->product_url ?? '',
            // Space
            'project_type'             => !empty($quote->project_type) ? (QuoteRequest::projectTypeLabels()[$quote->project_type] ?? $quote->project_type) : 'Chưa cung cấp',
            'usage_description'        => $quote->usage_description ?: '',
            'number_of_rooms'          => ($quote->number_of_rooms && $quote->number_of_rooms > 1) ? $quote->number_of_rooms : '',
            'area_m2'                  => $quote->area_m2 ? $quote->area_m2 . ' m²' : 'Chưa cung cấp',
            'ceiling_height_m'         => $quote->ceiling_height ? $quote->ceiling_height . ' m' : '',
            'estimated_volume_m3'      => $quote->estimated_volume_m3 ? $quote->estimated_volume_m3 . ' m³' : '',
            'number_of_people'         => $quote->number_of_people ?: '',
            'sun_exposure'             => !empty($quote->sun_exposure) ? (QuoteRequest::sunExposureLabels()[$quote->sun_exposure] ?? $quote->sun_exposure) : '',
            'glass_area'               => !empty($quote->glass_area) ? (QuoteRequest::glassAreaLabels()[$quote->glass_area] ?? $quote->glass_area) : '',
            'insulation_quality'       => !empty($quote->insulation_quality) ? (QuoteRequest::insulationLabels()[$quote->insulation_quality] ?? $quote->insulation_quality) : '',
            'current_aircon_status'    => !empty($quote->current_aircon_status) ? (QuoteRequest::airconStatusLabels()[$quote->current_aircon_status] ?? $quote->current_aircon_status) : '',
            // Technical
            'desired_capacity_btu'     => $quote->preferred_btu ? number_format($quote->preferred_btu) . ' BTU' : '',
            'calculated_btu'           => $quote->calculated_btu ? number_format($quote->calculated_btu) . ' BTU' : '',
            'btu'                      => $quote->calculated_btu ? number_format($quote->calculated_btu) . ' BTU' : 'Chưa xác định',
            'suggested_capacity_range' => $quote->suggested_capacity_range ?: '',
            'preferred_brands'         => implode(', ', $brandsArr) ?: '',
            'require_inverter'         => $quote->need_inverter ? 'Có' : '',
            'require_3_phase'          => $quote->need_three_phase ? 'Có' : '',
            'power_supply'             => $quote->power_supply ?: '',
            'installation_type'        => !empty($quote->installation_type) ? (QuoteRequest::installationTypeLabels()[$quote->installation_type] ?? $quote->installation_type) : '',
            'outdoor_unit_location'    => !empty($quote->outdoor_unit_location) ? (QuoteRequest::outdoorLocationLabels()[$quote->outdoor_unit_location] ?? $quote->outdoor_unit_location) : '',
            'pipe_distance_m'          => $quote->pipe_distance_m ? $quote->pipe_distance_m . ' m' : '',
            'drainage_available'       => $quote->drainage_available ?: '',
            // Budget
            'budget_range'             => !empty($quote->budget_range) ? (QuoteRequest::budgetRangeLabels()[$quote->budget_range] ?? $quote->budget_range) : 'Chưa cung cấp',
            'timeline'                 => !empty($quote->installation_time) ? (QuoteRequest::installationTimeLabels()[$quote->installation_time] ?? $quote->installation_time) : '',
            'need_installation_service'=> !empty($quote->need_installation_service) ? (QuoteRequest::needInstallLabels()[$quote->need_installation_service] ?? $quote->need_installation_service) : '',
            'need_invoice'             => $quote->need_invoice ? 'Có' : '',
            'need_site_survey'         => $quote->need_site_survey ? 'Có' : '',
            // Tracking
            'source'                   => $quote->source_page ?: url()->current(),
            'utm_source'               => $quote->utm_source ?: '',
            'utm_campaign'             => $quote->utm_campaign ?: '',
            // Misc
            'customer_note'            => $quote->message ?: 'Không có ghi chú',
            'message'                  => $quote->message ?: 'Không có ghi chú',
        ];

        // Debug log — full mail payload + null detection
        $criticalFields = ['quote_id', 'customer_name', 'customer_phone'];
        $missingCritical = array_filter($criticalFields, fn ($k) => empty($mailVars[$k]));
        if ($missingCritical) {
            Log::warning('QuoteRequest mail: missing critical fields', [
                'quote_id' => $quote->id,
                'missing'  => $missingCritical,
            ]);
        }
        Log::debug('QuoteRequest mail payload prepared', [
            'quote_id' => $quote->id,
            'var_keys' => array_keys($mailVars),
        ]);

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
            Log::error('QuoteRequest admin mail failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
        }

        // ── Customer mail ───────────────────────────────────────────
        if (! empty($quote->email)) {
            try {
                $this->mailService->sendCustomerEvent(
                    event:         'quote_customer',
                    customerEmail: $quote->email,
                    vars:          $mailVars,
                    relatedType:   'QuoteRequest',
                    relatedId:     $quote->id
                );
            } catch (\Throwable $e) {
                Log::error('QuoteRequest customer mail failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
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
                    'btu'          => app(\App\Services\Product\ProductTechnicalFactResolver::class)->getDisplay($p, 'marketing_capacity_btu')['value'] ?? null,
                    'sale_price'   => $p->sale_price,
                    'regular_price'=> $p->regular_price,
                    'main_image'   => $p->main_image,
                ])->values()->toArray(),
            ]);
    }

    protected function recordGoogleAdsOfflineConversion(QuoteRequest $quote, Request $request, string $eventName): void
    {
        try {
            app(GoogleAdsOfflineConversionService::class)->recordQuoteRequest($quote, $request, $eventName);
        } catch (\Throwable $exception) {
            Log::warning('Google Ads offline conversion record failed', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
