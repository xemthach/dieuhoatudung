<?php

namespace App\Support\Catalog;

/** Category-specific, source-backed schemas for Daikin SkyAir combinations. */
final class SkyAirTechnicalSchema
{
    public const VERSION_PREFIX = 'skyair';

    public const CATEGORIES = [
        'cassette' => ['id' => 24, 'name' => 'Điều hòa âm trần Cassette'],
        'ducted' => ['id' => 27, 'name' => 'Điều hòa giấu trần nối ống gió'],
        'floor_standing' => ['id' => 25, 'name' => 'Điều hòa tủ đứng'],
        'ceiling_suspended' => ['id' => 23, 'name' => 'Điều hòa đặt sàn/áp trần'],
    ];

    public static function version(string $type): string
    {
        return self::VERSION_PREFIX.'-'.$type.'-v1';
    }

    public static function schema(string $type): array
    {
        $base = [
            ['technical_capacity_btu', 'Công suất lạnh danh định', 'measurement', 'BTU', true],
            ['capacity_kw', 'Công suất lạnh danh định', 'measurement', 'kW', true],
            ['cooling_capacity_kw_min', 'Công suất lạnh tối thiểu', 'decimal', 'kW'],
            ['cooling_capacity_kw_max', 'Công suất lạnh tối đa', 'decimal', 'kW'],
            ['cooling_capacity_btu_min', 'Công suất lạnh tối thiểu', 'number', 'BTU'],
            ['cooling_capacity_btu_max', 'Công suất lạnh tối đa', 'number', 'BTU'],
            ['heating_capacity_kw_nominal', 'Công suất sưởi danh định', 'decimal', 'kW'],
            ['heating_capacity_btu_nominal', 'Công suất sưởi danh định', 'number', 'BTU'],
            ['power_input_kw', 'Điện năng tiêu thụ', 'measurement', 'kW'],
            ['cop', 'COP', 'decimal', 'none'],
            ['cspf', 'CSPF', 'decimal', 'none'],
            ['inverter', 'Công nghệ Inverter', 'boolean', 'none', true],
            ['cooling_type', 'Chế độ vận hành', 'enum', 'none', true],
            ['refrigerant_gas', 'Môi chất lạnh', 'refrigerant', 'none', true],
            ['refrigerant_charge_kg', 'Lượng nạp môi chất lạnh', 'decimal', 'kg'],
            ['phase', 'Pha điện dàn nóng', 'text', 'none', true],
            ['voltage', 'Điện áp dàn nóng', 'voltage', 'V'],
            ['frequency', 'Tần số', 'text', 'Hz'],
            ['airflow', 'Lưu lượng gió theo cấp', 'text', 'm³/min'],
            ['noise_level', 'Độ ồn theo cấp', 'text', 'dB(A)'],
            ['indoor_dimensions', 'Kích thước dàn lạnh', 'dimension', 'mm'],
            ['outdoor_dimensions', 'Kích thước dàn nóng', 'dimension', 'mm'],
            ['indoor_weight_kg', 'Khối lượng dàn lạnh', 'decimal', 'kg'],
            ['outdoor_weight_kg', 'Khối lượng dàn nóng', 'decimal', 'kg'],
            ['liquid_pipe_mm', 'Đường kính ống lỏng', 'decimal', 'mm'],
            ['gas_pipe_mm', 'Đường kính ống hơi', 'decimal', 'mm'],
            ['drain_pipe_mm', 'Đường kính ống xả', 'text', 'mm'],
            ['drain_lift_height_mm', 'Độ nâng bơm nước xả', 'number', 'mm'],
            ['max_actual_pipe_length_m', 'Chiều dài ống thực tối đa', 'number', 'm'],
            ['max_equivalent_pipe_length_m', 'Chiều dài ống tương đương tối đa', 'number', 'm'],
            ['max_height_difference_m', 'Chênh lệch độ cao tối đa', 'number', 'm'],
            ['cooling_operating_range', 'Dải nhiệt độ làm lạnh', 'text', '°C'],
            ['heating_operating_range', 'Dải nhiệt độ sưởi', 'text', '°C'],
            ['compressor_type', 'Loại máy nén', 'text', 'none'],
        ];

        $specific = match ($type) {
            'cassette' => [
                ['panel_model', 'Model mặt nạ trang trí', 'text', 'none'],
                ['panel_color', 'Màu mặt nạ', 'text', 'none'],
                ['panel_dimensions', 'Kích thước mặt nạ', 'dimension', 'mm'],
                ['panel_weight_kg', 'Khối lượng mặt nạ', 'decimal', 'kg'],
            ],
            'ducted' => [
                ['external_static_pressure_pa', 'Áp suất tĩnh ngoài', 'text', 'Pa', true],
                ['external_static_pressure_range_pa', 'Dải áp suất tĩnh ngoài', 'text', 'Pa'],
                ['external_static_pressure_factory_pa', 'Áp suất tĩnh cài đặt xuất xưởng', 'text', 'Pa'],
                ['filter_requirement', 'Yêu cầu phin lọc', 'text', 'none'],
            ],
            'floor_standing', 'ceiling_suspended' => [],
            default => throw new \InvalidArgumentException("Unknown SkyAir equipment type: {$type}"),
        };

        $fields = [];
        foreach (array_merge($base, $specific) as $index => $row) {
            $fields[] = [
                'key' => $row[0],
                'label' => $row[1],
                'type' => $row[2],
                'unit' => $row[3],
                'required' => false,
                'visible_frontend' => true,
                'visible_compare' => true,
                'use_for_ai' => (bool) ($row[4] ?? false),
                'aliases' => [],
                'sort_order' => ($index + 1) * 10,
                'validation_pattern' => '',
                'notes' => 'Daikin SkyAir 2026 technical table; value requires field-level provenance.',
            ];
        }

        return ['version' => self::version($type), 'status' => 'active', 'fields' => $fields];
    }
}
