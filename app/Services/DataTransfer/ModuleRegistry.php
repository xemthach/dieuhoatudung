<?php

namespace App\Services\DataTransfer;

/**
 * Registry of all importable/exportable modules and their field group configurations.
 */
class ModuleRegistry
{
    /**
     * Get all available modules.
     */
    public static function modules(): array
    {
        return [
            'product'         => 'Sản phẩm',
            'lead'            => 'Lead',
            'quote_request'   => 'Yêu cầu báo giá',
            'btu_calculation' => 'Lịch sử tính BTU',
        ];
    }

    /**
     * Get field groups for a module.
     */
    public static function fieldGroups(string $module): array
    {
        return match ($module) {
            'product'         => self::productFieldGroups(),
            'lead'            => self::leadFieldGroups(),
            'quote_request'   => self::quoteRequestFieldGroups(),
            'btu_calculation' => self::btuCalculationFieldGroups(),
            default           => [],
        };
    }

    /**
     * Get all fields for a module (flat list).
     */
    public static function allFields(string $module): array
    {
        $groups = self::fieldGroups($module);
        $fields = [];
        foreach ($groups as $group) {
            $fields = array_merge($fields, $group['fields']);
        }
        return array_unique($fields);
    }

    /**
     * Get fields for specific groups.
     */
    public static function fieldsForGroups(string $module, array $groupKeys): array
    {
        $groups = self::fieldGroups($module);
        $fields = [];
        foreach ($groupKeys as $key) {
            if (isset($groups[$key])) {
                $fields = array_merge($fields, $groups[$key]['fields']);
            }
        }
        return array_unique($fields);
    }

    /**
     * Get matching keys for import.
     */
    public static function matchingKeys(string $module): array
    {
        return match ($module) {
            'product'         => ['id' => 'ID', 'sku' => 'SKU', 'slug' => 'Slug'],
            'lead'            => ['id' => 'ID', 'phone' => 'Số điện thoại'],
            'quote_request'   => ['id' => 'ID', 'phone' => 'SĐT + Nguồn'],
            'btu_calculation' => ['id' => 'ID'],
            default           => ['id' => 'ID'],
        };
    }

    /**
     * Get required fields for import validation.
     */
    public static function requiredFields(string $module): array
    {
        return match ($module) {
            'product'         => ['name'],
            'lead'            => ['full_name', 'phone'],
            'quote_request'   => ['full_name', 'phone'],
            'btu_calculation' => ['area_m2', 'space_type', 'recommended_btu'],
            default           => [],
        };
    }

    /**
     * Get the model class for a module.
     */
    public static function modelClass(string $module): string
    {
        return match ($module) {
            'product'         => \App\Models\Product::class,
            'lead'            => \App\Models\Lead::class,
            'quote_request'   => \App\Models\QuoteRequest::class,
            'btu_calculation' => \App\Models\BtuCalculation::class,
            default           => throw new \InvalidArgumentException("Unknown module: {$module}"),
        };
    }

    // ─── Product Field Groups ────────────────────────────────────────

    private static function productFieldGroups(): array
    {
        return [
            'basic' => [
                'label'  => 'Thông tin cơ bản',
                'fields' => [
                    'id', 'name', 'slug', 'sku', 'model_code', 'brand_id', 'product_category_id',
                    'series', 'short_description', 'long_description', 'is_active', 'is_featured',
                    'is_bestseller', 'is_new', 'sort_order',
                ],
            ],
            'pricing' => [
                'label'  => 'Giá & bán hàng',
                'fields' => [
                    'regular_price', 'sale_price', 'discount_percent',
                    'promotion_start_at', 'promotion_end_at', 'stock_status',
                ],
            ],
            'specs' => [
                'label'  => 'Thông số kỹ thuật',
                'fields' => [
                    'btu', 'inverter', 'cooling_type', 'voltage', 'refrigerant_gas',
                    'power_consumption', 'airflow', 'noise_level', 'indoor_dimensions',
                    'outdoor_dimensions', 'weight', 'recommended_area', 'warranty_info',
                    'installation_note', 'specs_json',
                ],
            ],
            'seo' => [
                'label'  => 'SEO',
                'fields' => [
                    'seo_title', 'seo_description', 'canonical_url', 'robots',
                    'og_title', 'og_description', 'og_image', 'schema_enabled',
                ],
            ],
            'media' => [
                'label'  => 'Media',
                'fields' => [
                    'main_image', 'gallery_json', 'video_url', 'documents_json',
                ],
            ],
            'merchant' => [
                'label'  => 'Google Merchant',
                'fields' => [
                    'condition', 'gtin', 'identifier_exists', 'google_product_category',
                    'product_type', 'shipping_weight', 'shipping_label',
                    'custom_label_0', 'custom_label_1', 'custom_label_2',
                    'custom_label_3', 'custom_label_4',
                ],
            ],
        ];
    }

