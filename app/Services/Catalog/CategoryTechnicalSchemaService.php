<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CategoryTechnicalSchemaService
{
    public const STATUSES = ['missing', 'draft', 'active', 'deprecated'];

    public const FIELD_KEYS = [
        'capacity_btu',
        'capacity_kw',
        'cooling_capacity',
        'heating_capacity',
        'power_input',
        'cop',
        'eer',
        'inverter',
        'refrigerant',
        'voltage',
        'phase',
        'airflow',
        'noise_level',
        'static_pressure',
        'indoor_dimensions',
        'outdoor_dimensions',
        'indoor_weight',
        'outdoor_weight',
        'weight',
        'pipe_size_liquid',
        'pipe_size_gas',
        'max_pipe_length',
        'max_height_difference',
        'operating_range',
        'origin',
        'warranty',
    ];

    public const FIELD_TYPES = [
        'text',
        'number',
        'decimal',
        'boolean',
        'enum',
        'measurement',
        'dimension',
        'voltage',
        'pressure',
        'airflow',
        'noise',
        'weight',
        'refrigerant',
    ];

    public const UNITS = [
        'BTU',
        'kW',
        'W',
        'Pa',
        'dB',
        'mm',
        'kg',
        'm³/h',
        'V',
        'A',
        'Hz',
        'HP',
        'm',
        '°C',
        'none',
    ];

    public const PRESETS = [
        'cassette' => 'Điều hòa âm trần Cassette',
        'duct' => 'Điều hòa giấu trần nối ống gió',
        'floor_standing' => 'Điều hòa tủ đứng',
        'floor_ceiling' => 'Điều hòa đặt sàn/áp trần',
        'vrf_gmv' => 'VRF/GMV',
        'general_hvac' => 'HVAC tổng quát',
    ];

    public function normalize(?array $schema, string $version = 'v1', string $status = 'draft'): array
    {
        $schema ??= [];
        $fields = $this->extractFields($schema);
        $normalizedFields = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $this->normalizeKey((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $aliases = $field['aliases'] ?? [];
            if (is_string($aliases)) {
                $aliases = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $aliases) ?: [])));
            }

            $type = (string) ($field['type'] ?? 'text');
            $unit = (string) ($field['unit'] ?? 'none');

            $normalizedFields[] = [
                'key' => $key,
                'label' => trim((string) ($field['label'] ?? Str::headline(str_replace('_', ' ', $key)))),
                'type' => in_array($type, self::FIELD_TYPES, true) ? $type : 'text',
                'unit' => in_array($unit, self::UNITS, true) ? $unit : 'none',
                'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
                'visible_frontend' => filter_var($field['visible_frontend'] ?? true, FILTER_VALIDATE_BOOL),
                'visible_compare' => filter_var($field['visible_compare'] ?? true, FILTER_VALIDATE_BOOL),
                'use_for_ai' => filter_var($field['use_for_ai'] ?? false, FILTER_VALIDATE_BOOL),
                'aliases' => array_values(array_unique(array_filter(array_map(fn ($alias) => trim((string) $alias), (array) $aliases)))),
                'sort_order' => is_numeric($field['sort_order'] ?? null) ? (int) $field['sort_order'] : (($index + 1) * 10),
                'validation_pattern' => trim((string) ($field['validation_pattern'] ?? '')),
                'notes' => trim((string) ($field['notes'] ?? '')),
            ];
        }

        usort($normalizedFields, fn (array $a, array $b): int => [$a['sort_order'], $a['key']] <=> [$b['sort_order'], $b['key']]);

        return [
            'version' => (string) (Arr::get($schema, 'version') ?: $version ?: 'v1'),
            'status' => in_array((string) (Arr::get($schema, 'status') ?: $status), self::STATUSES, true)
                ? (string) (Arr::get($schema, 'status') ?: $status)
                : 'draft',
            'fields' => array_values($normalizedFields),
        ];
    }

    public function validate(array $schema): array
    {
        $errors = [];
        $schema = $this->normalize($schema, (string) ($schema['version'] ?? 'v1'), (string) ($schema['status'] ?? 'draft'));
        $keys = [];

        if (! in_array($schema['status'], self::STATUSES, true)) {
            $errors[] = 'status không hợp lệ.';
        }

        foreach ($schema['fields'] as $index => $field) {
            $path = 'fields.'.($index + 1);

            if ($field['key'] === '') {
                $errors[] = "{$path}.key bắt buộc.";
            }

            if (in_array($field['key'], $keys, true)) {
                $errors[] = "{$path}.key bị trùng: {$field['key']}.";
            }
            $keys[] = $field['key'];

            if ($field['label'] === '') {
                $errors[] = "{$path}.label bắt buộc.";
            }

            if (! in_array($field['type'], self::FIELD_TYPES, true)) {
                $errors[] = "{$path}.type không hợp lệ.";
            }

            if (! in_array($field['unit'], self::UNITS, true)) {
                $errors[] = "{$path}.unit không hợp lệ.";
            }

            if ($field['validation_pattern'] !== '' && @preg_match($field['validation_pattern'], '') === false) {
                $errors[] = "{$path}.validation_pattern không phải regex hợp lệ.";
            }
        }

        if ($schema['status'] === 'active' && $schema['fields'] === []) {
            $errors[] = 'Schema active phải có ít nhất một field.';
        }

        return array_values(array_unique($errors));
    }

    public function presetFor(?string $categoryName, ?string $preset = null): array
    {
        $preset = $preset ?: $this->inferPreset($categoryName);
        $keys = match ($preset) {
            'cassette' => ['capacity_btu', 'inverter', 'refrigerant', 'voltage', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'indoor_weight', 'outdoor_weight', 'pipe_size_liquid', 'pipe_size_gas'],
            'duct' => ['capacity_btu', 'inverter', 'refrigerant', 'voltage', 'static_pressure', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'pipe_size_liquid', 'pipe_size_gas'],
            'floor_standing' => ['capacity_btu', 'inverter', 'refrigerant', 'voltage', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'weight'],
            'vrf_gmv' => ['capacity_kw', 'power_input', 'refrigerant', 'voltage', 'phase', 'max_pipe_length', 'max_height_difference', 'operating_range'],
            'floor_ceiling' => ['capacity_btu', 'inverter', 'refrigerant', 'voltage', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'pipe_size_liquid', 'pipe_size_gas', 'max_pipe_length', 'max_height_difference'],
            default => ['capacity_btu', 'capacity_kw', 'inverter', 'refrigerant', 'voltage', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions'],
        };

        $fields = [];
        foreach ($keys as $index => $key) {
            $fields[] = $this->definition($key, ($index + 1) * 10);
        }

        return $this->normalize([
            'version' => 'v1',
            'status' => 'draft',
            'fields' => $fields,
        ]);
    }

    public function inferPreset(?string $categoryName): string
    {
        $ascii = Str::ascii(Str::lower((string) $categoryName));

        return match (true) {
            Str::contains($ascii, ['cassette', 'am tran']) => 'cassette',
            Str::contains($ascii, ['giau tran', 'ong gio', 'duct']) => 'duct',
            Str::contains($ascii, ['tu dung']) => 'floor_standing',
            Str::contains($ascii, ['dat san', 'ap tran']) => 'floor_ceiling',
            Str::contains($ascii, ['vrf', 'gmv', 'vrv']) => 'vrf_gmv',
            default => 'general_hvac',
        };
    }

    public function fieldsFor(ProductCategory $category, ?string $purpose = null): array
    {
        $fields = $this->normalize($category->technicalSchema(), $category->technical_schema_version ?? 'v1', $category->technicalSchemaStatus())['fields'];

        $fields = match ($purpose) {
            'frontend' => array_filter($fields, fn (array $field): bool => (bool) $field['visible_frontend']),
            'compare' => array_filter($fields, fn (array $field): bool => (bool) $field['visible_compare']),
            'ai' => array_filter($fields, fn (array $field): bool => (bool) $field['use_for_ai']),
            default => $fields,
        };

        return array_values($fields);
    }

    public function aliasMap(ProductCategory $category): array
    {
        $map = [];

        foreach ($this->fieldsFor($category) as $field) {
            $key = $field['key'];
            $map[$this->normalizeAlias($key)] = $key;
            $map[$this->normalizeAlias($field['label'])] = $key;

            foreach ((array) $field['aliases'] as $alias) {
                $map[$this->normalizeAlias($alias)] = $key;
            }
        }

        foreach ((array) ($category->getRawOriginal('technical_schema_json') ? json_decode((string) $category->getRawOriginal('technical_schema_json'), true) : []) as $schemaKey => $schemaValue) {
            if ($schemaKey !== 'field_aliases' || ! is_array($schemaValue)) {
                continue;
            }

            foreach ($schemaValue as $alias => $key) {
                if (is_string($alias) && is_string($key)) {
                    $map[$this->normalizeAlias($alias)] = $this->normalizeKey($key);
                }
            }
        }

        return array_filter($map);
    }

    public function normalizeSchemaKey(ProductCategory $category, string $key): string
    {
        $normalized = $this->normalizeAlias($key);

        return $this->aliasMap($category)[$normalized] ?? $this->normalizeKey($key);
    }

    public function productSpecsFor(Product $product, string $purpose = 'frontend'): array
    {
        $category = $product->category;
        if (! $category?->hasTechnicalSchema()) {
            return [];
        }

        $flatSpecs = $this->flatProductSpecs($product);
        $rows = [];

        foreach ($this->fieldsFor($category, $purpose) as $field) {
            $key = $field['key'];
            $value = $flatSpecs[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $field['label'],
                'value' => $this->formatValue($value, $field),
                'sort_order' => $field['sort_order'],
                'unit' => $field['unit'],
            ];
        }

        return $rows;
    }

    public function flatProductSpecs(Product $product): array
    {
        $category = $product->category;
        $flat = [
            'capacity_btu' => $product->btu,
            'capacity_kw' => $product->capacity_kw,
            'hp' => $product->hp,
            'inverter' => $product->inverter,
            'refrigerant' => $product->refrigerant_gas,
            'voltage' => $product->voltage,
            'airflow' => $product->airflow,
            'noise_level' => $product->noise_level,
            'indoor_dimensions' => $product->indoor_dimensions,
            'outdoor_dimensions' => $product->outdoor_dimensions,
            'weight' => $product->weight,
            'warranty' => strip_tags((string) $product->warranty_info),
        ];

        $specs = (array) ($product->specs_json ?? []);
        if (isset($specs[0]) && is_array($specs[0])) {
            foreach ($specs as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $flat[$category ? $this->normalizeSchemaKey($category, $key) : $this->normalizeKey($key)] = $item['value'] ?? null;
            }
        } else {
            foreach ($specs as $key => $value) {
                if (is_string($key)) {
                    $flat[$category ? $this->normalizeSchemaKey($category, $key) : $this->normalizeKey($key)] = $value;
                }
            }
        }

        return array_filter($flat, fn ($value): bool => $value !== null && $value !== '');
    }

    private function definition(string $key, int $sortOrder): array
    {
        $definitions = [
            'capacity_btu' => ['Công suất lạnh', 'measurement', 'BTU', true, ['công suất', 'cooling capacity', 'capacity', 'BTU']],
            'capacity_kw' => ['Công suất lạnh', 'measurement', 'kW', true, ['cooling capacity kW', 'capacity kw']],
            'cooling_capacity' => ['Công suất lạnh', 'measurement', 'kW', true, ['cooling capacity']],
            'heating_capacity' => ['Công suất sưởi', 'measurement', 'kW', true, ['heating capacity']],
            'power_input' => ['Công suất điện', 'measurement', 'W', true, ['power input', 'input power', 'điện năng tiêu thụ']],
            'cop' => ['COP', 'decimal', 'none', true, ['coefficient of performance']],
            'eer' => ['EER', 'decimal', 'none', true, ['energy efficiency ratio']],
            'inverter' => ['Công nghệ Inverter', 'boolean', 'none', true, ['inverter']],
            'refrigerant' => ['Môi chất lạnh', 'refrigerant', 'none', true, ['gas', 'loại gas', 'refrigerant gas']],
            'voltage' => ['Nguồn điện', 'voltage', 'V', true, ['điện áp', 'power supply', 'nguồn điện']],
            'phase' => ['Pha điện', 'text', 'none', true, ['phase', 'pha']],
            'airflow' => ['Lưu lượng gió', 'airflow', 'm³/h', true, ['air flow', 'airflow', 'lưu lượng']],
            'noise_level' => ['Độ ồn', 'noise', 'dB', true, ['noise', 'sound level', 'độ ồn']],
            'static_pressure' => ['Áp suất tĩnh', 'pressure', 'Pa', true, ['ESP', 'áp suất tĩnh', 'external static pressure', 'static pressure', 'áp suất ngoài']],
            'indoor_dimensions' => ['Kích thước dàn lạnh', 'dimension', 'mm', false, ['indoor dimension', 'indoor unit dimension', 'kích thước dàn lạnh']],
            'outdoor_dimensions' => ['Kích thước dàn nóng', 'dimension', 'mm', false, ['outdoor dimension', 'outdoor unit dimension', 'kích thước dàn nóng']],
            'indoor_weight' => ['Trọng lượng dàn lạnh', 'weight', 'kg', false, ['indoor weight', 'trọng lượng dàn lạnh']],
            'outdoor_weight' => ['Trọng lượng dàn nóng', 'weight', 'kg', false, ['outdoor weight', 'trọng lượng dàn nóng']],
            'weight' => ['Trọng lượng', 'weight', 'kg', false, ['weight', 'khối lượng', 'trọng lượng']],
            'pipe_size_liquid' => ['Ống lỏng', 'text', 'none', false, ['ống lỏng', 'liquid pipe', 'pipe liquid', 'liquid side']],
            'pipe_size_gas' => ['Ống gas', 'text', 'none', false, ['ống gas', 'gas pipe', 'pipe gas', 'gas side']],
            'max_pipe_length' => ['Chiều dài ống tối đa', 'measurement', 'm', false, ['max pipe length', 'pipe length']],
            'max_height_difference' => ['Chênh lệch độ cao tối đa', 'measurement', 'm', false, ['height difference', 'max height difference']],
            'operating_range' => ['Dải nhiệt độ hoạt động', 'text', '°C', false, ['operating range', 'operation range']],
            'origin' => ['Xuất xứ', 'text', 'none', false, ['origin', 'made in', 'xuất xứ']],
            'warranty' => ['Bảo hành', 'text', 'none', false, ['warranty', 'bảo hành']],
        ];

        [$label, $type, $unit, $useForAi, $aliases] = $definitions[$key] ?? [Str::headline($key), 'text', 'none', false, []];

        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'unit' => $unit,
            'required' => false,
            'visible_frontend' => true,
            'visible_compare' => true,
            'use_for_ai' => $useForAi,
            'aliases' => $aliases,
            'sort_order' => $sortOrder,
            'validation_pattern' => '',
            'notes' => '',
        ];
    }

    private function extractFields(array $schema): array
    {
        $fields = Arr::get($schema, 'fields', []);

        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];
        foreach ($fields as $key => $field) {
            if (is_string($key) && is_array($field)) {
                $field['key'] ??= $key;
            }

            if (is_array($field)) {
                $normalized[] = $field;
            }
        }

        foreach ((array) Arr::get($schema, 'allowed_fields', []) as $index => $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (collect($normalized)->contains(fn (array $field): bool => ($field['key'] ?? null) === $key)) {
                continue;
            }

            $normalized[] = $this->definition($key, ($index + 1) * 10);
        }

        $legacyAliases = (array) Arr::get($schema, 'field_aliases', []);
        if ($legacyAliases !== []) {
            foreach ($normalized as &$field) {
                foreach ($legacyAliases as $alias => $target) {
                    if (is_string($alias) && is_string($target) && ($field['key'] ?? null) === $target) {
                        $field['aliases'] = array_values(array_unique(array_merge((array) ($field['aliases'] ?? []), [$alias])));
                    }
                }
            }
            unset($field);
        }

        return $normalized;
    }

    private function formatValue(mixed $value, array $field): string
    {
        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        $value = trim((string) $value);
        $unit = (string) ($field['unit'] ?? 'none');

        if ($unit !== 'none' && $value !== '' && ! Str::contains(Str::lower($value), Str::lower($unit))) {
            return $value.' '.$unit;
        }

        return $value;
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function normalizeAlias(string $alias): string
    {
        return Str::of($alias)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
