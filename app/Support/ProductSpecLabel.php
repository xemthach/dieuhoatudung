<?php

namespace App\Support;

/**
 * Central mapping of product spec keys to Vietnamese display labels.
 *
 * RULES:
 *   - DB keys (snake_case) are NEVER renamed.
 *   - This class provides DISPLAY labels only.
 *   - Used by: Frontend (show.blade.php), Admin (ProductForm), API, Comparison, Export.
 *   - New keys auto-fallback to humanized format (no raw snake_case shown to users).
 */
class ProductSpecLabel
{
    /**
     * Spec key → Vietnamese label mapping.
     * Grouped by HVAC domain for maintainability.
     */
    public const MAP = [
        // ── Hiệu suất năng lượng ──
        'eer'                           => 'Hiệu suất năng lượng (EER)',
        'cop'                           => 'Hệ số hiệu quả sưởi (COP)',
        'eer_cop'                       => 'EER / COP',
        'power_factor'                  => 'Hệ số công suất (PF)',

        // ── Dòng điện / Điện ──
        'rated_current_a'               => 'Dòng điện định mức (A)',
        'cooling_current_input_a'       => 'Dòng điện làm lạnh (A)',
        'heating_current_input_a'       => 'Dòng điện sưởi (A)',
        'cooling_power_input_kw'        => 'Công suất điện làm lạnh (kW)',
        'heating_power_input_kw'        => 'Công suất điện sưởi (kW)',
        'cooling_capacity_kw'           => 'Công suất lạnh (kW)',
        'heating_capacity_kw'           => 'Công suất sưởi (kW)',

        // ── Gas lạnh ──
        'refrigerant_charge_kg'         => 'Lượng gas nạp (kg)',
        'refrigerant_type'              => 'Loại gas',
        'refrigerant_factory_charge_kg' => 'Gas nạp tại nhà máy (kg)',

        // ── Dàn lạnh ──
        'indoor_model'                  => 'Model dàn lạnh',
        'indoor_airflow_cfm'            => 'Lưu lượng gió dàn lạnh (CFM)',
        'indoor_package_dim_mm'         => 'Kích thước đóng gói dàn lạnh (mm)',
        'indoor_package_weight_kg'      => 'Trọng lượng đóng gói dàn lạnh (kg)',
        'indoor_esp_nominal_pa'         => 'Cột áp tĩnh dàn lạnh (Pa)',
        'noise_db'                      => 'Độ ồn dàn lạnh (dB)',

        // ── Mặt nạ (Panel) ──
        'panel_dimensions_mm'           => 'Kích thước mặt nạ (mm)',
        'panel_package_dim_mm'          => 'Kích thước đóng gói mặt nạ (mm)',
        'panel_weight_kg'               => 'Trọng lượng mặt nạ (kg)',
        'panel_package_weight_kg'       => 'Trọng lượng đóng gói mặt nạ (kg)',

        // ── Dàn nóng ──
        'outdoor_model'                 => 'Model dàn nóng',
        'outdoor_noise_db'              => 'Độ ồn dàn nóng (dB)',
        'outdoor_package_dim_mm'        => 'Kích thước đóng gói dàn nóng (mm)',
        'outdoor_weight_kg'             => 'Trọng lượng dàn nóng (kg)',
        'outdoor_package_weight_kg'     => 'Trọng lượng đóng gói dàn nóng (kg)',

        // ── Đường ống lắp đặt ──
        'pipe_liquid'                   => 'Đường kính ống lỏng',
        'pipe_gas'                      => 'Đường kính ống gas',
        'pipe_max_height'               => 'Chênh lệch độ cao tối đa (m)',
        'pipe_max_length'               => 'Chiều dài ống tối đa (m)',
        'pipe_connections_liquid_pipe_mm'=> 'Kết nối ống lỏng (mm)',
        'pipe_connections_gas_pipe_mm'  => 'Kết nối ống gas (mm)',

        // ── Kích thước & Trọng lượng ──
        'weight_kg'                     => 'Trọng lượng (kg)',
        'dimensions_mm'                 => 'Kích thước (mm)',
        'net_dimensionswhd_mm'          => 'Kích thước thiết bị - RxSxC (mm)',
        'net_dimensions_whd_mm'         => 'Kích thước thiết bị - RxSxC (mm)',
        'packed_dimensionswhd_mm'       => 'Kích thước đóng gói - RxSxC (mm)',

        // ── Vận hành ──
        'loading_qty'                   => 'Số lượng đóng container (40\'GP/40\'HQ)',
        'moisture_protection'           => 'Bảo vệ chống ẩm',
        'max_connected_indoor'          => 'Số dàn lạnh kết nối tối đa',
        'capacity_range_hp'             => 'Dải công suất (HP)',
        'ambient_temp_operation_range_cooling' => 'Nhiệt độ hoạt động - Lạnh (°C)',
        'ambient_temp_operation_range_heating' => 'Nhiệt độ hoạt động - Sưởi (°C)',

        // ── Solar / Inverter ──
        'max_pv_input_power_w'          => 'Công suất PV đầu vào tối đa (W)',
        'max_dc_open_circuit_voltage_v' => 'Điện áp hở mạch DC tối đa (V)',
        'dc_voltage_vdc'                => 'Điện áp DC (VDC)',
        'ac_voltage'                    => 'Điện áp AC',
        'max_ac_output_power_w'         => 'Công suất AC đầu ra tối đa (W)',

        // ── Điều kiện test (35°C/46°C) ──
        'cooling35_1_capacity_kw'       => 'Công suất lạnh @35°C (kW)',
        'cooling35_1_buth'              => 'Công suất lạnh @35°C (BTU/h)',
        'cooling35_1_power_input_kw'    => 'Tiêu thụ điện @35°C (kW)',
        'cooling35_1_current_input_a'   => 'Dòng điện @35°C (A)',
        'cooling35_1_eer_buthw'         => 'EER @35°C (BTU/h/W)',
        'cooling46_2_capacity_kw'       => 'Công suất lạnh @46°C (kW)',
        'cooling46_2_buth'              => 'Công suất lạnh @46°C (BTU/h)',
        'cooling46_2_power_input_kw'    => 'Tiêu thụ điện @46°C (kW)',
        'cooling46_2_current_input_a'   => 'Dòng điện @46°C (A)',
        'cooling46_2_eer_buthw'         => 'EER @46°C (BTU/h/W)',
        'heating_3_capacity_kw'         => 'Công suất sưởi (kW)',
        'heating_3_buth'                => 'Công suất sưởi (BTU/h)',
        'heating_3_power_input_kw'      => 'Tiêu thụ điện sưởi (kW)',
        'heating_3_current_input_a'     => 'Dòng điện sưởi (A)',
        'heating_3_cop_ww'              => 'COP sưởi (W/W)',

        // ── ESP ──
        'esp_pa'                        => 'Cột áp tĩnh (Pa)',

        // ── Catalogue metadata ──
        'source_catalogue'              => 'Nguồn catalogue',
        'source_page'                   => 'Trang catalogue',
        'source_table'                  => 'Bảng catalogue',

        // ── Daikin efficiency specs ──
        'seer'                          => 'SEER (Hiệu suất mùa lạnh)',
        'scop'                          => 'SCOP (Hiệu suất mùa sưởi)',
        'cspf'                          => 'CSPF (Hệ số hiệu suất mùa)',
        'heating_kw'                    => 'Công suất sưởi (kW)',
        'compressor'                    => 'Loại máy nén',

        // ── Daikin Packaged specs ──
        'power_consumption_kw'          => 'Công suất điện tiêu thụ (kW)',
        'noise_indoor'                  => 'Độ ồn dàn lạnh (dB)',
        'noise_outdoor'                 => 'Độ ồn dàn nóng (dB)',
        'indoor_weight'                 => 'Trọng lượng dàn lạnh',
        'outdoor_weight'                => 'Trọng lượng dàn nóng',
        'height_diff'                   => 'Chênh lệch độ cao tối đa',
        'fan_type'                      => 'Kiểu quạt',
        'esp'                           => 'Cột áp tĩnh (ESP)',

        // ── LG catalogue specs ──
        'noise_detail'                  => 'Độ ồn chi tiết (SH/H/M/L)',
        'airflow_detail'                => 'Lưu lượng gió chi tiết',
        'pipe_length'                   => 'Chiều dài ống (tiêu chuẩn/tối đa)',
        'cooling_heating'               => 'Công suất sưởi',
        'sub_type'                      => 'Kiểu phụ',

        // ── Panasonic catalogue specs ──
        'series'                        => 'Dòng sản phẩm',
        'nanoe_x'                       => 'nanoe™ X',
        'temp_range'                    => 'Phạm vi nhiệt độ hoạt động',
        // noise_outdoor, pipe_max_length, power_consumption_kw → already defined above

        // ── Fallback OCR keys (Vietnamese without diacritics from PDF extraction) ──
        'model_dn_lnh'                  => 'Model dàn lạnh',
        'lu_lng_gi'                     => 'Lưu lượng gió',
        's_lng_dn_nng'                  => 'Số lượng dàn nóng',
        'indoor_dn_lnh_phm_vi'          => 'Phạm vi ESP dàn lạnh (Pa)',
        'indoor_dn_lnh_n'               => 'Độ ồn dàn lạnh (dB)',
        'indoor_rng_x_su_x_cao_c_bao_b'=> 'Kích thước đóng gói dàn lạnh (mm)',
        'outdoor_dn_nng_n'              => 'Độ ồn dàn nóng (dB)',
        'outdoor_rng_x_su_x_cao_c_bao_b'=> 'Kích thước đóng gói dàn nóng (mm)',
        'iu_ho_m_trn_umatch_cassette'   => 'Model Cassette U-Match',
        'iu_ho_m_trn'                   => 'Model âm trần',
        'iu_ho_m_trn_umatch_duct'       => 'Model ống gió U-Match',
        'thng_s_h_iu_ho_thng_s_ca_chiu_si_c_t_mu_xanh' => 'Thông số sưởi',
        'pipe_kt_ni_ti_a'               => 'Kết nối ống tại chỗ',
        'pipe_ng_ng_kt_ni'              => 'Đường ống kết nối',
        'pipe_ng_knh_ng_lng'            => 'Đường kính ống lỏng',
        'd_liu_in_cng_sut_in_chiu_lnh'  => 'Công suất điện chiều lạnh (kW)',
        'd_liu_in_cng_sut_u_vo_nh_mc'   => 'Công suất đầu vào định mức (kW)',
        'd_liu_in_dng_in_chiu_lnh'      => 'Dòng điện chiều lạnh (A)',
        'd_liu_in_dng_in_nh_mc'         => 'Dòng điện định mức (A)',
        'd_liu_in_in_p_ti_thiuti_a'     => 'Điện áp tại thiết bị',
        'mi_cht_lnh_loi'                => 'Loại môi chất lạnh',
        'mi_cht_lnh_np'                 => 'Lượng gas nạp (kg)',
    ];

