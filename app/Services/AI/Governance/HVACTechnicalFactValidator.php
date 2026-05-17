<?php

namespace App\Services\AI\Governance;

use App\Support\IssueList;
use Illuminate\Support\Arr;

class HVACTechnicalFactValidator
{
    public function __construct(
        private readonly HVACUnitNormalizer $normalizer,
        private readonly VerifiedFactRegistry $registry,
    ) {}

    public function validateText(string $html, array $context): array
    {
        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $registry = (array) Arr::get($context, 'verified_fact_registry', []);
        $claims = $this->normalizer->extractTechnicalClaims($plain);

        $warnings = [];
        $blocked = [];
        $used = [];
        $classified = [];

        foreach ($claims as $claim) {
            $match = $this->registry->findMatchingFact($registry, $claim);
            if ($match !== null) {
                $used[] = $match['fact_key'] ?? $match['source_field'] ?? (string) ($claim['normalized_value'] ?? '');
                $classified[] = array_merge($claim, [
                    'status' => 'verified',
                    'source' => $match['source'] ?? null,
                    'source_field' => $match['source_field'] ?? null,
                ]);

                continue;
            }

            $claimText = (string) ($claim['original'] ?? $claim['normalized_value'] ?? '');
            $warnings[] = 'unverified_numeric_claim:'.$claimText;
            $blocked[] = 'unverified_numeric_claim:'.$claimText;
            $classified[] = array_merge($claim, ['status' => 'unverified']);
        }

        return [
            'status' => $blocked === [] ? 'verified' : 'blocked',
            'warnings' => IssueList::normalize($warnings),
            'blocked_claims' => IssueList::normalize($blocked),
            'used_facts' => IssueList::normalize($used),
            'technical_claims' => $classified,
        ];
    }
}
