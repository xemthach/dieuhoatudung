<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Writes only provenance-backed technical facts through one canonical path. */
class ProductTechnicalSpecWriter
{
    private const ALLOWED_FIELDS = [
        'technical_capacity_btu', 'marketing_capacity_btu', 'capacity_btu', 'capacity_kw',
        'power_input_kw', 'hp', 'cooling_type', 'inverter', 'voltage', 'refrigerant_gas',
        'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'weight',
        'recommended_area', 'cooling_capacity', 'heating_capacity', 'eer', 'cop',
        'static_pressure', 'pipe_size_liquid', 'pipe_size_gas', 'max_pipe_length',
        'max_height_difference', 'operating_range',
    ];

    public function __construct(private readonly ProductTechnicalFactResolver $resolver) {}

    public function write(Product $product, string $fieldKey, mixed $value, array $provenance): array
    {
        $schemaAllowsField = $product->category?->hasTechnicalSchema()
            && in_array($fieldKey, $product->category->technicalSchemaPermittedFields(), true);

        if (! in_array($fieldKey, self::ALLOWED_FIELDS, true) && ! $schemaAllowsField) {
            throw new InvalidArgumentException('Unknown technical field rejected: '.$fieldKey);
        }
        $this->assertProvenance($fieldKey, $provenance);
        $before = $this->resolver->value($product, $fieldKey);
        $updates = [];

        if (in_array($fieldKey, ['technical_capacity_btu', 'marketing_capacity_btu'], true)) {
            $updates[$fieldKey] = (int) $value;
            if ($fieldKey === 'technical_capacity_btu') {
                $updates['technical_capacity_status'] = 'verified_candidate';
            }
        } else {
            $specs = $product->specs_json ?? [];
            $found = false;
            foreach ($specs as &$item) {
                if (is_array($item) && ($item['key'] ?? null) === $fieldKey) {
                    $item['value'] = (string) $value;
                    $item['unit'] = $this->schemaUnit($product, $fieldKey);
                    $item['source_pdf'] = $provenance['source_pdf'];
                    $item['source_sha256'] = $provenance['source_sha256'];
                    $item['source_page'] = $provenance['source_page'];
                    $item['source_row'] = $provenance['source_row'];
                    $item['source_column'] = $provenance['source_column'];
                    $item['source_section'] = 'TECHNICAL_APPENDIX';
                    $item['extraction_method'] = $provenance['extraction_method'];
                    $item['source_native'] = true;
                    $item['derived'] = false;
                    $item['verification_status'] = 'verified_candidate';
                    $found = true;
                }
            }
            unset($item);
            if (!$found) $specs[] = ['key' => $fieldKey, 'value' => (string) $value, 'unit' => $this->schemaUnit($product, $fieldKey), 'source_pdf' => $provenance['source_pdf'], 'source_sha256' => $provenance['source_sha256'], 'source_page' => $provenance['source_page'], 'source_row' => $provenance['source_row'], 'source_column' => $provenance['source_column'], 'source_section' => 'TECHNICAL_APPENDIX', 'extraction_method' => $provenance['extraction_method'], 'source_native' => true, 'derived' => false, 'verification_status' => 'verified_candidate'];
            $updates['specs_json'] = $specs;

            // capacity_kw is a legacy decimal display mirror. Multi-value
            // appendix semantics remain in JSON and the resolver ignores the
            // mirror when JSON exists.
            if ($fieldKey === 'capacity_kw' && preg_match('/-?\d+(?:\.\d+)?/', (string) $value, $match)) {
                $updates['capacity_kw'] = (float) $match[0];
            }
            if ($fieldKey === 'power_input_kw') $updates['power_consumption'] = (string) $value;
            if ($fieldKey === 'hp') $updates['hp'] = (float) $value;
            if ($fieldKey === 'inverter') $updates['inverter'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            if ($fieldKey === 'cooling_type') $updates['cooling_type'] = $this->normalizeCoolingType((string) $value);
            if (in_array($fieldKey, ['voltage', 'refrigerant_gas', 'airflow', 'noise_level', 'indoor_dimensions', 'outdoor_dimensions', 'weight', 'recommended_area'], true)) {
                $updates[$fieldKey] = $value;
            }
        }

        $updates['technical_specs_source'] = 'catalog_verified_specs';

        $product->fill($updates);
        $product->save();

        return ['field' => $fieldKey, 'before' => $before, 'after' => $this->resolver->value($product->fresh(), $fieldKey), 'updates' => $updates, 'provenance' => $provenance];
    }

    /**
     * Prepare an explicit administrator override without rewriting source-native
     * technical evidence held in specs_json.
     *
     * @param array<string, mixed> $submitted
     * @return array<string, mixed>
     */
    public function manualOverrideAttributes(Product $product, array $submitted, ?string $reason): array
    {
        $fields = [
            'technical_capacity_btu', 'capacity_kw', 'hp', 'inverter', 'cooling_type',
            'voltage', 'refrigerant_gas', 'power_consumption', 'airflow', 'noise_level',
            'recommended_area', 'indoor_dimensions', 'outdoor_dimensions', 'weight',
            // Source-native commercial facts without dedicated Product columns.
            'phase', 'frequency',
        ];
        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $submitted)) continue;
            $value = $submitted[$field];
            if ($field === 'technical_capacity_btu' && filled($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw ValidationException::withMessages([$field => 'Công suất BTU kỹ thuật phải là số nguyên hợp lệ.']);
            }
            if (in_array($field, ['capacity_kw', 'hp'], true) && filled($value) && ! is_numeric($value)) {
                throw ValidationException::withMessages([$field => 'Giá trị phải là số hợp lệ.']);
            }
            $normalized = $this->normalizeManualValue($field, $value);
            $before = in_array($field, ['phase', 'frequency'], true)
                ? $this->resolver->value($product, $field)
                : $product->getAttribute($field);

            if (! $this->different($before, $normalized)) {
                continue;
            }

            if (in_array($field, ['phase', 'frequency'], true)) {
                $changes['specs_json'] = $this->withManualSpecOverride(
                    $changes['specs_json'] ?? $product->specs_json ?? [],
                    $field,
                    $normalized,
                );
                continue;
            }

            $changes[$field] = $normalized;
            if ($field === 'technical_capacity_btu') {
                $changes['technical_capacity_status'] = 'manual_override';
            }
        }