    /**
     * Groups for organized display on frontend.
     * Key = group label, Value = array of spec keys belonging to this group.
     */
    public const GROUPS = [
        'Hiệu suất năng lượng' => [
            'eer', 'cop', 'eer_cop', 'power_factor', 'seer', 'scop', 'cspf',
            'cooling35_1_eer_buthw', 'cooling46_2_eer_buthw', 'heating_3_cop_ww',
        ],
        'Công suất & Điện năng' => [
            'rated_current_a', 'heating_kw', 'power_consumption_kw',
            'cooling_capacity_kw', 'cooling_power_input_kw', 'cooling_current_input_a',
            'heating_capacity_kw', 'heating_power_input_kw', 'heating_current_input_a',
            'cooling35_1_capacity_kw', 'cooling35_1_buth', 'cooling35_1_power_input_kw', 'cooling35_1_current_input_a',
            'cooling46_2_capacity_kw', 'cooling46_2_buth', 'cooling46_2_power_input_kw', 'cooling46_2_current_input_a',
            'heating_3_capacity_kw', 'heating_3_buth', 'heating_3_power_input_kw', 'heating_3_current_input_a',
        ],
        'Dàn lạnh' => [
            'indoor_model', 'indoor_airflow_cfm', 'airflow_detail',
            'noise_db', 'noise_indoor', 'noise_detail',
            'indoor_esp_nominal_pa', 'esp_pa', 'esp',
            'indoor_package_dim_mm', 'indoor_package_weight_kg', 'indoor_weight',
        ],
        'Mặt nạ' => [
            'panel_dimensions_mm', 'panel_package_dim_mm',
            'panel_weight_kg', 'panel_package_weight_kg',
        ],
        'Dàn nóng' => [
            'outdoor_model', 'outdoor_noise_db', 'noise_outdoor',
            'outdoor_package_dim_mm', 'outdoor_weight_kg', 'outdoor_package_weight_kg', 'outdoor_weight',
        ],
        'Đường ống lắp đặt' => [
            'pipe_liquid', 'pipe_gas',
            'pipe_connections_liquid_pipe_mm', 'pipe_connections_gas_pipe_mm',
            'pipe_max_height', 'pipe_max_length', 'pipe_length', 'height_diff',
        ],
        'Gas lạnh' => [
            'refrigerant_charge_kg', 'refrigerant_type', 'refrigerant_factory_charge_kg',
        ],
        'Kích thước & Đóng gói' => [
            'weight_kg', 'dimensions_mm',
            'net_dimensionswhd_mm', 'net_dimensions_whd_mm', 'packed_dimensionswhd_mm',
            'loading_qty',
        ],
        'Vận hành' => [
            'compressor', 'fan_type', 'series', 'nanoe_x', 'temp_range',
            'moisture_protection', 'max_connected_indoor', 'capacity_range_hp',
            'ambient_temp_operation_range_cooling', 'ambient_temp_operation_range_heating',
        ],
        'Solar / Inverter' => [
            'max_pv_input_power_w', 'max_dc_open_circuit_voltage_v',
            'dc_voltage_vdc', 'ac_voltage', 'max_ac_output_power_w',
        ],
    ];

