<?php

namespace App\Services\Product;

class ProductTechnicalFieldAliasRegistry
{
    private const MAP = [
        'btu' => 'technical_capacity_btu',
        'capacity_btu' => 'technical_capacity_btu',
        'capacity_kw' => 'capacity_kw',
        'power_input' => 'power_input_kw',
        'power_consumption' => 'power_input_kw',
        'refrigerant' => 'refrigerant_gas',
        'gas' => 'refrigerant_gas',
        'phase' => 'voltage',
        'noise' => 'noise_level',
    ];

    public function canonical(string $key): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', '_', $key)));

        return self::MAP[$normalized] ?? $normalized;
    }
}
