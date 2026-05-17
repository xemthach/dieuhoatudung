<?php

namespace App\Services\AI\Governance;

use Illuminate\Support\Str;

class HVACUnitNormalizer
{
    private const TECHNICAL_UNITS = [
        'btu' => 'btu',
        'kw' => 'kw',
        'hp' => 'hp',
        'm2' => 'm2',
        'm²' => 'm2',
        'db' => 'db',
        'pa' => 'pa',
        'mm' => 'mm',
        'kg' => 'kg',
        'w' => 'w',
        'a' => 'a',
        'v' => 'v',
        'vnd' => 'vnd',
    ];

    public function normalizeClaim(string|float|int $number, string $unit): string
    {
        $unit = $this->normalizeUnit($unit);
        $number = $this->normalizeNumber((string) $number);

        return $this->formatNumber($number).'_'.$unit;
    }

    public function normalizeUnit(string $unit): string
    {
        $unit = Str::lower(trim($unit));
        $unit = str_replace(['㎡', 'm^2', 'm 2'], ['m2', 'm2', 'm2'], $unit);
        $unit = $unit === 'm²' ? 'm2' : $unit;
        $unit = $unit === 'đ' || $unit === 'dong' ? 'vnd' : $unit;

        return self::TECHNICAL_UNITS[$unit] ?? $unit;
    }

