<?php

namespace App\Services\AI\Governance;

use App\Support\IssueList;

class AICodeLeakDetector
{
    private const PATTERNS = [
        'internal_class_name' => '/\b[A-Z][a-z]+(?:[A-Z][A-Za-z0-9]+)+(?:Service|Controller|Model|Repository|DTO|Provider|Gateway|Adapter)?\b/u',
        'internal_layer_name' => '/\b[A-Za-z0-9_]*(?:Service|Controller|Model|Repository|DTO|Provider|Gateway|Adapter)\b/u',
        'internal_function_name' => '/\b[a-zA-Z_][a-zA-Z0-9_]*\s*\(\s*\)/u',
        'internal_variable_path' => '/\b(?:product|post|blog|config|request|response|job|payload|input|output)\.[a-zA-Z_][a-zA-Z0-9_.]*\b/iu',
        'snake_case_identifier' => '/\b[a-z]+_[a-z0-9_]+(?:_[a-z0-9]+)*\b/u',
        'raw_variable' => '/(?:\{\{|\}\}|\$[a-zA-Z_][a-zA-Z0-9_]*)/u',
        'namespace' => '/\bApp\\\\[A-Za-z0-9_\\\\]+/u',
        'method_signature' => '/\b(public|private|protected)\s+function\s+[a-zA-Z_][a-zA-Z0-9_]*\s*\(/u',
    ];

    public function detect(string $text): array
    {
        $blocked = [];

        foreach (self::PATTERNS as $code => $pattern) {
            if ($code === 'snake_case_identifier' && ! $this->containsInternalSnakeCase($text)) {
                continue;
            }

            if (preg_match($pattern, $text)) {
                $blocked[] = $code;
            }
        }

        return [
            'status' => $blocked === [] ? 'verified' : 'blocked',
            'warnings' => array_map(fn (string $code): string => 'internal_language_detected:'.$code, IssueList::normalize($blocked)),
            'blocked_claims' => IssueList::normalize($blocked),
        ];
    }

    private function containsInternalSnakeCase(string $text): bool
    {
        return preg_match('/\b(?:capacity_btu|technical_specs_json|model_code|product_category_id|refrigerant_gas|noise_level|recommended_area|power_consumption)\b/u', $text) === 1;
    }
}