        if ($changes === []) return [];
        if (blank($reason)) {
            throw ValidationException::withMessages(['technical_specs_override_reason' => 'Nhập lý do khi ghi đè thông số kỹ thuật có nguồn catalog.']);
        }

        return $changes + [
            'technical_specs_source' => 'manual_override',
            'technical_specs_override_reason' => trim((string) $reason),
            'technical_specs_overridden_at' => now(),
        ];
    }

    public function scopedManualAttributes(Product $product, array $specs, array $values): array
    {
        $existing = collect($specs)->firstWhere('key', 'admin_override_fields');
        $scope = $existing['value'] ?? [];
        if (! $existing && $product->technical_specs_source === 'manual_override') {
            $scope = ['capacity_kw','power_input_kw','hp','inverter','cooling_type','voltage','refrigerant_gas',
                'airflow','noise_level','indoor_dimensions','outdoor_dimensions','weight','recommended_area'];
        }
        $fields = [];
        foreach ($values as $field => $value) {
            $key = $field === 'power_consumption' ? 'power_input_kw' : $field;
            $fields[] = $key;
            $specs = $this->withManualSpecOverride($specs, $key, $this->normalizeManualValue($field, $value));
        }
        $specs = array_filter($specs, static fn ($row) =>
            ! is_array($row) || ($row['key'] ?? '') !== 'admin_override_fields');
        $specs[] = ['key' => 'admin_override_fields', 'value' => array_values(array_unique([...$scope, ...$fields])),
            'source_section' => 'MANUAL_OVERRIDE', 'admin_only' => true, 'source_native' => false];
        return $specs;
    }

    /** Explicit clears mask source facts while retaining the original evidence rows. */
    public function manualClearAttributes(Product $product, array $specs, array $fields, string $reason): array
    {
        foreach ($fields as $field) {
            $key = $field === 'power_consumption' ? 'power_input_kw' : $field;
            $specs = $this->withManualSpecOverride($specs, $key, null);
        }
        return [
            'specs_json' => $specs, 'technical_specs_source' => 'manual_override',
            'technical_specs_override_reason' => $reason, 'technical_specs_overridden_at' => now(),
        ];
    }

    /** Only extension values can be public. Source and audit metadata stay in the stored row. */
    public static function publicExtraAllowed(string $key, array $row): bool
    {
        $key = Str::slug($key, '_');
        $standard = [
            'btu', 'capacity_btu', 'technical_capacity_btu', 'marketing_capacity_btu', 'cong_suat_btu',
            'capacity_kw', 'kw', 'cong_suat_kw', 'cong_suat_dinh_muc', 'hp', 'ma_luc', 'ma_luc_hp',
            'inverter', 'cooling_type', 'kieu_lam_lanh', 'voltage', 'dien_ap', 'phase', 'technical_phase',
            'frequency', 'technical_frequency', 'gas', 'refrigerant', 'refrigerant_gas', 'loai_gas',
            'power_consumption', 'power_input_kw', 'airflow', 'noise_level', 'recommended_area',
            'indoor_dimensions', 'outdoor_dimensions', 'weight', 'remote_model', 'panel_model',
        ];

        return ! in_array($key, $standard, true) && self::isPublicSpec($key, $row);
    }

    public static function isPublicSpec(string $key, array $row): bool
    {
        return $key !== '' && ! preg_match('/(^|_)(private|internal|admin|cost|supplier|source|audit|token|digest|evidence|verification|secret|password|wholesale)(_|$)/', $key)
            && ! ($row['private'] ?? false) && ! ($row['is_private'] ?? false) && ! ($row['admin_only'] ?? false)
            && filter_var($row['visible_frontend'] ?? true, FILTER_VALIDATE_BOOLEAN)
            && in_array($row['visibility'] ?? 'public', ['public', 'frontend'], true);
    }

    private static function editableManualRow(mixed $row): bool
    {
        if (! is_array($row) || ! self::publicExtraAllowed((string) ($row['key'] ?? ''), $row)) {
            return false;
        }
        $legacy = array_diff(array_keys($row), ['key', 'value', 'unit', 'source_native']) === []
            && ! ($row['source_native'] ?? false);
        return ($row['source_section'] ?? '') === 'MANUAL_EXTENDED' || $legacy;
    }

    public function manualRows(array $specs): array
    {
        return array_values(array_filter($specs, self::editableManualRow(...)));
    }

    public function mergeManualRows(array $stored, array $submitted, array $schemaAliases = []): array
    {
        $kept = [];
        $used = [];
        foreach ($stored as $key => $row) {
            if (self::editableManualRow($row)) {
                continue;
            }
            $name = is_array($row) ? ($row['key'] ?? $key) : $key;
            $normalized = Str::slug((string) $name, '_');
            $used[$schemaAliases[$normalized] ?? $normalized] = true;
            // Preserve legacy associative JSON and source-native evidence verbatim.
            if (is_string($key)) {
                $kept[$key] = $row;
            } else {
                $kept[] = $row;
            }
        }
        foreach ($submitted as $index => $row) {
            $name = trim((string) ($row['key'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $key = Str::slug($name, '_');
            $key = $schemaAliases[$key] ?? $key;
            if (! self::publicExtraAllowed($key, []) || isset($used[$key]) || $value === '') {
                throw ValidationException::withMessages(["data.manual_extended_specs.{$index}.key" => 'Nhập thông số công khai, có giá trị và không trùng trường chuẩn hoặc thông số đã lưu.']);
            }
            $used[$key] = true;
            $kept[] = ['key' => $name, 'value' => $value, 'source_section' => 'MANUAL_EXTENDED', 'source_native' => false];
        }
        return $kept;
    }

    private function schemaUnit(Product $product, string $fieldKey): string
    {
        foreach ($product->category?->technicalSchemaFieldDefinitions() ?? [] as $field) {
            if (($field['key'] ?? null) === $fieldKey) {
                return (string) ($field['unit'] ?? 'none');
            }
        }

        return 'none';
    }

    private function normalizeCoolingType(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match (true) {
            in_array($normalized, ['heat_pump', '2_chieu', '2 chiều', '2 chiều lạnh/sưởi'], true) => '2_chieu',
            in_array($normalized, ['cooling_only', '1_chieu', '1 chiều', '1 chiều lạnh'], true) => '1_chieu',
            default => $value,
        };
    }

    private function assertProvenance(string $fieldKey, array $provenance): void
    {
        foreach (['source_pdf', 'source_sha256', 'source_page', 'source_row', 'source_column', 'source_section', 'extraction_method'] as $key) {
            if (($provenance[$key] ?? '') === '') throw new InvalidArgumentException('Incomplete catalog provenance: ' . $key);
        }
        $section = $provenance['source_section'] ?? '';
        if ($fieldKey === 'marketing_capacity_btu' && $section !== 'PRODUCT_LIST') {
            throw new InvalidArgumentException('Marketing capacity requires PRODUCT_LIST provenance');
        }
        if ($fieldKey !== 'marketing_capacity_btu' && $section !== 'TECHNICAL_APPENDIX') {
            throw new InvalidArgumentException('Technical correction requires TECHNICAL_APPENDIX');
        }
    }

    private function normalizeManualValue(string $field, mixed $value): mixed
    {
        if ($value === '') return null;

        return match ($field) {
            'technical_capacity_btu' => filled($value) ? (int) $value : null,
            'capacity_kw', 'hp' => filled($value) ? (float) $value : null,
            'inverter' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_string($value) ? trim($value) : $value,
        };
    }

    private function different(mixed $before, mixed $after): bool
    {
        if ($before === null || $after === null) return $before !== $after;
        if (is_numeric($before) && is_numeric($after)) return (float) $before !== (float) $after;

        return $before !== $after;
    }

    /**
     * Source-native rows remain immutable audit evidence. A correction is a
     * separate current-value row, replacing only an earlier manual correction.
     */
    private function withManualSpecOverride(array $specs, string $field, mixed $value): array
    {
        $result = [];
        foreach ($specs as $index => $item) {
            if (is_array($item)
                && ($item['key'] ?? null) === $field
                && ($item['source_section'] ?? null) === 'MANUAL_OVERRIDE') {
                continue;
            }

            if (is_string($index)) {
                $result[$index] = $item;
            } else {
                $result[] = $item;
            }
        }

        $result[] = [
            'key' => $field,
            'value' => $value,
            'unit' => 'none',
            'source_section' => 'MANUAL_OVERRIDE',
            'source_native' => false,
            'derived' => false,
            'verification_status' => 'manual_override',
        ];

        return $result;
    }
}
