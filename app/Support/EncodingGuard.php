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
    ];

    private const MOJIBAKE_PATTERN = '/�|ï¿½|Ãƒ.|Ã„.|Ã†.|Ã¡Âº|Ã¡Â»|Ã¢â‚¬|Ã¢â‚¬â„¢|Ã¢â‚¬Å“|Ã¢â‚¬Â|Ã¢â‚¬â€œ|Ã¢â‚¬â€|Ã¡|Ã¢|Ã´|Ãª|Ã©|Ã¨|Ã³|Ã²|Ãº|Ã¹|Ãí|Ãì|Ã£|Ãµ|áº|á»|Ä|Ä‘|Æ°|Æ¡|Â²|Â°|Â |â€|â€™|â€œ|â€|â€“|â€”/u';

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
        return self::isValidUtf8($value) && preg_match(self::MOJIBAKE_PATTERN, $value) === 1;
    }

    public static function mojibakeScore(string $value): int
    {
        if (! self::isValidUtf8($value)) {
            return 1000;
        }

        preg_match_all(self::MOJIBAKE_PATTERN, $value, $matches);

        return count($matches[0]);
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

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $bytes = @mb_convert_encoding($value, $encoding, 'UTF-8');
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $fixed = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
            if (! is_string($fixed) || ! self::isValidUtf8($fixed)) {
                continue;
            }

            $score = self::mojibakeScore($fixed);
            if ($score < $bestScore) {
                $best = $fixed;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function convertLegacyBytes(string $value): ?string
    {
        foreach (self::LEGACY_ENCODINGS as $encoding) {
            $converted = @iconv($encoding, 'UTF-8', $value);
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
}
