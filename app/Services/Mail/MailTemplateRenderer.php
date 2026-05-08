<?php

namespace App\Services\Mail;

use App\Models\MailTemplate;
use Illuminate\Support\Facades\Log;

/**
 * MailTemplateRenderer
 *
 * Central service for:
 *  - Rendering templates with variable substitution
 *  - Providing the canonical variable registry grouped by category
 *  - Mapping each template key to its relevant variables
 *  - Providing sample payload for preview/test
 */
class MailTemplateRenderer
{
    // ──────────────────────────────────────────────────────────────────────
    // Variable Registry (canonical source of truth)
    // ──────────────────────────────────────────────────────────────────────

    /** @var array<string, array<string, array{name:string, description:string, example:string}>> */
    private static array $variableRegistry = [

        'site' => [
            'site_name'    => ['name' => 'site_name',    'description' => 'Tên website',         'example' => 'Điều Hòa Tủ Đứng'],
            'company_name' => ['name' => 'company_name', 'description' => 'Tên công ty',          'example' => 'Công ty TNHH Điện Lạnh ABC'],
            'hotline'      => ['name' => 'hotline',      'description' => 'Hotline liên hệ',      'example' => '09.4321.6060'],
            'email'        => ['name' => 'email',        'description' => 'Email liên hệ',        'example' => 'contact@dieuhoa.vn'],
            'website_url'  => ['name' => 'website_url',  'description' => 'URL website',          'example' => 'https://dieuhoa.vn'],
            'admin_url'    => ['name' => 'admin_url',    'description' => 'Link quản trị admin',  'example' => 'https://dieuhoa.vn/admin'],
        ],

        'customer' => [
            'customer_name'  => ['name' => 'customer_name',  'description' => 'Tên khách hàng',  'example' => 'Nguyễn Văn A'],
            'customer_phone' => ['name' => 'customer_phone', 'description' => 'SĐT khách hàng',  'example' => '0901234567'],
            'customer_email' => ['name' => 'customer_email', 'description' => 'Email khách hàng','example' => 'khachhang@gmail.com'],
            'message'        => ['name' => 'message',        'description' => 'Nội dung gửi',    'example' => 'Tôi cần tư vấn điều hòa cho phòng 30m2'],
        ],

        'product' => [
            'product_name'  => ['name' => 'product_name',  'description' => 'Tên sản phẩm',  'example' => 'Điều hòa GREE 18000BTU'],
            'product_url'   => ['name' => 'product_url',   'description' => 'Link sản phẩm', 'example' => 'https://dieuhoa.vn/san-pham/gree-18000'],
            'product_sku'   => ['name' => 'product_sku',   'description' => 'Mã SKU',         'example' => 'GWH18AGB-K6DNA1B'],
            'product_price' => ['name' => 'product_price', 'description' => 'Giá sản phẩm',  'example' => 'Liên hệ'],
        ],

        'lead' => [
            'quote_id'                 => ['name' => 'quote_id',                 'description' => 'Ma yeu cau bao gia',          'example' => '42'],
            'lead_type'                => ['name' => 'lead_type',                'description' => 'Loai lead',                   'example' => 'product'],
            'intent_score'             => ['name' => 'intent_score',             'description' => 'Diem intent 0-100',           'example' => '90'],
            'source'                   => ['name' => 'source',                   'description' => 'Trang gui form',              'example' => 'San pham chi tiet'],
            'need_type'                => ['name' => 'need_type',                'description' => 'Nhu cau khach hang',          'example' => 'quote_request'],
            'area'                     => ['name' => 'area',                     'description' => 'Dien tich phong',             'example' => '30 m2'],
            'btu'                      => ['name' => 'btu',                      'description' => 'Cong suat BTU',               'example' => '18,000 BTU'],
            'budget'                   => ['name' => 'budget',                   'description' => 'Ngan sach',                   'example' => '20-40 trieu'],
            // Customer detail
            'province_city'            => ['name' => 'province_city',            'description' => 'Tinh/Thanh pho',              'example' => 'TP.HCM'],
            'address'                  => ['name' => 'address',                  'description' => 'Dia chi',                     'example' => '123 Nguyen Trai'],
            'preferred_contact_method' => ['name' => 'preferred_contact_method', 'description' => 'Cach lien he ua thich',       'example' => 'Zalo'],
            'preferred_contact_time'   => ['name' => 'preferred_contact_time',   'description' => 'Gio lien he',                 'example' => 'Buoi toi 18-21h'],
            // Product
            'product_sku'              => ['name' => 'product_sku',              'description' => 'SKU san pham',                'example' => 'GREE-36KG'],
            'product_model'            => ['name' => 'product_model',            'description' => 'Model san pham',              'example' => 'GWH36KG'],
            'product_brand'            => ['name' => 'product_brand',            'description' => 'Thuong hieu',                 'example' => 'GREE'],
            'product_category'         => ['name' => 'product_category',         'description' => 'Danh muc san pham',           'example' => 'Dieu hoa tu dung'],
            'product_capacity_btu'     => ['name' => 'product_capacity_btu',     'description' => 'Cong suat san pham (BTU)',     'example' => '36,000 BTU'],
            'product_url'              => ['name' => 'product_url',              'description' => 'Link san pham',               'example' => 'https://...'],
            // Space
            'project_type'             => ['name' => 'project_type',             'description' => 'Loai cong trinh',             'example' => 'Van phong'],
            'usage_description'        => ['name' => 'usage_description',        'description' => 'Mo ta khong gian',            'example' => 'Phong khach 2 tang'],
            'number_of_rooms'          => ['name' => 'number_of_rooms',          'description' => 'So phong/khu vuc',             'example' => '3'],
            'area_m2'                  => ['name' => 'area_m2',                  'description' => 'Dien tich m2',                'example' => '80 m2'],
            'ceiling_height_m'         => ['name' => 'ceiling_height_m',         'description' => 'Chieu cao tran',              'example' => '3.5 m'],
            'estimated_volume_m3'      => ['name' => 'estimated_volume_m3',      'description' => 'The tich phong',              'example' => '280 m3'],
            'number_of_people'         => ['name' => 'number_of_people',         'description' => 'So nguoi thuong xuyen',       'example' => '15'],
            'sun_exposure'             => ['name' => 'sun_exposure',             'description' => 'Muc do tiep xuc nang',        'example' => 'Nang nhieu'],
            'glass_area'               => ['name' => 'glass_area',               'description' => 'Dien tich kinh',              'example' => 'Nhieu kinh'],
            'insulation_quality'       => ['name' => 'insulation_quality',       'description' => 'Chat luong cach nhiet',       'example' => 'Trung binh'],
            'current_aircon_status'    => ['name' => 'current_aircon_status',    'description' => 'Tinh trang dieu hoa hien tai', 'example' => 'Chua co'],
            // Technical
            'desired_capacity_btu'     => ['name' => 'desired_capacity_btu',     'description' => 'BTU khach yeu cau',           'example' => '36,000 BTU'],
            'calculated_btu'           => ['name' => 'calculated_btu',           'description' => 'BTU tinh toan tu form',       'example' => '42,000 BTU'],
            'suggested_capacity_range' => ['name' => 'suggested_capacity_range', 'description' => 'Khoang cong suat de xuat',    'example' => '36000-42000 BTU'],
            'preferred_brands'         => ['name' => 'preferred_brands',         'description' => 'Thuong hieu ua thich',        'example' => 'GREE, Daikin'],
            'require_inverter'         => ['name' => 'require_inverter',         'description' => 'Yeu cau Inverter',            'example' => 'Co'],
            'require_3_phase'          => ['name' => 'require_3_phase',          'description' => 'Yeu cau 3 pha',              'example' => 'Khong'],
            'power_supply'             => ['name' => 'power_supply',             'description' => 'Nguon dien',                  'example' => '3 pha 380V'],
            'installation_type'        => ['name' => 'installation_type',        'description' => 'Loai lap dat',                'example' => 'Lap moi'],
            'outdoor_unit_location'    => ['name' => 'outdoor_unit_location',    'description' => 'Vi tri dan nong',             'example' => 'San thuong'],
            'pipe_distance_m'          => ['name' => 'pipe_distance_m',          'description' => 'Khoang cach ong gio',         'example' => '8 m'],
            'drainage_available'       => ['name' => 'drainage_available',       'description' => 'Thoat nuoc condensate',       'example' => 'Co san'],
            // Budget
            'budget_range'             => ['name' => 'budget_range',             'description' => 'Ngan sach du kien',           'example' => '40-70 trieu'],
            'timeline'                 => ['name' => 'timeline',                 'description' => 'Thoi gian lap dat',           'example' => 'Cang som cang tot'],
            'need_installation_service'=> ['name' => 'need_installation_service','description' => 'Dich vu yeu cau',             'example' => 'Bao gia tron goi'],
            'need_invoice'             => ['name' => 'need_invoice',             'description' => 'Can hoa don VAT',             'example' => 'Co'],
            'need_site_survey'         => ['name' => 'need_site_survey',         'description' => 'Can khao sat',               'example' => 'Co'],
            // Tracking
            'utm_source'               => ['name' => 'utm_source',               'description' => 'UTM Source',                  'example' => 'google'],
            'utm_campaign'             => ['name' => 'utm_campaign',             'description' => 'UTM Campaign',               'example' => 'dieu_hoa_2025'],
            // Misc
            'customer_note'            => ['name' => 'customer_note',            'description' => 'Ghi chu cua khach',           'example' => 'Can gap'],
            'message'                  => ['name' => 'message',                  'description' => 'Noi dung ghi chu',            'example' => 'Yeu cau them'],
        ],

        'review' => [
            'rating'         => ['name' => 'rating',         'description' => 'Số sao đánh giá',      'example' => '5'],
            'review_content' => ['name' => 'review_content', 'description' => 'Nội dung đánh giá',    'example' => 'Sản phẩm rất tốt, giao hàng nhanh'],
            'review_url'     => ['name' => 'review_url',     'description' => 'Link review trong admin','example' => 'https://dieuhoa.vn/admin/reviews/1'],
        ],

        'qa' => [
            'question'     => ['name' => 'question',     'description' => 'Câu hỏi',                     'example' => 'Có giao hàng Trà Vinh không?'],
            'answer'       => ['name' => 'answer',       'description' => 'Câu trả lời',                  'example' => 'Có, chúng tôi giao toàn quốc miễn phí.'],
            'question_url' => ['name' => 'question_url', 'description' => 'Link quản trị câu hỏi/đáp',   'example' => 'https://dieuhoa.vn/admin/questions/1'],
        ],
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Template key → variable name list mapping
    // ──────────────────────────────────────────────────────────────────────

    /** @var array<string, array<string>> */
    private static array $templateVariableMap = [
        'lead_admin_notification' => [
            'customer_name', 'customer_phone', 'customer_email',
            'source', 'need_type', 'area', 'message',
            'admin_url', 'site_name', 'hotline', 'website_url',
        ],
        'lead_customer_confirmation' => [
            'customer_name', 'customer_phone', 'site_name', 'hotline', 'website_url',
        ],
        'quote_admin_notification' => [
            'quote_id', 'lead_type', 'intent_score',
            'customer_name', 'customer_phone', 'customer_email',
            'province_city', 'address', 'preferred_contact_method', 'preferred_contact_time',
            'product_name', 'product_sku', 'product_model', 'product_brand', 'product_category', 'product_capacity_btu', 'product_url',
            'project_type', 'usage_description', 'number_of_rooms',
            'area_m2', 'ceiling_height_m', 'estimated_volume_m3', 'number_of_people',
            'sun_exposure', 'glass_area', 'insulation_quality', 'current_aircon_status',
            'desired_capacity_btu', 'calculated_btu', 'suggested_capacity_range',
            'preferred_brands', 'require_inverter', 'require_3_phase', 'power_supply',
            'installation_type', 'outdoor_unit_location', 'pipe_distance_m', 'drainage_available',
            'budget_range', 'timeline', 'need_installation_service', 'need_invoice', 'need_site_survey',
            'source', 'utm_source', 'utm_campaign', 'customer_note', 'message', 'btu',
            'admin_url', 'site_name', 'hotline', 'website_url',
        ],
        'quote_customer_confirmation' => [
            'quote_id', 'customer_name', 'customer_phone', 'customer_email',
            'product_name', 'product_sku', 'product_capacity_btu', 'product_url',
            'project_type', 'area_m2', 'calculated_btu', 'suggested_capacity_range',
            'budget_range', 'timeline', 'need_installation_service',
            'customer_note', 'message',
            'hotline', 'website_url', 'site_name',
        ],
        'review_admin_notification' => [
            'customer_name', 'customer_phone', 'rating', 'content', 'status',
            'product_name', 'admin_url', 'site_name', 'hotline', 'website_url',
        ],
        'review_approved_customer' => [
            'customer_name', 'product_name',
            'site_name', 'hotline', 'website_url',
        ],
        'question_admin_notification' => [
            'customer_name', 'customer_phone', 'customer_email',
            'question', 'product_name',
            'admin_url', 'site_name', 'hotline', 'website_url',
        ],
        'question_answered_customer' => [
            'customer_name', 'question', 'answer',
            'product_name', 'site_name', 'hotline', 'website_url',
        ],
        'system_alert' => [
            'alert_type', 'message', 'occurred_at',
            'site_name', 'hotline', 'website_url',
        ],
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Render methods
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Render the subject of a template.
     */
    public function renderSubject(MailTemplate $template, array $payload): string
    {
        return $this->interpolate($template->subject ?? '', $payload, $template->key);
    }

    /**
     * Render the HTML body of a template.
     */
    public function renderHtml(MailTemplate $template, array $payload): string
    {
        // Visual editor mode: wrap content_html in base email layout
        if ($template->use_visual_editor && !empty($template->content_html)) {
            $content = $this->interpolate($template->content_html, $payload, $template->key);

            return view('emails.layouts.base', array_merge($payload, [
                'content'  => $content,
                'subject'  => $this->renderSubject($template, $payload),
            ]))->render();
        }

        // Raw HTML mode: use body_html directly
        return $this->interpolate($template->body_html ?? '', $payload, $template->key);
    }

    /**
     * Render the plain-text body of a template.
     */
    public function renderText(MailTemplate $template, array $payload): ?string
    {
        if (empty($template->body_text)) {
            return null;
        }
        return $this->interpolate($template->body_text, $payload, $template->key);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Variable registry helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get ALL available variables grouped by category.
     *
     * @return array<string, array{label:string, variables:list<array{name:string,description:string,example:string}>}>
     */
    public function getAllVariableGroups(): array
    {
        $labels = [
            'site'     => 'Biến chung (site)',
            'customer' => 'Biến khách hàng',
            'product'  => 'Biến sản phẩm',
            'lead'     => 'Biến báo giá / lead',
            'review'   => 'Biến đánh giá',
            'qa'       => 'Biến hỏi đáp',
        ];

        $result = [];
        foreach (self::$variableRegistry as $group => $vars) {
            $result[$group] = [
                'label'     => $labels[$group] ?? ucfirst($group),
                'variables' => array_values($vars),
            ];
        }

        return $result;
    }

    /**
     * Get available variables for a specific template key.
     * Returns full variable definitions (name, description, example).
     *
     * @return list<array{name:string, description:string, example:string}>
     */
    public function getAvailableVariables(string $templateKey): array
    {
        $names = self::$templateVariableMap[$templateKey] ?? [];

        // Flatten registry to name => definition map
        $flat = [];
        foreach (self::$variableRegistry as $vars) {
            foreach ($vars as $name => $def) {
                $flat[$name] = $def;
            }
        }

        $result = [];
        foreach ($names as $name) {
            if (isset($flat[$name])) {
                $result[] = $flat[$name];
            } else {
                // Variable referenced in map but not in registry — include with minimal info
                $result[] = ['name' => $name, 'description' => $name, 'example' => ''];
            }
        }

        return $result;
    }

    /**
     * Get sample payload for a template key.
     * Used for preview rendering and test send.
     *
     * @return array<string, string>
     */
    public function getSamplePayload(string $templateKey): array
    {
        // Full sample data pool
        $allSamples = [
            'site_name'      => 'Điều Hòa Tủ Đứng',
            'company_name'   => 'Công ty TNHH Điện Lạnh ABC',
            'hotline'        => '09.4321.6060',
            'email'          => 'contact@dieuhoa.vn',
            'website_url'    => config('app.url', 'https://dieuhoa.vn'),
            'admin_url'      => config('app.url', 'https://dieuhoa.vn') . '/admin',
            'customer_name'  => 'Nguyễn Văn A',
            'customer_phone' => '0901234567',
            'customer_email' => 'khachhang@gmail.com',
            'message'        => 'Tôi cần tư vấn điều hòa cho phòng ngủ 30m2, ngân sách khoảng 15 triệu.',
            'product_name'   => 'Điều hòa GREE GWH18AGB 18000BTU',
            'product_url'    => config('app.url', 'https://dieuhoa.vn') . '/san-pham/gree-18000',
            'product_sku'    => 'GWH18AGB-K6DNA1B',
            'product_price'  => 'Liên hệ báo giá',
            'source'         => 'Trang chi tiết sản phẩm',
            'need_type'      => 'Mua điều hòa',
            'area'           => '30m2',
            'btu'            => '18.000 BTU',
            'budget'         => '15–20 triệu',
            'rating'         => '5',
            'review_content' => 'Sản phẩm rất tốt, lắp đặt nhanh, nhân viên nhiệt tình. Rất hài lòng!',
            'review_url'     => config('app.url', 'https://dieuhoa.vn') . '/admin/product-reviews/1',
            'question'       => 'Điều hòa 18000 BTU phù hợp phòng bao nhiêu m2?',
            'answer'         => 'Điều hòa 18000 BTU phù hợp cho phòng từ 25–35m2 tùy điều kiện cách nhiệt.',
            'question_url'   => config('app.url', 'https://dieuhoa.vn') . '/admin/product-questions/1',
        ];

        // Filter to only variables relevant to this template key
        $names = self::$templateVariableMap[$templateKey] ?? array_keys($allSamples);

        $payload = [];
        foreach ($names as $name) {
            $payload[$name] = $allSamples[$name] ?? "(sample: {$name})";
        }

        // Always include common site vars
        foreach (['site_name', 'hotline', 'website_url', 'admin_url'] as $k) {
            $payload[$k] ??= $allSamples[$k];
        }

        return $payload;
    }

    /**
     * Get the template key → variable name mapping (for display/info only).
     *
     * @return array<string, array<string>>
     */
    public function getTemplateVariableMap(): array
    {
        return self::$templateVariableMap;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Replace {{variable}} and {{ variable }} placeholders.
     * Missing variables are logged as warnings but NOT crashed.
     */
    private function interpolate(string $content, array $payload, ?string $templateKey = null): string
    {
        // Track missing variables (warn only, do not crash)
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches);
        $usedVars = array_unique($matches[1] ?? []);

        foreach ($usedVars as $varName) {
            if (!array_key_exists($varName, $payload)) {
                Log::warning("MailTemplateRenderer: variable '{$varName}' missing in payload", [
                    'template_key' => $templateKey,
                ]);
            }
        }

        // Replace all {{var}} and {{ var }} patterns
        foreach ($payload as $key => $value) {
            $value   = (string) ($value ?? '');
            $content = str_replace(
                ['{{' . $key . '}}', '{{ ' . $key . ' }}', '{{ ' . $key . '}}', '{{' . $key . ' }}'],
                $value,
                $content
            );
        }

        // Remove remaining unresolved placeholders (replace with empty string)
        return preg_replace('/\{\{\s*\w+\s*\}\}/', '', $content);
    }
}
