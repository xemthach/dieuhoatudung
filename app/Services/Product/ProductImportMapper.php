<?php

namespace App\Services\Product;

/**
 * Maps imported product data to the correct database fields.
 *
 * PRIORITY ORDER:
 *   1. Standard DB column fields (always fill these first)
 *   2. Extra specs in specs_json (only when NO column exists)
 *
 * Standard fields → go to dedicated DB columns.
 * Everything else → goes to specs_json as extra specs.
 * Metadata keys   → excluded entirely from specs_json.
 */
class ProductImportMapper
{
    /**
     * Import key → DB column mapping.
     *
     * ANY key from import data that matches a left-hand key
     * will be written to the right-hand DB column.
     * It will NEVER appear in specs_json.
     */
    public const FIELD_MAP = [
        // ── Capacity ──
        'capacity_btu'          => 'btu',
        'btu'                   => 'btu',
        'capacity_kw'           => 'capacity_kw',
        'kw'                    => 'capacity_kw',
        'hp'                    => 'hp',
        'horsepower'            => 'hp',

        // ── Inverter ──
        'inverter'              => 'inverter',
        'is_inverter'           => 'inverter',

        // ── Cooling type ──
        'cooling_type'          => 'cooling_type',
        'cooling_heating_type'  => 'cooling_type',
        'cooling_heating'       => 'cooling_type',

        // ── Voltage / Phase ──
        'phase'                 => 'voltage',
        'voltage'               => 'voltage',
        'dien_ap'               => 'voltage',

        // ── Gas ──
        'refrigerant'           => 'refrigerant_gas',
        'refrigerant_gas'       => 'refrigerant_gas',
        'gas'                   => 'refrigerant_gas',
        'loai_gas'              => 'refrigerant_gas',

        // ── Power ──
        'power_consumption'     => 'power_consumption',
        'power_input_kw'        => 'power_consumption',
        'dien_nang_tieu_thu'    => 'power_consumption',

        // ── Airflow ──
        'airflow'               => 'airflow',
        'airflow_m3h'           => 'airflow',
        'luu_luong_gio'         => 'airflow',

        // ── Noise ──
        'noise_level'           => 'noise_level',
        'noise'                 => 'noise_level',
        'sound_level_db'        => 'noise_level',
        'do_on'                 => 'noise_level',

        // ── Dimensions ──
        'indoor_dimensions'     => 'indoor_dimensions',
        'dimensions_indoor'     => 'indoor_dimensions',
        'kich_thuoc_dan_lanh'   => 'indoor_dimensions',
        'outdoor_dimensions'    => 'outdoor_dimensions',
        'dimensions_outdoor'    => 'outdoor_dimensions',
        'kich_thuoc_dan_nong'   => 'outdoor_dimensions',

        // ── Weight ──
        'weight'                => 'weight',
        'weight_indoor'         => 'weight',
        'trong_luong'           => 'weight',

        // ── Area ──
        'recommended_area'      => 'recommended_area',
        'suitable_area_m2'      => 'recommended_area',
        'dien_tich_de_nghi'     => 'recommended_area',

        // ── Series ──
        'series'                => 'series',
    ];

    /**
     * Keys that must NEVER appear in specs_json.
     * These are product identity/metadata fields, not technical specs.
     */
    public const EXCLUDED_FROM_SPECS = [
        // Product identity
        'name', 'slug', 'brand', 'brand_id', 'product_category',
        'product_category_id', 'model_code', 'sku',
        // Content fields
        'short_description', 'description', 'long_description',
        'warranty_info', 'installation_note',
        // Status flags
        'is_active', 'is_featured', 'is_new', 'is_bestseller',
        // SEO
        'seo_title', 'meta_description', 'seo_description',
        // Import metadata
        'import_action', 'extraction_confidence',
        // Misc
        'type', 'stock_status', 'regular_price', 'sale_price',
    ];

