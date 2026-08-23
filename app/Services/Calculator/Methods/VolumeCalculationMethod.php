<?php

namespace App\Services\Calculator\Methods;

use App\Enums\BtuCalculationMethod;

final class VolumeCalculationMethod
{
    /**
     * @param  array{label:string,w_per_m3:float|int}  $spaceFactor
     * @return array<string, mixed>
     */
    public function calculate(
        float $areaM2,
        float $heightM,
        array $spaceFactor,
        int $people,
        bool $sunlight,
        bool $heatEquipment,
        array $ruleSet,
    ): array {
        $volumeM3 = $areaM2 * $heightM;
        $wPerM3 = (float) $spaceFactor['w_per_m3'];
        $baseLoadW = $volumeM3 * $wPerM3;
        $wToBtu = (float) config('hvac.btu.w_to_btu', 3.412);
        $baseBtu = round($baseLoadW * $wToBtu);
        $btu = $baseBtu;
        $steps = [
            "Thể tích {$areaM2}m² × {$heightM}m = ".number_format($volumeM3, 2).' m³',
            number_format($volumeM3, 2)." m³ × {$wPerM3} W/m³ ({$spaceFactor['label']}) = ".number_format($baseLoadW).' W',
            number_format($baseLoadW)." W × {$wToBtu} = ".number_format($baseBtu).' BTU',
        ];
        $adjustments = [];
        if ($sunlight) {
            $before = $btu;
            $factor = (float) config('hvac.btu.sunlight_multiplier', 1.10);
            $btu = round($btu * $factor);
            $delta = $btu - $before;
            $steps[] = 'Có nắng trực tiếp (+'.round(($factor - 1) * 100, 2)."% = +{$delta} BTU)";
            $adjustments['sunlight'] = ['factor' => $factor, 'delta_btu' => $delta];
        }

        if ($heatEquipment) {
            $before = $btu;
            $factor = (float) config('hvac.btu.heat_equipment_multiplier', 1.10);
            $btu = round($btu * $factor);
            $delta = $btu - $before;
            $steps[] = 'Nhiều thiết bị sinh nhiệt (+'.round(($factor - 1) * 100, 2)."% = +{$delta} BTU)";
            $adjustments['heat_equipment'] = ['factor' => $factor, 'delta_btu' => $delta];
        }

        $peopleIncluded = (int) config('hvac.btu.people_included_in_base', 10);
        $extraPersonBtu = (int) config('hvac.btu.extra_person_btu', 400);
        if ($people > $peopleIncluded) {
            $extra = ($people - $peopleIncluded) * $extraPersonBtu;
            $btu += $extra;
            $steps[] = ($people - $peopleIncluded)." người vượt mức × {$extraPersonBtu} BTU = +".number_format($extra).' BTU';
            $adjustments['extra_people'] = ['count' => $people - $peopleIncluded, 'delta_btu' => $extra];
        }

        $calculatedBtu = (int) round($btu);

        return [
            'method' => BtuCalculationMethod::VOLUME->value,
            'method_label' => BtuCalculationMethod::VOLUME->label(),
            'rule_version' => (string) $ruleSet['version'],
            'methodology' => (string) $ruleSet['methodology'],
            'calculated_btu' => $calculatedBtu,
            'raw_btu' => $calculatedBtu,
            'raw_watts' => $baseLoadW,
            'base_load_w' => $baseLoadW,
            'volume_m3' => $volumeM3,
            'volume_w_per_m3' => $wPerM3,
            'cooling_w_per_m2' => null,
            'factor_value' => $wPerM3,
            'factor_unit' => 'W/m³',
            'adjustments' => $adjustments,
            'adjustment_breakdown' => $adjustments,
            'steps' => $steps,
            'people_included_in_base' => $peopleIncluded,
        ];
    }
}
