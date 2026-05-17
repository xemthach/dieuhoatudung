<?php

namespace App\Services\AI\Governance;

class AIContentQualityScorer
{
    public function score(array $payload, array $governanceResult = []): array
    {
        $score = 100;
        $warnings = [];

        $blocked = (array) ($governanceResult['blocked_claims'] ?? []);
        if ($blocked !== []) {
            $score -= min(60, count($blocked) * 15);
            $warnings[] = 'unsupported_claims:'.count($blocked);
        }

        foreach (['excerpt', 'content_html', 'seo_title', 'meta_description'] as $field) {
            if (blank($payload[$field] ?? null)) {
                $score -= 8;
                $warnings[] = 'missing_content_field:'.$field;
            }
        }

        if (! empty($payload['warnings'] ?? [])) {
            $score -= min(20, count((array) $payload['warnings']) * 2);
        }

        return [
            'score' => max(0, min(100, $score)),
            'status' => $score < 70 ? 'needs_review' : 'verified',
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
