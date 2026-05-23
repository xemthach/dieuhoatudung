<?php

namespace App\Services\Encoding;

use App\Support\EncodingGuard;
use Normalizer;

class UTF8RepairService
{
    private const REINTERPRET_ENCODINGS = [
        'Windows-1252',
        'ISO-8859-1',
        'Windows-1258',
        'CP1252',
        'CP850',
        'CP437',
    ];

    private const MOJIBAKE_MARKERS = [
        'Ã',
        'Ä',
        'Æ',
        'áº',
        'á»',
        'â€',
        'â€™',
        'â€œ',
        'â€',
        'â€“',
        'â€”',
        'Ãƒ',
        'Ã‚',
        'Ã¢â‚¬',
        'Ã¡Âº',
        'Ã¡Â»',
        'Ã„',
        'Ã†',
        'Ã°Å¸',
        'Ã¯Â¿Â½',
    ];

    /**
     * @return array{
     *   original:string,
     *   repaired:string,
     *   confidence:float,
     *   classification:string,
     *   improved:bool,
     *   original_score:int,
     *   repaired_score:int
     * }
     */
    public function analyze(string $value): array
    {
        $original = $this->normalize($value);
        $originalScore = $this->score($original);

        $best = $original;
        $bestScore = $originalScore;

        foreach ($this->generateCandidates($original) as $candidate) {
            $candidate = $this->normalize($candidate);
            if (! EncodingGuard::isValidUtf8($candidate)) {
                continue;
            }

            $candidateScore = $this->score($candidate);
            if ($candidateScore < $bestScore) {
                $best = $candidate;
                $bestScore = $candidateScore;
            }
        }

        $improved = $best !== $original && $bestScore < $originalScore;
        $confidence = $this->confidence($original, $best, $originalScore, $bestScore, $improved);

        return [
            'original' => $original,
            'repaired' => $best,
            'confidence' => $confidence,
            'classification' => $this->classify($original, $best, $confidence, $improved),
            'improved' => $improved,
            'original_score' => $originalScore,
            'repaired_score' => $bestScore,
        ];
    }

