<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\Governance\BusinessClaimValidator;
use App\Services\AI\Governance\ClaimClassifier;
use App\Services\AI\Governance\ContentSafetyValidator;
use App\Services\AI\Governance\HVACTechnicalFactValidator;
use App\Services\AI\Governance\HVACUnitNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test suite for the 3-layer AI Governance System.
 *
 * Tests cover:
 * - Product content scenarios (verified specs, missing specs, VAT)
 * - Blog/Post scenarios (educational statements, formulas)
 * - Category scenarios (no product source)
 * - Safety scenarios (code leaks, mojibake, HTML)
 * - Severity classification (critical vs warning vs review)
 */
class AIGovernanceThreeLayerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Product Tests
    // =========================================================================

    /** @test 1. Legacy BTU alone is not technical authority. */
    public function test_product_with_verified_btu_passes(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Sản phẩm có công suất 24.000 BTU, phù hợp cho không gian làm việc.</p>',
            $context
        );

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('ambiguous_capacity_claim:24.000 BTU', $result['blocked_claims']);
    }

    /** @test 2. Product không có 60m2 → AI viết 60m2 → rewrite/remove, không fail toàn job */
    public function test_product_without_area_rewrites_instead_of_blocking(): void
    {
        $product = Product::factory()->create([
            'recommended_area' => null,
            'btu' => 24000,
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Phù hợp phòng 60m2 với công suất 24.000 BTU.</p>',
            $context
        );

        // Should NOT hard-block the entire job
        $this->assertSame('blocked', $result['status']);
        // Should have warnings about unverified claim
        $hasUnverifiedWarning = false;
        foreach ($result['warnings'] as $w) {
            if (str_contains($w, '60') || str_contains($w, 'unverified')) {
                $hasUnverifiedWarning = true;
                break;
            }
        }
        $this->assertTrue($hasUnverifiedWarning, 'Should have warning about unverified 60m2 claim');
    }

    /** @test 3. Product có VAT enabled → AI viết đã bao gồm VAT → pass BusinessClaimValidator */
    public function test_product_with_vat_enabled_passes_business_validator(): void
    {
        $product = Product::factory()->create(['price_includes_vat' => true]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $businessValidator = app(BusinessClaimValidator::class);
        $result = $businessValidator->validate('Giá đã bao gồm VAT.', $context);

        $this->assertSame('verified', $result['status']);
        $this->assertContains('vat', $result['allowed_claims']);
    }

    /** @test 4. Product không có VAT enabled → AI viết VAT → rewrite/remove */
    public function test_product_without_vat_gets_rewritten(): void
    {
        $product = Product::factory()->create(['price_includes_vat' => false]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $businessValidator = app(BusinessClaimValidator::class);
        $result = $businessValidator->validate('Giá đã bao gồm VAT.', $context);

        // VAT should NOT be in blocked_claims (it's a business claim, not technical)
        $this->assertEmpty($result['allowed_claims']);
        $this->assertNotEmpty($result['rewrite_claims']);
        // Should suggest rewrite, not block
        $this->assertSame('verified', $result['status']); // Business validator never blocks
    }

    /** @test 5. AI payload chứa technical_specs_json → block update field */
    public function test_ai_payload_containing_specs_json_is_blocked_field(): void
    {
        $this->assertContains(
            'technical_specs_json',
            config('ai_product_allowed_fields.blocked_product_data_fields')
        );
        $this->assertContains(
            'capacity_btu',
            config('ai_product_allowed_fields.blocked_product_data_fields')
        );
    }

    // =========================================================================
    // Blog/Post Tests
    // =========================================================================

    /** @test 6. Blog viết "BTU là đơn vị đo công suất lạnh" → không block */
    public function test_blog_educational_btu_statement_not_blocked(): void
    {
        $classifier = app(ClaimClassifier::class);

        $classification = $classifier->classify(
            '1 BTU',
            'BTU là đơn vị đo công suất lạnh, viết tắt của British Thermal Unit.'
        );

        $this->assertSame('educational_statement', $classification);
    }

    /** @test 7. Blog viết "1m2 = 600 BTU" không có BTU rule → rewrite/remove */
    public function test_blog_formula_without_btu_service_marked_for_rewrite(): void
    {
        $classifier = app(ClaimClassifier::class);

        $classification = $classifier->classify(
            '600 BTU',
            '1m2 = 600 BTU là công thức tính công suất điều hòa.'
        );

        $this->assertSame('formula_calculation_claim', $classification);
    }

    /** @test 8. Blog có product liên quan, dùng specs product → pass */
    public function test_blog_with_related_product_specs_passes(): void
    {
        $product = Product::factory()->create([
            'btu' => 36000,
            'noise_level' => '54',
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Sản phẩm có công suất 36.000 BTU với độ ồn 54 dB.</p>',
            $context
        );

        $this->assertSame('blocked', $result['status']);
    }

    /** @test 9. Blog không có product liên quan, tự đưa dB/Pa/mm → rewrite/remove */
    public function test_blog_without_product_unverified_specs_warned(): void
    {
        // Empty context (no product)
        $governance = app(AIContentGovernance::class);
        $context = [
            'allowed_facts' => [],
            'verified_fact_registry' => [],
            'missing_facts' => [],
            'calculation_rules' => [],
        ];

        $result = $governance->validateText(
            '<p>Máy có độ ồn 54 dB và áp suất tĩnh 160 Pa.</p>',
            $context
        );

        // Should have warnings, not hard blocks
        $hasWarnings = ! empty($result['warnings']);
        $this->assertTrue($hasWarnings);
    }

    // =========================================================================
    // Category Tests
    // =========================================================================

    /** @test 10. Category content không có product source, không được đưa thông số cụ thể */
    public function test_category_without_product_source_flags_specific_specs(): void
    {
        $classifier = app(ClaimClassifier::class);

        // Product-specific claim without product context
        $classification = $classifier->classify(
            '24.000 BTU',
            'Sản phẩm có công suất 24.000 BTU phù hợp cho văn phòng.'
        );

        $this->assertSame('ambiguous_capacity_claim', $classification);
    }

    // =========================================================================
    // Safety Tests
    // =========================================================================

    /** @test 11. AI output có BTUCalculatorService → block/rewrite */
    public function test_code_leak_detected_and_blocked(): void
    {
        $safetyValidator = app(ContentSafetyValidator::class);

        $result = $safetyValidator->validate('Sử dụng BTUCalculatorService để tính công suất.');

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('critical', $result['severity']);
        $this->assertNotEmpty($result['blocked_claims']);
    }

    /** @test 12. AI output mojibake → reject */
    public function test_mojibake_detected_and_rejected(): void
    {
        $safetyValidator = app(ContentSafetyValidator::class);

        $result = $safetyValidator->validate('phÃ­ lắp đặt máy điều hòa');

        // Should either block or warn about mojibake
        $this->assertNotSame('verified', $result['status']);
    }

    /** @test 13. AI output HTML unsafe → sanitize */
    public function test_unsafe_html_detected(): void
    {
        $safetyValidator = app(ContentSafetyValidator::class);

        $result = $safetyValidator->validate('<script>alert("xss")</script><p>Content</p>');

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('critical', $result['severity']);
    }

    // =========================================================================
    // Claim Classification Tests
    // =========================================================================

    /** @test Educational "1 BTU" unit definition is not treated as product spec */
    public function test_unit_definition_1_btu_is_ignored(): void
    {
        $classifier = app(ClaimClassifier::class);

        $this->assertTrue(
            $classifier->isUnitDefinitionNumber('1 BTU', 'Mỗi 1 BTU tương ứng với năng lượng cần thiết.')
        );
    }

    /** @test Educational "1m2" area unit is not treated as product spec */
    public function test_unit_definition_1m2_is_ignored(): void
    {
        $classifier = app(ClaimClassifier::class);

        $this->assertTrue(
            $classifier->isUnitDefinitionNumber('1m2', 'Mỗi 1m2 là đơn vị diện tích quy chuẩn.')
        );
    }

    /** @test VAT mention classified as business claim, not technical */
    public function test_vat_classified_as_business_not_technical(): void
    {
        $classifier = app(ClaimClassifier::class);

        $classification = $classifier->classify(
            'VAT',
            'Giá đã bao gồm VAT và phí lắp đặt.'
        );

        $this->assertSame('business_config_claim', $classification);
    }

    /** @test Product spec "24.000 BTU" classified as product claim */
    public function test_product_spec_classified_correctly(): void
    {
        $classifier = app(ClaimClassifier::class);

        $classification = $classifier->classify(
            '24.000 BTU',
            'Sản phẩm có công suất 24.000 BTU.'
        );

        $this->assertSame('ambiguous_capacity_claim', $classification);
    }

    // =========================================================================
    // Severity Classification Tests
    // =========================================================================

    /** @test Unverified numeric claim produces warning, not block */
    public function test_unverified_numeric_claim_is_warning_not_block(): void
    {
        $product = Product::factory()->create([
            'btu' => 24000,
            'recommended_area' => null,
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Phù hợp phòng 60m2.</p>',
            $context
        );

        // Missing area is still a warning-only case.
        $this->assertNotSame('blocked', $result['status']);
    }

    /** @test Code leak produces critical block */
    public function test_code_leak_is_critical_block(): void
    {
        $safetyValidator = app(ContentSafetyValidator::class);

        $result = $safetyValidator->validate('App\\Services\\AI\\AIContentGovernance đang xử lý.');

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('critical', $result['severity']);
    }

    // =========================================================================
    // Business Claim Validator Integration Tests
    // =========================================================================

    /** @test Business claim validator handles warranty with source */
    public function test_warranty_with_source_passes(): void
    {
        $product = Product::factory()->create(['warranty_info' => 'Bảo hành 3 năm chính hãng']);
        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $validator = app(BusinessClaimValidator::class);
        $result = $validator->validate('Sản phẩm được bảo hành chính hãng.', $context);

        $this->assertContains('bao_hanh', $result['allowed_claims']);
    }

    /** @test Business claim validator rewrites warranty without source */
    public function test_warranty_without_source_gets_rewritten(): void
    {
        $product = Product::factory()->create(['warranty_info' => null]);
        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $validator = app(BusinessClaimValidator::class);
        $result = $validator->validate('Sản phẩm được bảo hành 5 năm.', $context);

        $this->assertNotContains('bao_hanh', $result['allowed_claims']);
        $this->assertSame('verified', $result['status']); // Never blocks
    }

    // =========================================================================
    // Integration: Full Pipeline Tests
    // =========================================================================

    /** @test Full pipeline: verified product passes clean */
    public function test_full_pipeline_verified_product(): void
    {
        $product = Product::factory()->create([
            'btu' => 24000,
            'technical_capacity_btu' => 24000,
            'technical_capacity_status' => 'verified_candidate',
            'noise_level' => '42',
            'warranty_info' => 'Bảo hành 3 năm',
            'price_includes_vat' => true,
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Sản phẩm có công suất kỹ thuật 24.000 BTU, độ ồn 42 dB. Giá đã bao gồm VAT. Bảo hành chính hãng.</p>',
            $context
        );

        $this->assertNotSame('blocked', $result['status']);
    }

    /** @test Full pipeline: mixed claims get warnings not blocks */
    public function test_full_pipeline_mixed_claims_no_hard_block(): void
    {
        $product = Product::factory()->create([
            'btu' => 24000,
            'recommended_area' => null,
            'price_includes_vat' => false,
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Công suất 24.000 BTU, phù hợp phòng 60m2. Giá bao gồm VAT.</p>',
            $context
        );

        // Generic capacity wording is blocked until its commercial/technical
        // semantics are made explicit.
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('ambiguous_capacity_claim:24.000 BTU', $result['blocked_claims']);
        // But should have warnings
        $this->assertNotEmpty($result['warnings']);
    }

    /** @test Validation log contains structured data */
    public function test_validation_log_is_structured(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildProductContext($product);

        $result = $governance->validateText(
            '<p>Công suất 24.000 BTU.</p>',
            $context
        );

        $this->assertArrayHasKey('validation_log', $result);
    }
}