    public function normalizeNumber(string $number): float
    {
        $number = trim($number);
        if ($number === '') {
            return 0.0;
        }

        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?$/', $number)) {
            $lastSeparator = max(strrpos($number, '.'), strrpos($number, ','));
            $decimalLength = strlen($number) - $lastSeparator - 1;

            if ($decimalLength === 3) {
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

    public function extractTechnicalClaims(string $text, string $context = ''): array
    {
        $claims = [];
        $plain = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') {
            return [];
        }

        $this->extractEspClaims($plain, $claims);
        $this->extractDimensionClaims($plain, $context, $claims);
        $this->extractRangeClaims($plain, $context, $claims);
        $this->extractNumberUnitClaims($plain, $context, $claims);

        return $this->uniqueClaims($claims);
    }

    public function inferUnitFromContext(string $context): string
    {
        $haystack = Str::ascii(Str::lower($context));

        if (preg_match('/\b(btu|kw|hp|db|pa|mm|kg|vnd|m2)\b/u', $haystack, $match)) {
            return $this->normalizeUnit($match[1]);
        }

        if (preg_match('/_(btu|kw|hp|db|pa|mm|kg|vnd|m2)(?:\b|_)/u', $haystack, $match)) {
            return $this->normalizeUnit($match[1]);
        }

        return match (true) {
            Str::contains($haystack, ['capacity_btu', 'calculated_btu', 'recommended_btu', 'cong suat btu']) => 'btu',
            Str::contains($haystack, ['capacity_kw', 'cong suat kw']) => 'kw',
            Str::contains($haystack, ['hp', 'horsepower']) => 'hp',
            Str::contains($haystack, ['esp', 'external static pressure', 'static pressure', 'ap suat tinh', 'cot ap']) => 'pa',
            Str::contains($haystack, ['noise', 'do on', 'db']) => 'db',
            Str::contains($haystack, ['weight', 'trong luong', 'khoi luong', 'mass']) => 'kg',
            Str::contains($haystack, ['refrigerant_charge', 'factory_charge', 'charge_kg', 'gas nap', 'luong gas', 'nap san']) => 'kg',
            Str::contains($haystack, ['dimensions', 'kich thuoc', 'pipe', 'ong dong', 'duong kinh']) => 'mm',
            Str::contains($haystack, ['area', 'dien tich', 'm2']) => 'm2',
            Str::contains($haystack, ['regular_price', 'sale_price', 'gia']) => 'vnd',
            Str::contains($haystack, ['voltage', 'dien ap', 'phase']) || preg_match('/\b[13]\s*pha\b/u', $haystack) => 'v',
            Str::contains($haystack, ['power_consumption', 'cong suat tieu thu', 'consumption']) => 'w',
            Str::contains($haystack, ['rated_current', 'dong dien', 'current']) => 'a',
            default => '',
        };
    }

    public function isSupportedTechnicalUnit(string $unit): bool
    {
        return in_array($this->normalizeUnit($unit), array_values(self::TECHNICAL_UNITS), true);
    }

    private function extractEspClaims(string $plain, array &$claims): void
    {
        if (! preg_match_all('/\bESP\s*[:=]?\s*(\d{1,4}(?:[.,]\d+)?)\s*(Pa)?\b/iu', $plain, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $this->addClaim($claims, $match[0], $match[1], 'pa', 'technical_spec_reference');
        }
    }

    private function extractRangeClaims(string $plain, string $context, array &$claims): void
    {
        if (! preg_match_all('/(?<![A-Za-z0-9.,])(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(?:-|–|đến|to)\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(BTU|kW|HP|m2|m²|dB|Pa|mm|kg|W|A|VND|đ|dong)?\b/iu', $plain, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $unit = $match[3] ?? '';
            if ($unit === '') {
                $unit = $this->inferUnitFromContext($context);
            }
            if ($unit === '') {
                continue;
            }

            $unit = $this->normalizeUnit($unit);
            $min = min($this->normalizeNumber($match[1]), $this->normalizeNumber($match[2]));
            $max = max($this->normalizeNumber($match[1]), $this->normalizeNumber($match[2]));
            $claims[] = [
                'original' => trim($match[0]),
                'number' => null,
                'unit' => $unit,
                'normalized_value' => $this->formatNumber($min).'_'.$this->formatNumber($max).'_'.$unit,
                'min' => $min,
                'max' => $max,
                'classification' => 'technical_spec_reference',
            ];
        }
    }

    private function extractDimensionClaims(string $plain, string $context, array &$claims): void
    {
        $pattern = '/(?<![A-Za-z0-9.,])(\d{1,4}(?:[.,]\d+)?(?:\s*[x×*]\s*\d{1,4}(?:[.,]\d+)?){1,})\s*(mm)?\b/iu';
        $pattern = '/(?<![A-Za-z0-9.,])(\d{1,4}(?:[.,]\d+)?(?:\s*[x\x{00D7}*]\s*\d{1,4}(?:[.,]\d+)?){1,})\s*(mm)?\b/iu';
        if (! preg_match_all($pattern, $plain, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $unit = $match[2] ?? '';
            if ($unit === '') {
                $unit = $this->inferUnitFromContext($context);
            }

            if ($this->normalizeUnit($unit) !== 'mm') {
                continue;
            }

            if (! preg_match_all('/\d{1,4}(?:[.,]\d+)?/u', $match[1], $numbers)) {
                continue;
            }

            foreach ($numbers[0] as $number) {
                $this->addClaim($claims, $number.' mm', $number, 'mm', 'technical_spec_reference');
            }
        }
    }

    private function extractNumberUnitClaims(string $plain, string $context, array &$claims): void
    {
        if (! preg_match_all('/(?<![A-Za-z0-9.,])(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)\s*(BTU|kW|HP|m2|m²|dB|Pa|mm|kg|W|A|VND|đ|dong)\b/iu', $plain, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $this->extractImplicitUnitClaims($plain, $context, $claims);

            return;
        }

        foreach ($matches as $match) {
            $original = $match[0][0];
            $offset = $match[0][1];
            $unit = $this->normalizeUnit($match[2][0]);

            if ($unit === 'a' && $offset > 0 && preg_match('/[A-Za-z]$/u', substr($plain, max(0, $offset - 1), 1))) {
                continue;
            }

            $this->addClaim($claims, $original, $match[1][0], $unit, 'technical_spec_reference');
        }

        $this->extractImplicitUnitClaims($plain, $context, $claims);
    }

    private function extractImplicitUnitClaims(string $plain, string $context, array &$claims): void
    {
        $unit = $this->inferUnitFromContext($context);
        if ($unit === '') {
            return;
        }

        if (! preg_match_all('/(?<![A-Za-z0-9.,])(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?|\d+)(?![A-Za-z0-9.,])/u', $plain, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $this->addClaim($claims, $match[0], $match[1], $unit, 'technical_spec_reference');
        }
    }

    private function addClaim(array &$claims, string $original, string|float|int $number, string $unit, string $classification): void
    {
        $unit = $this->normalizeUnit($unit);
        if (! $this->isSupportedTechnicalUnit($unit)) {
            return;
        }

        $normalizedNumber = $this->normalizeNumber((string) $number);
        $claims[] = [
            'original' => trim($original),
            'number' => $normalizedNumber,
            'unit' => $unit,
            'normalized_value' => $this->formatNumber($normalizedNumber).'_'.$unit,
            'classification' => $classification,
        ];
    }

    private function uniqueClaims(array $claims): array
    {
        $seen = [];
        $unique = [];

        foreach ($claims as $claim) {
            $key = ($claim['normalized_value'] ?? '').'|'.($claim['original'] ?? '');
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $claim;
        }

        return $unique;
    }

    private function formatNumber(float $number): string
    {
        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
