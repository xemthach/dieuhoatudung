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
            'blocked_claims' => IssueList::normalize($blocked), // Technical claims no longer hard-block
            'used_facts' => IssueList::normalize($used),
            'technical_claims' => $classified,
            'rewritable_claims' => $rewritable,
            'log' => $log,
        ];
    }
}
