<?php

namespace App\Services\Catalog;

use App\Models\CatalogModel;
use App\Models\Product;
use App\Services\HVAC\HVACTechnicalNormalizer;
use App\Services\Product\ProductImportMapper;
use Illuminate\Support\Str;

class CatalogSpecAuditor
{
    public const STATUSES = [
        'correct',
        'catalog_source_missing',
        'ambiguous_catalog_match',
        'missing_catalog_specs',
        'product_missing_specs',
        'product_extra_specs',
        'mismatched_value',
        'wrong_unit',
        'wrong_format',
        'suspicious_ai_generated',
        'manual_override_detected',
        'import_mapping_error',
    ];

    public function __construct(
        private readonly CatalogProductMatcher $matcher,
        private readonly HVACTechnicalNormalizer $normalizer,
        private readonly ProductImportMapper $mapper,
    ) {}

    public function audit(Product $product): array
    {
        $product->loadMissing(['brand', 'category']);
        $match = $this->matcher->match($product);

        if ($match['status'] !== 'matched') {
            return $this->row($product, $match['status'], details: ['candidates' => $match['candidates']]);
        }

        /** @var CatalogModel $catalogModel */
        $catalogModel = $match['model'];
        $catalogFields = $this->catalogFields($catalogModel);

        if ($catalogFields === []) {
            return $this->row($product, 'missing_catalog_specs', $catalogModel);
        }

        $productSpecs = $this->productSpecs($product);
        $items = [];
        $status = 'correct';

        foreach ($catalogFields as $fieldKey => $catalogField) {
            if (! array_key_exists($fieldKey, $productSpecs)) {
                $items[] = $this->item('product_missing_specs', $fieldKey, null, $catalogField);
                $status = $this->dominantStatus($status, 'product_missing_specs');
                continue;
            }

            $productField = $productSpecs[$fieldKey];
            $comparison = $this->compareField($fieldKey, $productField, $catalogField);
            if ($comparison['status'] !== 'correct') {
                $status = $this->dominantStatus($status, $comparison['status']);
            }

            $items[] = $this->item($comparison['status'], $fieldKey, $productField, $catalogField, $comparison);
        }

        foreach ($productSpecs as $fieldKey => $productField) {
            if (array_key_exists($fieldKey, $catalogFields)) {
                continue;
            }

            $extraStatus = $this->isSuspiciousExtra($fieldKey, $productField['raw'])
                ? 'suspicious_ai_generated'
                : 'product_extra_specs';
            $status = $this->dominantStatus($status, $extraStatus);
            $items[] = $this->item($extraStatus, $fieldKey, $productField, null);
        }

        if ($product->technical_specs_overridden_at || filled($product->technical_specs_override_reason)) {
            $status = $this->dominantStatus($status, 'manual_override_detected');
            $items[] = $this->item('manual_override_detected', null, null, null, [
                'reason' => $product->technical_specs_override_reason,
                'overridden_at' => $product->technical_specs_overridden_at?->toIso8601String(),
            ]);
        }

        return $this->row($product, $status, $catalogModel, $items);
    }

    private function productSpecs(Product $product): array
    {
        $specs = [];

        foreach (ProductImportMapper::standardColumns() as $column) {
            $value = $product->getAttribute($column);
            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            $key = $this->normalizer->normalizeFieldKey($column);
            $specs[$key] = $this->normalizer->normalizeValue($value, $key, inferUnit: in_array($key, ['btu', 'capacity_kw', 'hp'], true));
            $specs[$key]['source'] = 'products.'.$column;
        }

        foreach ($this->mapper->flattenSpecs((array) $product->specs_json) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $fieldKey = $this->normalizer->normalizeFieldKey((string) $key);
            $specs[$fieldKey] = $this->normalizer->normalizeValue($value, $fieldKey, inferUnit: false);
            $specs[$fieldKey]['source'] = 'products.specs_json';
        }

        return $specs;
    }