    /**
     * Keys to hide from frontend display (metadata, not user-facing).
     */
    public const HIDDEN_KEYS = [
        'source_catalogue', 'source_page', 'indoor_model', 'outdoor_model',
    ];

    /**
     * Get display label for a spec key.
     * Never returns raw snake_case — always a readable string.
     */
    public static function label(string $key): string
    {
        return self::MAP[$key] ?? self::humanize($key);
    }

    /**
     * Get all spec keys as label options (for Filament select/repeater).
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::MAP as $key => $label) {
            $options[$key] = $label;
        }
        return $options;
    }

    /**
     * Group specs for organized frontend display.
     *
     * @param  array  $specs  [{key: ..., value: ...}, ...]
     * @return array  [group_label => [{key, label, value}, ...], ...]
     */
    public static function groupSpecs(array $specs): array
    {
        $grouped = [];
        $ungrouped = [];
        $usedKeys = [];

        // Build reverse lookup: key → group
        $keyToGroup = [];
        foreach (self::GROUPS as $group => $keys) {
            foreach ($keys as $k) {
                $keyToGroup[$k] = $group;
            }
        }

        foreach ($specs as $spec) {
            $key = $spec['key'] ?? '';
            $value = $spec['value'] ?? '';
            if (!$key || !$value || $value === '-') continue;

            // Skip hidden keys
            if (in_array($key, self::HIDDEN_KEYS, true)) continue;

            $item = [
                'key'   => $key,
                'label' => self::label($key),
                'value' => self::formatValue($key, $value),
            ];

            if (isset($keyToGroup[$key])) {
                $group = $keyToGroup[$key];
                $grouped[$group][] = $item;
            } else {
                $ungrouped[] = $item;
            }

            $usedKeys[] = $key;
        }

        // Add ungrouped specs to "Thông số khác"
        if (!empty($ungrouped)) {
            $grouped['Thông số khác'] = $ungrouped;
        }

        return $grouped;
    }

