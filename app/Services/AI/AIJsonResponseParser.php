<?php

namespace App\Services\AI;

use RuntimeException;

class AIJsonResponseParser
{
    public function parse(string $text, bool $required): array
    {
        if (! $required) {
            return [];
        }

        $candidate = trim($text);
        if ($candidate === '') {
            throw new RuntimeException('invalid_json_response: empty AI response.');
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $candidate = $this->stripMarkdownFence($candidate);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $candidate = $this->extractJsonObject($candidate);
        if ($candidate !== null) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('invalid_json_response: '.json_last_error_msg());
    }

    private function stripMarkdownFence(string $text): string
    {
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', trim($text), $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }

    private function extractJsonObject(string $text): ?string
    {
        $startObject = strpos($text, '{');
        $startArray = strpos($text, '[');

        if ($startObject === false && $startArray === false) {
            return null;
        }

        $start = match (true) {
            $startObject === false => $startArray,
            $startArray === false => $startObject,
            default => min($startObject, $startArray),
        };
        $open = $text[$start];
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
