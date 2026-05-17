<?php

namespace App\Services\AI\Governance;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VerifiedFactRegistry
{
    public function __construct(
        private readonly HVACUnitNormalizer $normalizer,
    ) {}

    public function buildForProduct(Product $product, array $allowedFacts = []): array
    {
        $product->loadMissing(['brand', 'category']);

        $facts = [];
        foreach ($this->productFactDefinitions($product) as $key => $definition) {
            $this->addFact(
                $facts,
                $key,
                $definition['value'] ?? null,
                $definition['source'] ?? 'products',
                $definition['source_field'] ?? $key,
                $definition['label'] ?? $key,
                $definition['confidence'] ?? 1.0
            );
        }

        $flattenedSpecs = $this->flattenSpecs($product->specs_json ?? []);
        foreach ($flattenedSpecs as $index => $spec) {
            $key = 'product.technical_specs_json.'.$index;
            $value = trim(($spec['label'] ?: $spec['key']).' '.$spec['value']);
            $this->addFact(
                $facts,
                $key,
                $value,
                'technical_specs_json',
                'products.specs_json.'.$index,
                $spec['label'] ?: $spec['key'],
                1.0,
                [
                    'spec_key' => $spec['key'],
                    'unit' => $this->inferUnitFromSpec($spec, $flattenedSpecs, $index),
                    'source_page' => $spec['source_page'] ?? null,
                ]
            );
        }

        foreach ($allowedFacts as $key => $fact) {
            $this->addFact(
                $facts,
                (string) $key,
                $fact['value'] ?? null,
                $fact['source'] ?? 'allowed_facts',
                (string) $key,
                $fact['label'] ?? (string) $key,
                1.0,
                [
                    'aliases' => $fact['aliases'] ?? [],
                    'unit' => $fact['unit'] ?? null,
                ]
            );
        }

        return array_values($facts);
    }

    public function buildFromAllowedFacts(array $allowedFacts): array
    {
        $facts = [];

        foreach ($allowedFacts as $key => $fact) {
            $this->addFact(
                $facts,
                (string) $key,
                $fact['value'] ?? null,
                $fact['source'] ?? 'allowed_facts',
                (string) $key,
                $fact['label'] ?? (string) $key,
                1.0,
                [
                    'aliases' => $fact['aliases'] ?? [],
                    'unit' => $fact['unit'] ?? null,
                ]
            );
        }

        return array_values($facts);
    }

    public function indexByNormalizedValue(array $registry): array
    {
        $index = [];
        foreach ($registry as $fact) {
            foreach ((array) ($fact['normalized_values'] ?? []) as $value) {
                if ($value === '') {
                    continue;
                }

                $index[$value][] = $fact;
            }
        }

        return $index;
    }

    public function findMatchingFact(array $registry, array $claim): ?array
    {
        $unit = (string) ($claim['unit'] ?? '');
        $number = $claim['number'] ?? null;
        $normalized = (string) ($claim['normalized_value'] ?? '');

        foreach ($registry as $fact) {
            if (in_array($normalized, (array) ($fact['normalized_values'] ?? []), true)) {
                return $fact;
            }

            if (isset($claim['min'], $claim['max']) && $unit !== '') {
                $minValue = $this->normalizer->normalizeClaim((float) $claim['min'], $unit);
                $maxValue = $this->normalizer->normalizeClaim((float) $claim['max'], $unit);
                $values = (array) ($fact['normalized_values'] ?? []);

                if (in_array($minValue, $values, true) && in_array($maxValue, $values, true)) {
                    return $fact;
                }
            }

            foreach ((array) ($fact['normalized_ranges'] ?? []) as $range) {
                if ($unit !== ($range['unit'] ?? null)) {
                    continue;
                }

                $min = (float) ($range['min'] ?? 0);
                $max = (float) ($range['max'] ?? 0);
                $tolerance = max(0.01, max(abs($min), abs($max)) * 0.01);

                if (isset($claim['min'], $claim['max'])) {
                    $claimMin = (float) $claim['min'];
                    $claimMax = (float) $claim['max'];
                    if (abs($claimMin - $min) <= $tolerance && abs($claimMax - $max) <= $tolerance) {
                        return $fact;
                    }

                    continue;
                }

                if ($number === null) {
                    continue;
                }

                if ($number >= ($min - $tolerance) && $number <= ($max + $tolerance)) {
                    return $fact;
                }
            }
        }

        return null;
    }