    /**
     * Format a spec value for human-friendly display.
     */
    public static function formatValue(string $key, string $value): string
    {
        // Dimension values: add mm unit if not present
        if (str_contains($key, 'dim_mm') || str_contains($key, 'dimensions_mm')) {
            $value = str_replace('×', ' × ', str_replace('x', ' × ', $value));
            if (!str_contains($value, 'mm')) {
                $value .= ' mm';
            }
        }

        // Weight: add kg if not present
        if (str_contains($key, 'weight_kg') && !str_contains($value, 'kg')) {
            $value .= ' kg';
        }

        // Noise: add dB if not present
        if (str_contains($key, 'noise_db') && !str_contains($value, 'dB')) {
            $value .= ' dB(A)';
        }

        // Gas charge: add kg if not present
        if ($key === 'refrigerant_charge_kg' && !str_contains($value, 'kg')) {
            $value .= ' kg';
        }

        // Pipe dimensions: clean up inch notation
        if (str_starts_with($key, 'pipe_') && preg_match('/(\d+\/\d+)"\((\d+\.?\d*)\)/', $value, $m)) {
            $value = "{$m[1]}\" ({$m[2]} mm)";
        }

        // Height/Length: add m if not present
        if (in_array($key, ['pipe_max_height', 'pipe_max_length']) && !str_contains($value, 'm')) {
            $value .= ' m';
        }

        return $value;
    }

    /**
     * Convert snake_case to human readable (fallback for unmapped keys).
     * Example: indoor_airflow_cfm → Indoor airflow cfm
     */
    private static function humanize(string $key): string
    {
        // Replace common abbreviations
        $replacements = [
            '_mm' => ' (mm)',
            '_kg' => ' (kg)',
            '_db' => ' (dB)',
            '_pa' => ' (Pa)',
            '_cfm' => ' (CFM)',
            '_kw' => ' (kW)',
            '_a' => ' (A)',
            '_v' => ' (V)',
            '_w' => ' (W)',
            'indoor_' => 'Dàn lạnh - ',
            'outdoor_' => 'Dàn nóng - ',
            'panel_' => 'Mặt nạ - ',
            'pipe_' => 'Đường ống - ',
        ];

        $result = $key;
        foreach ($replacements as $search => $replace) {
            if (str_ends_with($result, $search)) {
                $result = substr($result, 0, -strlen($search)) . $replace;
            } elseif (str_starts_with($result, $search)) {
                $result = $replace . substr($result, strlen($search));
            }
        }

        // Clean up remaining underscores
        $result = str_replace('_', ' ', $result);
        return mb_convert_case($result, MB_CASE_TITLE, 'UTF-8');
    }
}
