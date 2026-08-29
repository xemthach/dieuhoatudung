<?php

namespace App\Support\Catalog;

/**
 * Canonical, source-backed technical schema for wall-mounted split products.
 *
 * The schema describes import/display fields only. Feature availability remains
 * in the catalog evidence matrix until the application has a non-boolean
 * feature storage contract.
 */
final class WallMountedTechnicalSchema
{
    public const CATEGORY_NAME = 'Điều hòa treo tường';
    public const CATEGORY_SLUG = 'dieu-hoa-treo-tuong';
    public const VERSION = 'wall-mounted-v1';

    public static function schema(): array
    {
        $rows = [
            // Core technical facts and compatibility summaries.
            ['technical_capacity_btu', 'Công suất lạnh danh định', 'measurement', 'BTU', ['btu', 'cooling_capacity_btu_nominal'], true, 'Core technical capacity; Product.btu is not overwritten.'],
            ['capacity_kw', 'Công suất lạnh danh định', 'measurement', 'kW', ['cooling_capacity_kw_nominal'], true, 'Source-native nominal cooling capacity.'],
            ['hp', 'Công suất HP', 'decimal', 'HP', [], false, 'Optional. Source group 3-3.5 HP remains blank.'],
            ['inverter', 'Công nghệ Inverter', 'boolean', 'none', [], true, 'Published series behavior.'],
            ['cooling_type', 'Chế độ vận hành', 'enum', 'none', ['cooling_heating_type'], true, '1 chiều lạnh or 2 chiều lạnh/sưởi.'],
            ['voltage', 'Nguồn điện', 'voltage', 'V', ['power_supply'], true, 'Raw published supply contract; may include multiple voltage/frequency variants.'],
            ['power_input_kw', 'Điện năng tiêu thụ lạnh danh định', 'measurement', 'kW', [], true, 'Compatibility summary derived exactly from published W / 1000 in the import artifact.'],
            ['airflow', 'Lưu lượng gió lạnh H/M/L/Q', 'text', 'm³/min', [], false, 'Compatibility summary; detailed source-native levels are canonical below.'],
            ['noise_level', 'Độ ồn dàn lạnh H/M/L/Q', 'text', 'dB(A)', [], false, 'Compatibility summary; detailed levels are canonical below.'],
            ['indoor_dimensions', 'Kích thước dàn lạnh C×R×D', 'dimension', 'mm', [], false, 'Compatibility summary; C=height, R=width, D=depth.'],
            ['outdoor_dimensions', 'Kích thước dàn nóng C×R×D', 'dimension', 'mm', [], false, 'Compatibility summary; C=height, R=width, D=depth.'],
            ['weight', 'Khối lượng dàn lạnh', 'weight', 'kg', [], false, 'Compatibility mirror of indoor_weight_kg.'],

            // Capacity and performance ranges.
            ['cooling_capacity_kw_min', 'Công suất lạnh tối thiểu', 'decimal', 'kW'],
            ['cooling_capacity_kw_max', 'Công suất lạnh tối đa', 'decimal', 'kW'],
            ['cooling_capacity_btu_min', 'Công suất lạnh tối thiểu', 'number', 'BTU'],
            ['cooling_capacity_btu_max', 'Công suất lạnh tối đa', 'number', 'BTU'],
            ['heating_capacity_kw_min', 'Công suất sưởi tối thiểu', 'decimal', 'kW'],
            ['heating_capacity_kw_nominal', 'Công suất sưởi danh định', 'decimal', 'kW'],
            ['heating_capacity_kw_max', 'Công suất sưởi tối đa', 'decimal', 'kW'],
            ['heating_capacity_btu_min', 'Công suất sưởi tối thiểu', 'number', 'BTU'],
            ['heating_capacity_btu_nominal', 'Công suất sưởi danh định', 'number', 'BTU'],
            ['heating_capacity_btu_max', 'Công suất sưởi tối đa', 'number', 'BTU'],
            ['cspf', 'CSPF', 'decimal', 'none'],

            // Electrical.
            ['phase', 'Pha điện', 'text', 'none'],
            ['frequency', 'Tần số', 'text', 'Hz'],
            ['power_supply_location', 'Vị trí cấp điện', 'enum', 'none'],
            ['rated_current_cooling_a', 'Dòng điện định mức lạnh', 'text', 'A', [], false, 'Text preserves voltage-dependent multi-values.'],
            ['rated_current_heating_a', 'Dòng điện định mức sưởi', 'text', 'A', [], false, 'Blank for cooling-only models.'],
            ['power_consumption_cooling_w_min', 'Điện năng lạnh tối thiểu', 'number', 'W'],
            ['power_consumption_cooling_w_nominal', 'Điện năng lạnh danh định', 'number', 'W'],
            ['power_consumption_cooling_w_max', 'Điện năng lạnh tối đa', 'number', 'W'],
            ['power_consumption_heating_w_min', 'Điện năng sưởi tối thiểu', 'number', 'W'],
            ['power_consumption_heating_w_nominal', 'Điện năng sưởi danh định', 'number', 'W'],
            ['power_consumption_heating_w_max', 'Điện năng sưởi tối đa', 'number', 'W'],

            // Indoor airflow and sound.
            ['indoor_airflow_cooling_high_m3_min', 'Lưu lượng gió lạnh cao', 'decimal', 'm³/min'],
            ['indoor_airflow_cooling_medium_m3_min', 'Lưu lượng gió lạnh trung bình', 'decimal', 'm³/min'],
            ['indoor_airflow_cooling_low_m3_min', 'Lưu lượng gió lạnh thấp', 'decimal', 'm³/min'],
            ['indoor_airflow_cooling_quiet_m3_min', 'Lưu lượng gió lạnh yên tĩnh', 'decimal', 'm³/min'],
            ['indoor_airflow_heating_high_m3_min', 'Lưu lượng gió sưởi cao', 'decimal', 'm³/min'],
            ['indoor_airflow_heating_medium_m3_min', 'Lưu lượng gió sưởi trung bình', 'decimal', 'm³/min'],
            ['indoor_airflow_heating_low_m3_min', 'Lưu lượng gió sưởi thấp', 'decimal', 'm³/min'],
            ['indoor_airflow_heating_quiet_m3_min', 'Lưu lượng gió sưởi yên tĩnh', 'decimal', 'm³/min'],
            ['fan_speed_modes', 'Các cấp tốc độ quạt', 'text', 'none'],
            ['indoor_noise_cooling_high_db', 'Độ ồn dàn lạnh - lạnh cao', 'number', 'dB(A)'],
            ['indoor_noise_cooling_medium_db', 'Độ ồn dàn lạnh - lạnh trung bình', 'number', 'dB(A)'],
            ['indoor_noise_cooling_low_db', 'Độ ồn dàn lạnh - lạnh thấp', 'number', 'dB(A)'],
            ['indoor_noise_cooling_quiet_db', 'Độ ồn dàn lạnh - lạnh yên tĩnh', 'number', 'dB(A)'],
            ['indoor_noise_heating_high_db', 'Độ ồn dàn lạnh - sưởi cao', 'number', 'dB(A)'],
            ['indoor_noise_heating_medium_db', 'Độ ồn dàn lạnh - sưởi trung bình', 'number', 'dB(A)'],
            ['indoor_noise_heating_low_db', 'Độ ồn dàn lạnh - sưởi thấp', 'number', 'dB(A)'],
            ['indoor_noise_heating_quiet_db', 'Độ ồn dàn lạnh - sưởi yên tĩnh', 'number', 'dB(A)'],

            // Indoor/outdoor units, compressor and refrigerant charge.
            ['indoor_height_mm', 'Chiều cao dàn lạnh', 'number', 'mm'],
            ['indoor_width_mm', 'Chiều rộng dàn lạnh', 'number', 'mm'],
            ['indoor_depth_mm', 'Chiều sâu dàn lạnh', 'number', 'mm'],
            ['indoor_weight_kg', 'Khối lượng dàn lạnh', 'decimal', 'kg'],
            ['compressor_type', 'Loại máy nén', 'text', 'none'],
            ['compressor_output_w', 'Công suất đầu ra máy nén', 'number', 'W'],
            ['refrigerant_charge_kg', 'Lượng nạp môi chất lạnh', 'decimal', 'kg', [], false, 'Catalog states charge only; refrigerant type is intentionally not inferred.'],
            ['outdoor_noise_cooling_high_db', 'Độ ồn dàn nóng - lạnh cao', 'number', 'dB(A)'],
            ['outdoor_noise_cooling_low_db', 'Độ ồn dàn nóng - lạnh thấp', 'number', 'dB(A)'],
            ['outdoor_noise_heating_high_db', 'Độ ồn dàn nóng - sưởi cao', 'number', 'dB(A)'],
            ['outdoor_noise_heating_low_db', 'Độ ồn dàn nóng - sưởi thấp', 'number', 'dB(A)'],
            ['outdoor_height_mm', 'Chiều cao dàn nóng', 'number', 'mm'],
            ['outdoor_width_mm', 'Chiều rộng dàn nóng', 'number', 'mm'],
            ['outdoor_depth_mm', 'Chiều sâu dàn nóng', 'number', 'mm'],
            ['outdoor_weight_kg', 'Khối lượng dàn nóng', 'decimal', 'kg'],

            // Piping and operating envelope.
            ['liquid_pipe_mm', 'Đường kính ống lỏng', 'decimal', 'mm'],
            ['gas_pipe_mm', 'Đường kính ống gas', 'decimal', 'mm'],
            ['drain_pipe_mm', 'Đường kính ống xả', 'decimal', 'mm'],
            ['max_pipe_length_m', 'Chiều dài ống tối đa', 'number', 'm'],
            ['max_height_difference_m', 'Chênh lệch độ cao tối đa', 'number', 'm'],
            ['cooling_operating_min_c_db', 'Nhiệt độ làm lạnh tối thiểu (DB)', 'decimal', '°C'],
            ['cooling_operating_max_c_db', 'Nhiệt độ làm lạnh tối đa (DB)', 'decimal', '°C'],
            ['heating_operating_min_c_wb', 'Nhiệt độ sưởi tối thiểu (WB)', 'decimal', '°C'],
            ['heating_operating_max_c_wb', 'Nhiệt độ sưởi tối đa (WB)', 'decimal', '°C'],
        ];

        $fields = [];
        foreach ($rows as $index => $row) {
            [$key, $label, $type, $unit] = $row;
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'unit' => $unit,
                'required' => false,
                'visible_frontend' => true,
                'visible_compare' => true,
                'use_for_ai' => (bool) ($row[5] ?? false),
                'aliases' => $row[4] ?? [],
                'sort_order' => ($index + 1) * 10,
                'validation_pattern' => '',
                'notes' => $row[6] ?? '',
            ];
        }

        return ['version' => self::VERSION, 'status' => 'active', 'fields' => $fields];
    }
}