    public function isLikelyMojibake(string $value): bool
    {
        $value = $this->normalize($value);
        if ($value === '') {
            return false;
        }

        if (! EncodingGuard::isValidUtf8($value)) {
            return true;
        }

        if ($this->markerCount($value) > 0) {
            return true;
        }

        return preg_match('/[\x{0080}-\x{009F}]/u', $value) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function generateCandidates(string $value): array
    {
        $candidates = [$value];

        foreach (self::REINTERPRET_ENCODINGS as $encoding) {
            $round = $value;

            for ($i = 0; $i < 3; $i++) {
                $round = $this->reinterpret($round, $encoding);
                if (! is_string($round) || $round === '') {
                    break;
                }

                if (! EncodingGuard::isValidUtf8($round)) {
                    break;
                }

                $candidates[] = $round;
            }
        }

        $guardFixed = EncodingGuard::repairMojibake($value);
        if ($guardFixed !== $value && EncodingGuard::isValidUtf8($guardFixed)) {
            $candidates[] = $guardFixed;
        }

        return array_values(array_unique(array_filter(
            $candidates,
            static fn ($v) => is_string($v) && $v !== ''
        )));
    }

    private function reinterpret(string $value, string $encoding): ?string
    {
        try {
            $candidate = @mb_convert_encoding($value, $encoding, 'UTF-8');
            if (is_string($candidate) && $candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
                return $candidate;
            }
        } catch (\ValueError) {
            // Unsupported encoding for mb_convert_encoding on this runtime.
        }

        $bytes = @iconv('UTF-8', $encoding.'//IGNORE', $value);
        if (! is_string($bytes) || $bytes === '' || ! mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }

        return $bytes;
    }

    private function normalize(string $value): string
    {
        $value = EncodingGuard::stripBom($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return $value;
    }

    private function score(string $value): int
    {
        $score = 0;

        if (! EncodingGuard::isValidUtf8($value)) {
            $score += 500;
        }

        $score += $this->markerCount($value) * 20;
        $score += substr_count($value, "\u{FFFD}") * 30;
        $score += $this->containsForbiddenArtifacts($value) ? 40 : 0;
        $score += preg_match('/[\x{0080}-\x{009F}]/u', $value) === 1 ? 24 : 0;

        // Reward proper Vietnamese diacritics but avoid over-optimistic scoring.
        $viCount = preg_match_all('/[\x{0102}\x{0103}\x{0110}\x{0111}\x{0128}\x{0129}\x{0168}\x{0169}\x{01A0}\x{01A1}\x{01AF}\x{01B0}\x{1EA0}-\x{1EF9}]/u', $value, $m);
        if (is_int($viCount) && $viCount > 0) {
            $score -= min(24, $viCount * 2);
        }

        return max(0, $score);
    }

    private function confidence(string $original, string $repaired, int $originalScore, int $repairedScore, bool $improved): float
    {
        if (! $improved || $repairedScore >= $originalScore) {
            return 0.0;
        }

        $base = max(10, $originalScore);
        $ratio = ($originalScore - $repairedScore) / $base;

        $confidence = min(1.0, $ratio);

        if ($this->markerCount($repaired) === 0) {
            $confidence += 0.15;
        }

        if ($this->containsForbiddenArtifacts($repaired)) {
            $confidence -= 0.4;
        }

        if (! $this->isReadableVietnamese($repaired)) {
            $confidence -= 0.3;
        }

        if ($this->hasVietnameseDiacritics($repaired)) {
            $confidence += 0.15;
        }

        if ($this->markerCount($repaired) >= $this->markerCount($original)) {
            $confidence -= 0.2;
        }

        if (! $this->hasVietnameseDiacritics($repaired)) {
            $confidence -= 0.15;
        }

        return max(0.0, min(1.0, round($confidence, 4)));
    }

    private function classify(string $original, string $repaired, float $confidence, bool $improved): string
    {
        if (! $this->isLikelyMojibake($original)) {
            return 'clean_utf8';
        }

        if (! $improved) {
            return 'permanently_corrupted';
        }

        if (! $this->isReadableVietnamese($repaired)) {
            return 'low_confidence';
        }

        if ($this->markerCount($original) > 0 && ! $this->hasVietnameseDiacritics($repaired)) {
            return 'low_confidence';
        }

        if ($confidence < 0.85) {
            return 'low_confidence';
        }

        return 'mojibake_recoverable';
    }

    private function isReadableVietnamese(string $text): bool
    {
        if (! EncodingGuard::isValidUtf8($text)) {
            return false;
        }

        if ($this->containsForbiddenArtifacts($text)) {
            return false;
        }

        if (preg_match('/([A-Z])\1{2,}[a-z]/u', $text) === 1) {
            return false;
        }

        if (substr_count($text, "\u{FFFD}") > 0) {
            return false;
        }

        return $this->markerCount($text) === 0;
    }

    private function hasVietnameseDiacritics(string $text): bool
    {
        return preg_match('/[\x{0102}\x{0103}\x{0110}\x{0111}\x{0128}\x{0129}\x{0168}\x{0169}\x{01A0}\x{01A1}\x{01AF}\x{01B0}\x{1EA0}-\x{1EF9}]/u', $text) === 1;
    }

    private function containsForbiddenArtifacts(string $text): bool
    {
        return preg_match('/[ÃßÃ˜Ã¸ÃžÃ¾ÃÃ°Å’Å“Æ’Â¤Â¥Â©Â®Âµ]/u', $text) === 1;
    }

    private function markerCount(string $text): int
    {
        $count = 0;
        foreach (self::MOJIBAKE_MARKERS as $marker) {
            $count += substr_count($text, $marker);
        }

        return $count;
    }
}
