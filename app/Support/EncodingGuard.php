<?php

namespace App\Support;

use RuntimeException;

class EncodingGuard
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    private const LEGACY_ENCODINGS = [
        'Windows-1258',
        'CP1258',
        'Windows-1252',
        'CP1252',
        'ISO-8859-1',
        'ISO-8859-15',
        'CP850',
        'CP437',
    ];

    public static function isValidUtf8(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8');
    }

    public static function stripBom(string $value): string
    {
        return str_starts_with($value, self::UTF8_BOM) ? substr($value, 3) : $value;
    }

    public static function hasBom(string $value): bool
    {
        return str_starts_with($value, self::UTF8_BOM);
    }

    public static function hasMojibake(string $value): bool
    {
        if (! self::isValidUtf8($value)) {
            return true;
        }

        return self::mojibakeScore($value) > 0;
    }

    public static function mojibakeScore(string $value): int
    {
        if (! self::isValidUtf8($value)) {
            return 1000;
        }

        $score = 0;
        foreach (self::mojibakePatterns() as $pattern) {
            $matches = [];
            $count = preg_match_all($pattern, $value, $matches);
            if (is_int($count) && $count > 0) {
                $score += $count;
            }
        }

        return $score;
    }

    public static function ensureUtf8(
        string $value,
        bool $autoFixMojibake = true,
        bool $rejectBroken = true,
        string $context = 'encoding'
    ): string {
        $value = self::stripBom($value);

        if (! self::isValidUtf8($value)) {
            $converted = self::convertLegacyBytes($value);
            if ($converted !== null) {
                $value = self::stripBom($converted);
            } elseif ($rejectBroken) {
                throw new RuntimeException("Broken UTF-8 detected in {$context}.");
            }
        }

        if (! self::isValidUtf8($value)) {
            if ($rejectBroken) {
                throw new RuntimeException("Broken UTF-8 detected in {$context}.");
            }

            return $value;
        }

        if ($autoFixMojibake && self::hasMojibake($value)) {
            return self::repairMojibake($value);
        }

        return $value;
    }

    public static function assertCleanUtf8Array(array $payload, string $context = 'payload'): void
    {
        array_walk_recursive($payload, function ($value) use ($context): void {
            if (! is_string($value)) {
                return;
            }

            self::assertCleanUtf8($value, $context);
        });
    }

    public static function assertCleanUtf8(string $value, string $context = 'value'): void
    {
        if (! self::isValidUtf8($value)) {
            throw new RuntimeException("Broken UTF-8 detected in {$context}.");
        }

        if (self::hasMojibake($value)) {
            throw new RuntimeException("Mojibake detected in {$context}.");
        }
    }

    public static function jsonEncode(mixed $value, int $flags = 0, int $depth = 512): string
    {
        $json = json_encode(
            $value,
            $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            $depth
        );

        return self::ensureUtf8($json, autoFixMojibake: false, rejectBroken: true, context: 'json');
    }

    public static function repairMojibake(string $value): string
    {
        if (! self::isValidUtf8($value)) {
            return $value;
        }

        $best = $value;
        $bestScore = self::mojibakeScore($value);

        foreach (['Windows-1252', 'ISO-8859-1', 'Windows-1258', 'CP850', 'CP437'] as $encoding) {
            $candidate = $value;

            for ($i = 0; $i < 3; $i++) {
                $candidate = self::reinterpret($candidate, $encoding);
                if (! is_string($candidate) || $candidate === '' || ! self::isValidUtf8($candidate)) {
                    break;
                }

                $score = self::mojibakeScore($candidate);
                if ($score < $bestScore) {
                    $best = $candidate;
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    private static function reinterpret(string $value, string $encoding): ?string
    {
        // "ChÃ­nh" (UTF-8 text mis-read as CP1252) => "Chính"
        try {
            $candidate = @mb_convert_encoding($value, $encoding, 'UTF-8');
            if (is_string($candidate) && $candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
                return $candidate;
            }
        } catch (\ValueError) {
            // Unsupported encoding in this runtime.
        }

        $bytes = @iconv('UTF-8', $encoding.'//IGNORE', $value);
        if (! is_string($bytes) || $bytes === '' || ! mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }

        return $bytes;
    }

    private static function convertLegacyBytes(string $value): ?string
    {
        foreach (self::LEGACY_ENCODINGS as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (! is_string($converted) || $converted === '') {
                continue;
            }

            $converted = self::stripBom($converted);
            if (self::isValidUtf8($converted)) {
                return $converted;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function mojibakePatterns(): array
    {
        return [
            '/\x{00C3}(?=[\x{0080}-\x{00BF}])/u',
            '/\x{00C2}(?=[\x{0080}-\x{00BF}])/u',
            '/\x{00C4}(?=[\x{0080}-\x{00BF}])/u',
            '/\x{00C6}(?=[\x{0080}-\x{00BF}])/u',
            '/\x{00E1}\x{00BA}|\x{00E1}\x{00BB}/u',
            '/\x{00E2}\x{20AC}/u',
            '/[\x{0080}-\x{009F}]/u',
            '/\x{FFFD}/u',
        ];
    }
}
