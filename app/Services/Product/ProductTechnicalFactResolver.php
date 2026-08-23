<?php

namespace App\Services\Product;

use App\Models\Product;

/**
 * Deterministic read path for catalog technical facts.
 *
 * Multi-value appendix facts remain canonical in specs_json. The legacy
 * dedicated columns are used only when the canonical JSON key is absent.
 */
class ProductTechnicalFactResolver
{
    private const JSON_CANONICAL = [
        'capacity_btu',
        'capacity_kw',
        'power_input_kw',
    ];

    private const DEDICATED_CANONICAL = [
        'technical_capacity_btu' => 'technical_capacity_btu',
        'marketing_capacity_btu' => 'marketing_capacity_btu',
    ];

    private const LEGACY_DEDICATED = [
        'power_input_kw' => 'power_consumption',
        'voltage' => 'voltage',
        'refrigerant_gas' => 'refrigerant_gas',
        'airflow' => 'airflow',
        'noise_level' => 'noise_level',
        'indoor_dimensions' => 'indoor_dimensions',
        'outdoor_dimensions' => 'outdoor_dimensions',
        'weight' => 'weight',
        'hp' => 'hp',
        'inverter' => 'inverter',
        'cooling_type' => 'cooling_type',
        'recommended_area' => 'recommended_area',
    ];

    public function get(Product $product, string $fieldKey): ?array
    {
        if (isset(self::DEDICATED_CANONICAL[$fieldKey])) {
            $column = self::DEDICATED_CANONICAL[$fieldKey];
            $dedicated = $this->present($product->{$column}, $fieldKey, 'dedicated');
            if ($dedicated !== null) {
                return $dedicated;
            }
            if ($fieldKey === 'technical_capacity_btu') {
                $legacyCanonical = $this->specs($product)['capacity_btu'] ?? null;
                return $this->present($legacyCanonical, $fieldKey, 'specs_json_canonical');
            }
            return null;
        }

        $spec = $this->specs($product)[$fieldKey] ?? null;
        if ($spec !== null && $spec !== '') {
            return $this->present($spec, $fieldKey, 'specs_json');
        }

        if (isset(self::LEGACY_DEDICATED[$fieldKey])) {
            $column = self::LEGACY_DEDICATED[$fieldKey];

            return $this->present($product->{$column}, $fieldKey, 'legacy_dedicated');
        }

        return null;
    }

    public function value(Product $product, string $fieldKey): mixed
    {
        return $this->get($product, $fieldKey)['value'] ?? null;
    }

    public function has(Product $product, string $fieldKey): bool
    {
        return $this->get($product, $fieldKey) !== null;
    }

    public function getVerified(Product $product, string $fieldKey): ?array
    {
        $facts = $this->allVerified($product);

        return isset($facts[$fieldKey]) ? ['field' => $fieldKey, 'value' => $facts[$fieldKey], 'storage' => 'verified'] : null;
    }

    public function allVerified(Product $product): array
    {
        $facts = [];
        if (in_array((string) $product->technical_capacity_status, ['verified', 'verified_candidate', 'approved'], true) && $product->technical_capacity_btu !== null) {
            $facts['technical_capacity_btu'] = $product->technical_capacity_btu;
        }
        foreach ((array) ($product->specs_json ?? []) as $item) {
            if (! is_array($item) || ! isset($item['key']) || ! in_array((string) ($item['verification_status'] ?? ''), ['verified', 'verified_candidate', 'approved'], true)) continue;
            if (($item['source_section'] ?? '') !== 'TECHNICAL_APPENDIX') continue;
            $facts[(string) $item['key']] = $item['value'] ?? null;
        }

        return array_filter($facts, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Resolve unit-aware source facts without converting or rewriting legacy fields.
     * Only explicitly verified source-native facts are returned.
     */
    public function unitAwareFacts(Product $product): array
    {
        $facts = [];
        foreach ((array) ($product->specs_json ?? []) as $item) {
            if (! is_array($item) || ! isset($item['key'], $item['value'], $item['unit'])) continue;
            if (! in_array((string) ($item['verification_status'] ?? ''), ['verified', 'verified_candidate', 'approved'], true)) continue;
            if (($item['source_native'] ?? false) !== true || ($item['derived'] ?? false) === true) continue;
            $facts[(string) $item['key']] = [
                'field' => (string) $item['key'],
                'semantic_role' => (string) ($item['semantic_role'] ?? 'RATED'),
                'value' => $item['value'],
                'unit' => (string) $item['unit'],
                'source_native' => true,
                'derived' => false,
                'verification' => (string) $item['verification_status'],
                'provenance' => [
                    'source' => $item['source_catalogue'] ?? null,
                    'source_page' => $item['source_page'] ?? null,
                    'source_section' => $item['source_section'] ?? null,
                    'source_row' => $item['source_row'] ?? null,
                ],
            ];
        }

        return $facts;
    }

    public function getDisplay(Product $product, string $fieldKey): ?array
    {
        $resolved = $this->get($product, $fieldKey);
        if ($resolved !== null) {
            return $resolved;
        }

        // Transitional display-only fallback. This legacy field is never
        // exposed by getVerified() and is never used as technical capacity.
        if ($fieldKey === 'marketing_capacity_btu') {
            return $this->present($product->btu, $fieldKey, 'legacy_display');
        }
        if ($fieldKey === 'technical_capacity_btu') {
            return $this->present($product->btu, $fieldKey, 'legacy_display');
        }

        return null;
    }

    public function allForDisplay(Product $product): array
    {
        $facts = $this->specs($product);
        foreach (['technical_capacity_btu', 'marketing_capacity_btu'] as $key) {
            $value = $this->getDisplay($product, $key)['value'] ?? null;
            if ($value !== null && $value !== '') $facts[$key] = $value;
        }
        foreach (['capacity_kw', 'power_input_kw'] as $key) {
            $value = $this->value($product, $key);
            if ($value !== null && $value !== '') $facts[$key] = $value;
        }

        return $facts;
    }

    public function hasVerifiedSourcePage(Product $product): bool
    {
        foreach ((array) ($product->getAttribute('specs_json') ?? []) as $item) {
            if (is_array($item)
                && in_array((string) ($item['verification_status'] ?? ''), ['verified', 'verified_candidate', 'approved'], true)
                && ($item['source_section'] ?? '') === 'TECHNICAL_APPENDIX'
                && filled($item['source_page'] ?? null)) {
                return true;
            }
        }

        return filled($product->getAttribute('source_catalogue'))
            && filled($product->getAttribute('source_page'));
    }

    public function storageClass(string $fieldKey): string
    {
        if (isset(self::DEDICATED_CANONICAL[$fieldKey])) return 'DEDICATED_CANONICAL';
        if (in_array($fieldKey, self::JSON_CANONICAL, true)) return 'JSON_CANONICAL';
        if (isset(self::LEGACY_DEDICATED[$fieldKey])) return 'DUPLICATE_STORAGE';

        return 'JSON_CANONICAL';
    }

    public function specs(Product $product): array
    {
        $result = [];
        foreach ((array) ($product->specs_json ?? []) as $item) {
            if (is_array($item) && isset($item['key'])) {
                $result[(string) $item['key']] = $item['value'] ?? null;
            } elseif (is_array($item)) {
                foreach ($item as $key => $value) $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    private function present(mixed $value, string $fieldKey, string $storage): ?array
    {
        return $value === null || $value === '' ? null : ['field' => $fieldKey, 'value' => $value, 'storage' => $storage];
    }
}