    // ─── Lead Field Groups ───────────────────────────────────────────

    private static function leadFieldGroups(): array
    {
        return [
            'contact' => [
                'label'  => 'Thông tin liên hệ',
                'fields' => [
                    'id', 'full_name', 'phone', 'email', 'region',
                ],
            ],
            'source' => [
                'label'  => 'Nguồn / Tracking',
                'fields' => [
                    'source_page', 'lead_type', 'intent_score',
                    'quote_request_id',
                ],
            ],
            'product_context' => [
                'label'  => 'Sản phẩm quan tâm',
                'fields' => [
                    'interested_product_id', 'product_name', 'product_sku',
                    'product_url', 'brand_name', 'category_name', 'capacity_btu',
                ],
            ],
            'status' => [
                'label'  => 'Trạng thái & Ghi chú',
                'fields' => [
                    'status', 'admin_note', 'need_type', 'area', 'budget',
                    'usage_type', 'message', 'created_at',
                ],
            ],
        ];
    }

    // ─── Quote Request Field Groups ──────────────────────────────────

    private static function quoteRequestFieldGroups(): array
    {
        return [
            'customer' => [
                'label'  => 'Khách hàng',
                'fields' => [
                    'id', 'full_name', 'phone', 'email', 'address',
                    'province_city', 'district', 'message',
                ],
            ],
            'product_context' => [
                'label'  => 'Sản phẩm',
                'fields' => [
                    'product_id', 'product_name', 'product_sku', 'product_model',
                    'product_brand', 'product_category', 'product_capacity_btu', 'product_url',
                ],
            ],
            'hvac' => [
                'label'  => 'Yêu cầu HVAC',
                'fields' => [
                    'project_type', 'area_m2', 'ceiling_height', 'estimated_volume_m3',
                    'number_of_rooms', 'number_of_people', 'sun_exposure', 'insulation_quality',
                    'glass_area', 'open_space', 'current_aircon_status',
                    'preferred_btu', 'calculated_btu', 'suggested_capacity_range',
                    'need_inverter', 'need_three_phase', 'power_supply',
                    'installation_type', 'pipe_distance_m', 'outdoor_unit_location',
                    'budget_range', 'installation_time',
                ],
            ],
            'tracking' => [
                'label'  => 'Nguồn / Tracking',
                'fields' => [
                    'lead_type', 'intent_score', 'source_page',
                    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                    'landing_page', 'referrer', 'status', 'admin_note', 'created_at',
                ],
            ],
        ];
    }

    // ─── BTU Calculation Field Groups ────────────────────────────────

    private static function btuCalculationFieldGroups(): array
    {
        return [
            'customer' => [
                'label'  => 'Khách hàng',
                'fields' => [
                    'id', 'full_name', 'phone', 'email', 'note',
                ],
            ],
            'input' => [
                'label'  => 'Dữ liệu đầu vào',
                'fields' => [
                    'area_m2', 'ceiling_height', 'space_type', 'people_count',
                    'direct_sunlight', 'heat_equipment', 'priority',
                ],
            ],
            'result' => [
                'label'  => 'Kết quả tính toán',
                'fields' => [
                    'recommended_btu', 'calculated_btu', 'cooling_w_per_m2',
                    'matched_product_ids',
                ],
            ],
            'source' => [
                'label'  => 'Nguồn',
                'fields' => [
                    'source_page', 'ip_address', 'created_at',
                ],
            ],
        ];
    }
}
