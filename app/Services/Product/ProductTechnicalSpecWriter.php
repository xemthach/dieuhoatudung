<?php

namespace App\Services\Product;

use App\Models\Product;
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
        if (! in_array($fieldKey, self::ALLOWED_FIELDS, true)) {
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
                    $item['source_pdf'] = $provenance['source_pdf'];
                    $item['source_sha256'] = $provenance['source_sha256'];
                    $item['source_page'] = $provenance['source_page'];
                    $item['source_row'] = $provenance['source_row'];
                    $item['source_column'] = $provenance['source_column'];
                    $item['source_section'] = 'TECHNICAL_APPENDIX';
                    $item['verification_status'] = 'verified_candidate';
                    $found = true;
                }
            }
            unset($item);
            if (!$found) $specs[] = ['key' => $fieldKey, 'value' => (string) $value, 'source_pdf' => $provenance['source_pdf'], 'source_sha256' => $provenance['source_sha256'], 'source_page' => $provenance['source_page'], 'source_row' => $provenance['source_row'], 'source_column' => $provenance['source_column'], 'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'];
            $updates['specs_json'] = $specs;

            // capacity_kw is a legacy decimal display mirror. Multi-value
            // appendix semantics remain in JSON and the resolver ignores the
            // mirror when JSON exists.
            if ($fieldKey === 'capacity_kw' && preg_match('/-?\d+(?:\.\d+)?/', (string) $value, $match)) {
                $updates['capacity_kw'] = (float) $match[0];
            }
            if ($fieldKey === 'power_input_kw') $updates['power_consumption'] = (string) $value;
        }

        $updates['technical_specs_source'] = 'catalog_verified_specs';

        $product->fill($updates);
        $product->save();

        return ['field' => $fieldKey, 'before' => $before, 'after' => $this->resolver->value($product->fresh(), $fieldKey), 'updates' => $updates, 'provenance' => $provenance];
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
}