    /**
     * Map raw import data to product attributes + extra specs.
     *
     * @param  array  $raw  Raw import row (from JSON/CSV)
     * @return array{attributes: array, extra_specs: array}
     */
    public function map(array $raw): array
    {
        $attributes = [];
        $extraSpecs = [];

        foreach ($raw as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // 1. Standard field → DB column (PRIORITY)
            if (isset(self::FIELD_MAP[$key])) {
                $dbCol = self::FIELD_MAP[$key];
                $attributes[$dbCol] = $this->castValue($dbCol, $value);
                continue;
            }

            // 2. Excluded metadata → skip entirely
            if (in_array($key, self::EXCLUDED_FROM_SPECS, true)) {
                continue;
            }

            // 3. Unknown key → extra specs in JSON (flat key-value, no duplicates)
            $extraSpecs[$key] = (string) $value;
        }

        return [
            'attributes'  => $attributes,
            'extra_specs' => $extraSpecs,
        ];
    }

    /**
     * Clean existing specs_json: move standard fields to DB columns,
     * remove metadata, deduplicate.
     *
     * @param  \App\Models\Product  $product
     * @return array{moved: array, cleaned_specs: array}
     */
    public function cleanSpecs($product): array
    {
        $specs = $product->specs_json;
        if (!is_array($specs)) {
            return ['moved' => [], 'cleaned_specs' => []];
        }

        $moved = [];
        $cleaned = [];

        // Flatten from Repeater format if needed
        $flat = $this->flattenSpecs($specs);

        foreach ($flat as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // If this key maps to a standard DB column → MOVE to column
            if (isset(self::FIELD_MAP[$key])) {
                $dbCol = self::FIELD_MAP[$key];
                $currentVal = $product->getRawOriginal($dbCol);

                // Move if column is currently empty
                if (empty($currentVal) && $currentVal !== '0' && $currentVal !== 0) {
                    $product->{$dbCol} = $this->castValue($dbCol, $value);
                    $moved[$key] = ['to' => $dbCol, 'value' => $value];
                }
                // Either way, remove from specs_json
                continue;
            }

            // If this is excluded metadata → remove from specs
            if (in_array($key, self::EXCLUDED_FROM_SPECS, true)) {
                continue;
            }

            // Keep as extra spec (flat, overwrite duplicates)
            $cleaned[$key] = (string) $value;
        }

        return [
            'moved'         => $moved,
            'cleaned_specs' => $cleaned,
        ];
    }

    /**
     * Convert specs_json from Repeater format [{key:..., value:...}] to flat {key: value}.
     */
    public function flattenSpecs(array $specs): array
    {
        $flat = [];

        if (empty($specs)) {
            return $flat;
        }

        // Repeater format: [{key: ..., value: ...}]
        if (isset($specs[0]) && is_array($specs[0]) && array_key_exists('key', $specs[0])) {
            foreach ($specs as $item) {
                $k = $item['key'] ?? null;
                $v = $item['value'] ?? null;
                if ($k !== null) {
                    $flat[$k] = $v; // Overwrite duplicates
                }
            }
        } else {
            // Already flat format
            $flat = $specs;
        }

        return $flat;
    }

    /**
     * Convert flat specs to Repeater format for Filament.
     */
    public function toRepeaterFormat(array $flat): array
    {
        $result = [];
        foreach ($flat as $key => $value) {
            $result[] = ['key' => $key, 'value' => (string) $value];
        }
        return $result;
    }

    /**
     * All DB columns that are considered "standard spec fields".
     * Used by form to validate what NOT to show in JSON repeater.
     */
    public static function standardColumns(): array
    {
        return array_unique(array_values(self::FIELD_MAP));
    }

    /**
     * Cast value to appropriate type for DB column.
     */
    public function castValue(string $column, $value)
    {
        return match ($column) {
            'btu'           => (int) $value,
            'capacity_kw'   => (float) $value,
            'hp'            => (float) $value,
            'inverter'      => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'cooling_type'  => $this->normalizeCoolingType($value),
            default         => (string) $value,
        };
    }

    /**
     * Normalize cooling type to DB enum values.
     */
    public function normalizeCoolingType($value): ?string
    {
        $v = mb_strtolower(trim((string) $value));

        if (str_contains($v, '2 chiều') || str_contains($v, '2 chieu') || $v === '2_chieu') {
            return '2_chieu';
        }
        if (str_contains($v, '1 chiều') || str_contains($v, '1 chieu') || $v === '1_chieu') {
            return '1_chieu';
        }

        return $value;
    }
}