    private function productFactDefinitions(Product $product): array
    {
        return [
            'product.id' => ['value' => $product->id, 'source_field' => 'products.id', 'label' => 'product id'],
            'product.name' => ['value' => $product->name, 'source_field' => 'products.name', 'label' => 'ten san pham'],
            'product.slug' => ['value' => $product->slug, 'source_field' => 'products.slug', 'label' => 'slug'],
            'product.model_code' => ['value' => $product->model_code, 'source_field' => 'products.model_code', 'label' => 'model'],
            'product.sku' => ['value' => $product->sku, 'source_field' => 'products.sku', 'label' => 'sku'],
            'product.brand' => ['value' => $product->brand?->name, 'source_field' => 'brands.name', 'label' => 'brand'],
            'product.category' => ['value' => $product->category?->name, 'source_field' => 'product_categories.name', 'label' => 'category'],
            'product.capacity_btu' => ['value' => $product->btu, 'source_field' => 'products.btu', 'label' => 'capacity btu'],
            'product.capacity_kw' => ['value' => $product->capacity_kw, 'source_field' => 'products.capacity_kw', 'label' => 'capacity kw'],
            'product.hp' => ['value' => $product->hp, 'source_field' => 'products.hp', 'label' => 'hp'],
            'product.cooling_type' => ['value' => $product->cooling_type, 'source_field' => 'products.cooling_type', 'label' => 'cooling type'],
            'product.inverter' => ['value' => $product->inverter, 'source_field' => 'products.inverter', 'label' => 'inverter'],
            'product.phase' => ['value' => $product->voltage, 'source_field' => 'products.voltage', 'label' => 'phase voltage'],
            'product.refrigerant' => ['value' => $product->refrigerant_gas, 'source_field' => 'products.refrigerant_gas', 'label' => 'refrigerant'],
            'product.airflow' => ['value' => $product->airflow, 'source_field' => 'products.airflow', 'label' => 'airflow'],
            'product.noise_level' => ['value' => $product->noise_level, 'source_field' => 'products.noise_level', 'label' => 'noise db'],
            'product.indoor_dimensions' => ['value' => $product->indoor_dimensions, 'source_field' => 'products.indoor_dimensions', 'label' => 'indoor dimensions'],
            'product.outdoor_dimensions' => ['value' => $product->outdoor_dimensions, 'source_field' => 'products.outdoor_dimensions', 'label' => 'outdoor dimensions'],
            'product.weight' => ['value' => $product->weight, 'source_field' => 'products.weight', 'label' => 'weight'],
            'product.recommended_area' => ['value' => $product->recommended_area, 'source_field' => 'products.recommended_area', 'label' => 'recommended area'],
            'product.warranty_info' => ['value' => strip_tags((string) $product->warranty_info), 'source_field' => 'products.warranty_info', 'label' => 'warranty policy'],
            'product.regular_price' => ['value' => $product->regular_price, 'source_field' => 'products.regular_price', 'label' => 'regular price'],
            'product.sale_price' => ['value' => $product->sale_price, 'source_field' => 'products.sale_price', 'label' => 'sale price'],
            'product.vat_enabled' => ['value' => (bool) $product->price_includes_vat, 'source_field' => 'products.price_includes_vat', 'label' => 'vat included'],
        ];
    }

