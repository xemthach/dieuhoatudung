<?php

namespace App\Services\HVAC;

use Illuminate\Support\Str;

class HVACTechnicalNormalizer
{
    public function normalizeModel(?string $value): string
    {
        return $this->normalizeIdentity($value);
    }

    public function normalizeSku(?string $value): string
    {
        return $this->normalizeIdentity($value);
    }

    public function normalizeFieldKey(string $key): string
    {
        $key = Str::of(Str::ascii($key))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return match ($key) {
            'capacity_btu', 'cong_suat_btu', 'btu_h' => 'btu',
            'kw', 'capacity_kw', 'cong_suat_kw' => 'capacity_kw',
            'horsepower', 'ma_luc' => 'hp',
            'phase', 'dien_ap' => 'voltage',
            'refrigerant', 'gas', 'loai_gas' => 'refrigerant_gas',
            'noise', 'sound_level_db', 'do_on' => 'noise_level',
            'dimensions_indoor', 'kich_thuoc_dan_lanh' => 'indoor_dimensions',
            'dimensions_outdoor', 'kich_thuoc_dan_nong' => 'outdoor_dimensions',
            'trong_luong', 'khoi_luong' => 'weight',
            'suitable_area_m2', 'dien_tich_de_nghi', 'area' => 'recommended_area',
            default => $key,
        };
    }

    public function normalizeUnit(?string $unit): string
    {
        $unit = Str::of(Str::ascii((string) $unit))->lower()->replace(['m^2', 'm 2', 'm²'], 'm2')->trim()->toString();

        return match ($unit) {
            'btu/h', 'btuh' => 'btu',
            'kilowatt' => 'kw',
            'horsepower' => 'hp',
            'decibel' => 'db',
            default => $unit,
        };
    }

    public function inferUnitFromField(string $fieldKey): string
    {
        return match ($this->normalizeFieldKey($fieldKey)) {
            'btu' => 'btu',
            'capacity_kw' => 'kw',
            'hp' => 'hp',
            'noise_level' => 'db',
            'indoor_dimensions', 'outdoor_dimensions' => 'mm',
            'weight' => 'kg',
            'recommended_area' => 'm2',
            default => '',
        };
    }

    public function normalizeValue(mixed $value, string $fieldKey = '', ?string $unit = null, bool $inferUnit = true): array
    {
        $text = trim(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $detectedUnit = $this->extractUnit($text);
        $expectedUnit = $this->normalizeUnit($unit ?: ($inferUnit ? $this->inferUnitFromField($fieldKey) : ''));
        $actualUnit = $detectedUnit !== '' ? $detectedUnit : $expectedUnit;

        $normalizedText = Str::of(Str::ascii($text))->lower()->squish()->toString();

        if ($this->normalizeFieldKey($fieldKey) === 'voltage') {
            return [
                'value' => preg_replace('/\s+/', '', $normalizedText) ?: $normalizedText,
                'unit' => $detectedUnit ?: $expectedUnit,
                'has_explicit_unit' => $detectedUnit !== '',
                'raw' => $text,
            ];
        }

        if (preg_match_all('/\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?|\d+(?:[.,]\d+)?/u', $text, $matches) && $matches[0] !== []) {
            $numbers = array_map(fn (string $number): string => $this->formatNumber($this->normalizeNumber($number)), $matches[0]);
            $normalized = implode('x', $numbers);

            return [
                'value' => $actualUnit !== '' ? $normalized.'_'.$actualUnit : $normalized,
                'unit' => $actualUnit,
                'has_explicit_unit' => $detectedUnit !== '',
                'raw' => $text,
            ];
        }

        return [
            'value' => $normalizedText,
            'unit' => $detectedUnit ?: $expectedUnit,
            'has_explicit_unit' => $detectedUnit !== '',
            'raw' => $text,
        ];
    }

    public function extractUnit(string $value): string
    {
        if (! preg_match('/\b(BTU\/h|BTU|kW|HP|Pa|dB|mm|kg|W|A|V|m2|m\^2|m²)\b/iu', $value, $match)) {
            return '';
        }

        return $this->normalizeUnit($match[1]);
    }

    public function technicalValueHasExplicitUnit(string $value): bool
    {
        return $this->extractUnit($value) !== '';
    }

    private function normalizeIdentity(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))->upper()->replaceMatches('/[^A-Z0-9]+/', '')->toString();
    }

    private function normalizeNumber(string $number): float
    {
        $number = trim($number);

        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?$/', $number)) {
            $lastSeparator = max(strrpos($number, '.'), strrpos($number, ','));
            if ($lastSeparator !== false && strlen($number) - $lastSeparator - 1 === 3) {
                return (float) str_replace(['.', ','], '', $number);
            }
        }

        if (str_contains($number, ',') && ! str_contains($number, '.')) {
            $number = str_replace(',', '.', $number);
        } else {
            $number = str_replace(',', '', $number);
        }

        return (float) $number;
    }

    private function formatNumber(float $number): string
    {
        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
