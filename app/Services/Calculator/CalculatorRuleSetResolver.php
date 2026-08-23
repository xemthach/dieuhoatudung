<?php

namespace App\Services\Calculator;

use App\Enums\BtuCalculationMethod;
use InvalidArgumentException;

final class CalculatorRuleSetResolver
{
    /** @return array<string, mixed> */
    public function resolve(BtuCalculationMethod|string $method, ?string $ruleVersion = null): array
    {
        $method = is_string($method) ? BtuCalculationMethod::tryFrom($method) : $method;
        if (! $method) {
            throw new InvalidArgumentException('Unsupported calculator method.');
        }

        $version = $ruleVersion ?: $this->activeVersion($method);
        $rule = config("hvac_calculator_rules.rules.{$version}");

        if (! is_array($rule) || ($rule['method'] ?? null) !== $method->value) {
            throw new InvalidArgumentException("Rule [{$version}] is not valid for method [{$method->value}].");
        }

        $areaVersion = $method === BtuCalculationMethod::AREA
            ? $version
            : (string) ($rule['derived_from'] ?? '');
        $areaRule = config("hvac_calculator_rules.rules.{$areaVersion}");
        $profile = is_array($areaRule) ? ($areaRule['factor_profile'] ?? null) : null;

        if (! is_string($profile) || $profile === '') {
            throw new InvalidArgumentException("Rule [{$version}] has no valid area factor profile.");
        }

        $referenceHeight = (float) ($rule['reference_height_m'] ?? config('hvac.btu.baseline_ceiling_m', 3.0));
        $spaceTypes = collect(config('hvac_calculator_rules.space_types', []))
            ->map(function (array $space, string $key) use ($method, $profile, $referenceHeight): array {
                if (! array_key_exists($profile, $space)) {
                    throw new InvalidArgumentException("Space type [{$key}] has no factor profile [{$profile}].");
                }

                $areaFactor = $space[$profile];
                $factor = $method === BtuCalculationMethod::VOLUME
                    ? (float) $areaFactor / $referenceHeight
                    : $areaFactor;

                return [
                    'key' => $key,
                    'label' => (string) $space['label_vi'],
                    'label_vi' => (string) $space['label_vi'],
                    'label_en' => (string) $space['label_en'],
                    'group' => (string) $space['group'],
                    'factor' => $factor,
                    'factor_unit' => $method === BtuCalculationMethod::VOLUME ? 'W/m³' : 'W/m²',
                    'w_per_m2' => $areaFactor,
                    'w_per_m3' => $areaFactor / $referenceHeight,
                    'reference_btu_m2' => (float) ($space['reference_btu_m2'] ?? 0),
                    'confidence' => (string) ($space['confidence'] ?? 'UNKNOWN'),
                    'source' => (string) ($space['source'] ?? 'UNSPECIFIED'),
                    'activation' => (string) ($space['activation'] ?? 'UNKNOWN'),
                    'enabled' => true,
                ];
            })
            ->all();

        return $rule + [
            'version' => $version,
            'method' => $method->value,
            'area_rule_version' => $areaVersion,
            'space_types' => $spaceTypes,
            'reference_height_m' => $referenceHeight,
        ];
    }

    public function activeVersion(BtuCalculationMethod|string $method): string
    {
        $method = is_string($method) ? BtuCalculationMethod::tryFrom($method) : $method;
        if (! $method) {
            throw new InvalidArgumentException('Unsupported calculator method.');
        }

        $version = config("hvac_calculator_rules.active.{$method->value}");
        if (! is_string($version) || $version === '') {
            throw new InvalidArgumentException("No active calculator rule for [{$method->value}].");
        }

        return $version;
    }

    /** @return array<string, array<string, mixed>> */
    public function governance(): array
    {
        return collect(BtuCalculationMethod::cases())
            ->mapWithKeys(fn (BtuCalculationMethod $method): array => [
                $method->value => $this->resolve($method),
            ])
            ->all();
    }
}
