<?php

namespace App\Services\AI\Governance;

use App\Support\IssueList;
use Illuminate\Support\Arr;

/**
 * Validates HVAC technical specifications claims only.
 *
 * Handles: BTU, kW, HP, Pa, dB, mm, kg, W, A, m², gas type,
 * voltage, dimensions, airflow, static pressure, pipe diameter,
 * pipe length, weight.
 *
 * Uses ClaimClassifier to distinguish between:
 * - Product-specific technical claims (must be verified against specs)
 * - Educational/general statements (allowed without verification)
 * - Formula/calculation claims (require verified BTU service)
 *
 * Does NOT handle: VAT, warranty, free installation, admin-configured claims.
 * Those are handled by BusinessClaimValidator.
 */
class HVACTechnicalFactValidator
{
    public function __construct(
        private readonly HVACUnitNormalizer $normalizer,
        private readonly VerifiedFactRegistry $registry,
        private readonly ClaimClassifier $classifier,
    ) {}

    /**
     * Validate technical claims in text content.
     *
     * Instead of blocking on every unverified number, this validator:
     * 1. Extracts claims from text
     * 2. Classifies each claim by sentence context
     * 3. Only verifies product_technical_claim types against specs
     * 4. Allows educational_statement types without verification
     * 5. Flags formula_calculation_claim types for rewrite if no BTU service
     *
     * @return array{status: string, warnings: array, blocked_claims: array,
     *               used_facts: array, technical_claims: array,
     *               rewritable_claims: array, log: array}
     */
    public function validateText(string $html, array $context): array
    {
        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $registry = (array) Arr::get($context, 'verified_fact_registry', []);
        $claims = $this->normalizer->extractTechnicalClaims($plain);
        $btuServiceAvailable = (bool) Arr::get($context, 'calculation_rules.specific_btu_result_allowed', false);

        $warnings = [];
        $blocked = [];
        $used = [];
        $classified = [];
        $rewritable = [];
        $log = [];

        foreach ($claims as $claim) {
            $claimText = (string) ($claim['original'] ?? $claim['normalized_value'] ?? '');

            // Get sentence context for this claim
            $sentenceContext = $this->classifier->extractSentenceContext($plain, $claimText);

            // Check if this is a unit-definition number (e.g., "1 BTU", "1m2")
            if ($this->classifier->isUnitDefinitionNumber($claimText, $sentenceContext)) {
                $classified[] = array_merge($claim, [
                    'status' => 'ignored',
                    'classification' => 'unit_definition',
                    'reason' => 'Unit definition/educational number',
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => 'unit_definition',
                    'action' => 'ignored_not_product_spec',
                ];

                continue;
            }

            // Classify the claim based on sentence context
            $classification = $this->classifier->classify($claimText, $sentenceContext);

            // Business claims are handled by BusinessClaimValidator, skip here
            if ($classification === 'business_config_claim') {
                $classified[] = array_merge($claim, [
                    'status' => 'skipped',
                    'classification' => $classification,
                    'reason' => 'Handled by BusinessClaimValidator',
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => $classification,
                    'action' => 'delegated_to_business_validator',
                ];

                continue;
            }

            // Educational statements don't need product verification
            if ($classification === 'educational_statement') {
                $classified[] = array_merge($claim, [
                    'status' => 'allowed',
                    'classification' => $classification,
                    'reason' => 'General educational HVAC statement',
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => 'educational_statement',
                    'action' => 'ignored_not_product_spec',
                ];

                continue;
            }

            // Formula/calculation claims require verified BTU service
            if ($classification === 'formula_calculation_claim') {
                if ($btuServiceAvailable) {
                    // Verify against BTU calculation results in allowed facts
                    $match = $this->registry->findMatchingFact($registry, $claim);
                    if ($match !== null) {
                        $used[] = $match['fact_key'] ?? $match['source_field'] ?? $claimText;
                        $classified[] = array_merge($claim, [
                            'status' => 'verified',
                            'classification' => $classification,
                            'source' => $match['source'] ?? 'verified_hvac_calculation',
                        ]);
                        $log[] = [
                            'validator' => 'TechnicalFactValidator',
                            'claim' => $claimText,
                            'classification' => 'formula_calculation_claim',
                            'source' => $match['source'] ?? 'verified_hvac_calculation',
                            'action' => 'verified_against_calculation',
                        ];

                        continue;
                    }
                }

                // No verified calculation - mark for rewrite, NOT block
                $rewritable[] = [
                    'claim' => $claimText,
                    'classification' => $classification,
                    'reason' => 'Formula/calculation without verified BTU service',
                    'rewrite_to' => 'Công suất điều hòa nên được xác định dựa trên diện tích, chiều cao trần, số người, hướng nắng và tải nhiệt thực tế.',
                ];
                $warnings[] = 'unverified_formula_claim:'.$claimText;
                $classified[] = array_merge($claim, [
                    'status' => 'rewritable',
                    'classification' => $classification,
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => 'formula_calculation_claim',
                    'source' => 'no_verified_btu_service',
                    'action' => 'marked_for_rewrite',
                ];

                continue;
            }

            if ($classification === 'generic_capacity_mention') {
                $classified[] = array_merge($claim, [
                    'status' => 'ignored',
                    'classification' => $classification,
                    'reason' => 'Capacity mention without technical wording is not a verified technical fact.',
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => $classification,
                    'action' => 'ignored_not_technical_authority',
                ];
                continue;
            }

            if (in_array($classification, ['marketing_capacity_claim', 'technical_capacity_claim', 'technical_capacity_range_claim', 'ambiguous_capacity_claim'], true)
                && ($claim['unit'] ?? null) === 'btu') {
                $claimValue = (float) ($claim['number'] ?? 0);
                $marketingValue = $this->capacityValue($context, 'marketing_capacity_btu');
                $technicalValue = $this->capacityValue($context, 'technical_capacity_btu');

                if ($classification === 'marketing_capacity_claim') {
                    if ($marketingValue !== null && $claimValue === $marketingValue) {
                        $used[] = 'product.marketing_capacity_btu';
                        $classified[] = array_merge($claim, [
                            'status' => 'verified',
                            'classification' => $classification,
                            'source' => 'ProductTechnicalFactResolver::marketing_capacity_btu',
                        ]);
                        $log[] = [
                            'validator' => 'TechnicalFactValidator',
                            'claim' => $claimText,
                            'classification' => $classification,
                            'action' => 'verified_marketing_context',
                        ];
                        continue;
                    }

                    $warnings[] = 'unverified_marketing_capacity_claim:'.$claimText;
                    $rewritable[] = ['claim' => $claimText, 'classification' => $classification, 'reason' => 'Marketing capacity does not match verified commercial grouping.'];
                    $classified[] = array_merge($claim, ['status' => 'unverified', 'classification' => $classification]);
                    continue;
                }

                if ($classification === 'technical_capacity_claim') {
                    if ($technicalValue !== null && $claimValue === $technicalValue) {
                        $used[] = 'product.rated_cooling_capacity_btu';
                        $classified[] = array_merge($claim, [
                            'status' => 'verified',
                            'classification' => $classification,
                            'source' => 'ProductTechnicalFactResolver::technical_capacity_btu',
                        ]);
                        $log[] = [
                            'validator' => 'TechnicalFactValidator',
                            'claim' => $claimText,
                            'classification' => $classification,
                            'action' => 'verified_against_technical_capacity',
                        ];
                        continue;
                    }

                    $blocked[] = 'contradicted_technical_capacity:'.$claimText;
                    $classified[] = array_merge($claim, [
                        'status' => 'contradicted',
                        'classification' => $classification,
                        'source' => 'ProductTechnicalFactResolver::technical_capacity_btu',
                        'verified_value' => $technicalValue,
                    ]);
                    $log[] = [
                        'validator' => 'TechnicalFactValidator',
                        'claim' => $claimText,
                        'classification' => $classification,
                        'action' => 'blocked_contradicted_technical_capacity',
                        'verified_value' => $technicalValue,
                    ];
                    continue;
                }

                if ($classification === 'technical_capacity_range_claim') {
                    $rangeClaims = [$claim];
                    if (isset($claim['min'], $claim['max'])) {
                        $rangeClaims = [];
                        foreach ([(float) $claim['min'], (float) $claim['max']] as $bound) {
                            $boundClaim = $claim;
                            unset($boundClaim['min'], $boundClaim['max']);
                            $boundClaim['number'] = $bound;
                            $boundClaim['normalized_value'] = $this->normalizer->normalizeClaim($bound, (string) $claim['unit']);
                            $rangeClaims[] = $boundClaim;
                        }
                    }
                    $matches = array_map(fn (array $rangeClaim): ?array => $this->registry->findMatchingFact($registry, $rangeClaim), $rangeClaims);
                    if (! in_array(null, $matches, true)) {
                        foreach ($matches as $match) {
                            $used[] = $match['fact_key'] ?? $match['source_field'] ?? $claimText;
                        }
                        $classified[] = array_merge($claim, [
                            'status' => 'verified',
                            'classification' => $classification,
                            'source' => $matches[0]['source'] ?? 'verified_fact_registry',
                        ]);
                        $log[] = [
                            'validator' => 'TechnicalFactValidator',
                            'claim' => $claimText,
                            'classification' => $classification,
                            'action' => 'verified_capacity_range_bound',
                            'source' => array_values(array_unique(array_filter(array_column($matches, 'source_field')))),
                        ];
                        continue;
                    }

                    $blocked[] = 'contradicted_technical_capacity_range:'.$claimText;
                    $classified[] = array_merge($claim, ['status' => 'contradicted', 'classification' => $classification]);
                    continue;
                }

                if ($technicalValue !== null
                    && $claimValue === $technicalValue) {
                    $used[] = 'product.rated_cooling_capacity_btu';
                    $classified[] = array_merge($claim, [
                        'status' => 'verified',
                        'classification' => 'technical_capacity_claim',
                        'source' => 'ProductTechnicalFactResolver::technical_capacity_btu',
                        'original_classification' => $classification,
                    ]);
                    $log[] = [
                        'validator' => 'TechnicalFactValidator',
                        'claim' => $claimText,
                        'classification' => $classification,
                        'action' => 'resolved_against_unique_verified_technical_capacity',
                    ];
                    continue;
                }

                $blocked[] = 'ambiguous_capacity_claim:'.$claimText;
                $rewritable[] = ['claim' => $claimText, 'classification' => $classification, 'reason' => 'Capacity wording does not identify commercial or technical semantics.'];
                $classified[] = array_merge($claim, ['status' => 'ambiguous', 'classification' => $classification]);
                continue;
            }

            // Product technical claims must be verified against specs
            $match = $this->registry->findMatchingFact($registry, $claim);
            if ($match !== null) {
                $used[] = $match['fact_key'] ?? $match['source_field'] ?? $claimText;
                $classified[] = array_merge($claim, [
                    'status' => 'verified',
                    'classification' => $classification,
                    'source' => $match['source'] ?? null,
                    'source_field' => $match['source_field'] ?? null,
                ]);
                $log[] = [
                    'validator' => 'TechnicalFactValidator',
                    'claim' => $claimText,
                    'classification' => 'product_technical_claim',
                    'source' => $match['source_field'] ?? 'verified_fact_registry',
                    'action' => 'verified_against_specs',
                ];

                continue;
            }

            // Unverified product technical claim - mark for rewrite (warning severity)
            $rewritable[] = [
                'claim' => $claimText,
                'classification' => $classification,
                'reason' => 'Technical claim not found in product specs',
            ];
            $warnings[] = 'unverified_technical_claim:'.$claimText;
            $classified[] = array_merge($claim, [
                'status' => 'unverified',
                'classification' => $classification,
            ]);
            $log[] = [
                'validator' => 'TechnicalFactValidator',
                'claim' => $claimText,
                'classification' => 'product_technical_claim',
                'source' => 'not_found_in_specs',
                'action' => 'marked_for_rewrite',
            ];
        }

        return [
            'status' => $blocked === [] ? 'verified' : 'blocked',
            'warnings' => IssueList::normalize($warnings),
            'blocked_claims' => IssueList::normalize($blocked),
            'used_facts' => IssueList::normalize($used),
            'technical_claims' => $classified,
            'rewritable_claims' => $rewritable,
            'log' => $log,
        ];
    }

    private function capacityValue(array $context, string $key): ?float
    {
        $value = Arr::get($context, $key);
        if ($value === null) {
            $value = Arr::get($context, 'capacity_semantics.'.$key.'.value');
        }
        if ($value === null) {
            $factKey = $key === 'technical_capacity_btu'
                ? 'product.rated_cooling_capacity_btu'
                : 'product.marketing_capacity_btu';
            foreach ((array) Arr::get($context, 'verified_fact_registry', []) as $fact) {
                if (($fact['fact_key'] ?? null) === $factKey) {
                    $value = $fact['value'] ?? null;
                    break;
                }
            }
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