    private function addFact(
        array &$facts,
        string $key,
        mixed $value,
        string $source,
        string $sourceField,
        string $label,
        float $confidence,
        array $meta = []
    ): void {
        if (blank($value) && $value !== 0 && $value !== false) {
            return;
        }

        $textValue = is_scalar($value)
            ? (string) $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $context = trim(implode(' ', array_filter([
            $key,
            $label,
            $sourceField,
            Arr::get($meta, 'spec_key'),
            Arr::get($meta, 'unit'),
            implode(' ', array_filter((array) Arr::get($meta, 'aliases', []), 'is_scalar')),
        ])));
        $claims = $this->normalizer->extractTechnicalClaims($textValue, $context);
        $normalizedValues = [];
        $normalizedRanges = [];

        foreach ($claims as $claim) {
            if (isset($claim['min'], $claim['max'])) {
                $normalizedRanges[] = [
                    'unit' => $claim['unit'],
                    'min' => $claim['min'],
                    'max' => $claim['max'],
                    'original' => $claim['original'],
                ];
            } else {
                $normalizedValues[] = $claim['normalized_value'];
            }
        }

        $normalizedKey = Str::of($key)->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_')->lower()->toString();
        $fact = [
            'fact_key' => $key,
            'normalized_key' => $normalizedKey,
            'original_value' => $value,
            'normalized_value' => $normalizedValues[0] ?? null,
            'normalized_values' => array_values(array_unique($normalizedValues)),
            'normalized_ranges' => $normalizedRanges,
            'source' => $source,
            'source_field' => $sourceField,
            'source_page' => $meta['source_page'] ?? null,
            'confidence' => $confidence,
            'label' => $label,
        ];

        if (isset($facts[$key])) {
            $existingHasClaims = ($facts[$key]['normalized_values'] ?? []) !== []
                || ($facts[$key]['normalized_ranges'] ?? []) !== [];
            $newHasClaims = $fact['normalized_values'] !== [] || $fact['normalized_ranges'] !== [];

            if ($existingHasClaims && ! $newHasClaims) {
                return;
            }

            $fact['normalized_values'] = array_values(array_unique(array_merge(
                (array) ($facts[$key]['normalized_values'] ?? []),
                $fact['normalized_values']
            )));
            $fact['normalized_ranges'] = array_values(array_merge(
                (array) ($facts[$key]['normalized_ranges'] ?? []),
                $fact['normalized_ranges']
            ));
            $fact['normalized_value'] = $fact['normalized_values'][0] ?? ($facts[$key]['normalized_value'] ?? null);
            $fact['source_page'] = $fact['source_page'] ?? ($facts[$key]['source_page'] ?? null);
        }

        $facts[$key] = $fact;
    }

    private function flattenSpecs(array $specs): array
    {
        return collect($specs)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                $key = (string) ($item['key'] ?? '');

                return [
                    'key' => $key,
                    'label' => (string) ($item['label'] ?? $key ?: ($item['name'] ?? '')),
                    'value' => (string) ($item['value'] ?? $item['text'] ?? ''),
                    'source_page' => $item['source_page'] ?? $item['page'] ?? null,
                ];
            })
            ->filter(fn ($item) => $item['label'] !== '' || $item['value'] !== '')
            ->values()
            ->all();
    }

    private function inferUnitFromSpec(array $spec, array $specs = [], int $index = 0): string
    {
        $haystack = $this->normalizedSpecText($spec);

        if (preg_match('/\b(btu|kw|hp|db|pa|mm|kg|vnd|m2)\b/u', $haystack, $match)) {
            return $match[1];
        }

        if (preg_match('/_(btu|kw|hp|db|pa|mm|kg|vnd|m2)(?:\b|_)/u', $haystack, $match)) {
            return $match[1];
        }

        $unit = $this->normalizer->inferUnitFromContext($haystack);
        if ($unit !== '') {
            return $unit;
        }

        $value = (string) ($spec['value'] ?? '');
        if (! preg_match('/\d+\s*(?:-|to|den|den)\s*\d+/u', Str::ascii(Str::lower($value)))) {
            return '';
        }

        foreach ([$index - 1, $index + 1] as $nearIndex) {
            if (! isset($specs[$nearIndex])) {
                continue;
            }

            $nearText = $this->normalizedSpecText($specs[$nearIndex]);
            $nearUnit = $this->normalizer->inferUnitFromContext($nearText);
            if ($nearUnit === '') {
                continue;
            }

            if ($this->sameSpecFamily($haystack, $nearText)) {
                return $nearUnit;
            }
        }

        return '';
    }

    private function normalizedSpecText(array $spec): string
    {
        return Str::ascii(Str::lower(trim(implode(' ', [
            $spec['key'] ?? '',
            $spec['label'] ?? '',
            $spec['value'] ?? '',
        ]))));
    }

    private function sameSpecFamily(string $current, string $nearby): bool
    {
        $families = [
            ['esp', 'external static pressure', 'static pressure', 'ap suat', 'cot ap', 'pressure', 'pa', 'range', 'pham vi', 'phm vi', 'phm_vi', 'dn_lnh', 'dieu chinh'],
            ['noise', 'do on', 'db'],
            ['pipe', 'ong dong', 'duong kinh', 'mm'],
            ['weight', 'trong luong', 'khoi luong', 'kg'],
            ['gas nap', 'luong gas', 'refrigerant charge', 'factory charge', 'kg'],
            ['area', 'dien tich', 'm2'],
        ];

        foreach ($families as $family) {
            $currentHit = Str::contains($current, $family);
            $nearbyHit = Str::contains($nearby, $family);
            if ($currentHit && $nearbyHit) {
                return true;
            }
        }

        return false;
    }
}