    private function catalogFields(CatalogModel $catalogModel): array
    {
        $fields = [];

        foreach ($catalogModel->fields as $field) {
            $key = $this->normalizer->normalizeFieldKey($field->field_key);
            $unit = $field->unit ?: $this->normalizer->inferUnitFromField($key);
            $normalized = $field->normalized_value ?: $this->normalizer->normalizeValue($field->field_value, $key, $unit)['value'];

            $fields[$key] = [
                'raw' => (string) $field->field_value,
                'value' => $normalized,
                'unit' => $this->normalizer->normalizeUnit($unit),
                'has_explicit_unit' => $this->normalizer->technicalValueHasExplicitUnit((string) $field->field_value) || filled($field->unit),
                'source_page' => $field->source_page ?: $catalogModel->source_page,
                'source_text' => $field->source_text,
                'catalog_field_id' => $field->id,
            ];
        }

        return $fields;
    }

    private function compareField(string $fieldKey, array $productField, array $catalogField): array
    {
        if ($catalogField['unit'] !== '' && $productField['unit'] !== '' && $catalogField['unit'] !== $productField['unit']) {
            return ['status' => 'wrong_unit', 'reason' => 'unit differs'];
        }

        if ($catalogField['unit'] !== '' && ! $productField['has_explicit_unit'] && ! in_array($fieldKey, ['btu', 'capacity_kw', 'hp'], true)) {
            return ['status' => 'wrong_unit', 'reason' => 'product value does not carry catalog unit'];
        }

        if ($productField['value'] !== $catalogField['value']) {
            return ['status' => 'mismatched_value', 'reason' => 'normalized values differ'];
        }

        if ($this->hasWrongFormat($productField['raw'])) {
            return ['status' => 'wrong_format', 'reason' => 'spacing or separator format is unstable'];
        }

        return ['status' => 'correct'];
    }

    private function isSuspiciousExtra(string $fieldKey, string $value): bool
    {
        $haystack = Str::ascii(Str::lower($fieldKey.' '.$value));

        return Str::contains($haystack, [
            'dien tich de nghi',
            'recommended_area',
            'suitable_area',
            'phu hop',
            'sieu em',
            'tiet kiem dien toi da',
            'hieu suat vuot troi',
            'uoc tinh',
        ]);
    }

    private function hasWrongFormat(string $value): bool
    {
        return preg_match('/\s{2,}|\s\/\s/u', $value) === 1;
    }

    private function dominantStatus(string $current, string $candidate): string
    {
        $rank = [
            'correct' => 0,
            'product_extra_specs' => 10,
            'wrong_format' => 20,
            'suspicious_ai_generated' => 30,
            'product_missing_specs' => 40,
            'wrong_unit' => 50,
            'mismatched_value' => 60,
            'manual_override_detected' => 70,
            'import_mapping_error' => 80,
        ];

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    private function row(Product $product, string $status, ?CatalogModel $catalogModel = null, array $items = [], array $details = []): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'brand' => $product->brand?->name,
            'model' => $product->model_code,
            'sku' => $product->sku,
            'catalog_source_id' => $catalogModel?->catalog_source_id,
            'catalog_model_id' => $catalogModel?->id,
            'catalog_source' => $catalogModel?->source?->source_name,
            'catalog_model' => $catalogModel?->model,
            'validation_status' => $status,
            'risk_level' => $this->riskFor($status),
            'items' => $items,
            'details' => $details,
        ];
    }

    private function item(string $status, ?string $fieldKey, ?array $productField, ?array $catalogField, array $details = []): array
    {
        return [
            'validation_status' => $status,
            'field_key' => $fieldKey,
            'product_value' => $productField['raw'] ?? null,
            'catalog_value' => $catalogField['raw'] ?? null,
            'product_unit' => $productField['unit'] ?? null,
            'catalog_unit' => $catalogField['unit'] ?? null,
            'source_page' => $catalogField['source_page'] ?? null,
            'risk_level' => $this->riskFor($status),
            'details' => $details,
        ];
    }

    private function riskFor(string $status): string
    {
        return match ($status) {
            'mismatched_value', 'wrong_unit', 'manual_override_detected', 'import_mapping_error' => 'high',
            'product_missing_specs', 'suspicious_ai_generated', 'ambiguous_catalog_match' => 'medium',
            default => 'low',
        };
    }
}
