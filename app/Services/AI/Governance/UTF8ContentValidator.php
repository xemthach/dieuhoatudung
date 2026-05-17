<?php

namespace App\Services\AI\Governance;

use App\Support\EncodingGuard;
use RuntimeException;

class UTF8ContentValidator
{
    public function cleanString(string $value, string $context = 'ai_content'): string
    {
        return EncodingGuard::ensureUtf8($value, autoFixMojibake: true, rejectBroken: true, context: $context);
    }

    public function validatePayload(array &$payload, string $context = 'ai_payload'): void
    {
        array_walk_recursive($payload, function (&$value, $key) use ($context): void {
            if (! is_string($value)) {
                return;
            }

            $value = $this->cleanString($value, $context.'.'.$key);
        });
    }

    public function assertClean(string $value, string $context = 'ai_content'): void
    {
        if (! EncodingGuard::isValidUtf8($value)) {
            throw new RuntimeException("Broken UTF-8 detected in {$context}.");
        }

        if (EncodingGuard::hasMojibake($value) || preg_match('/(?:\x{00C3}.|\x{00C4}.|\x{00E1}\x{00BA}|\x{00E1}\x{00BB}|\x{FFFD})/u', $value) === 1) {
            throw new RuntimeException("Mojibake detected in {$context}.");
        }
    }
}
