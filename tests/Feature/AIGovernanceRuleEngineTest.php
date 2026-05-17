<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use App\Services\AI\Governance\AICodeLeakDetector;
use App\Services\AI\Governance\ForbiddenClaimEngine;
use App\Services\AI\Governance\HVACUnitNormalizer;
use App\Services\AI\Governance\UTF8ContentValidator;
use App\Services\AI\Governance\VerifiedFactRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AIGovernanceRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_hvac_unit_normalizer_handles_global_unit_aliases(): void
    {
        $normalizer = app(HVACUnitNormalizer::class);

        $claims = collect($normalizer->extractTechnicalClaims('60m², 60 m2, ESP 160, 54dB, 6.35mm'));

        $this->assertTrue($claims->contains('normalized_value', '60_m2'));
        $this->assertTrue($claims->contains('normalized_value', '160_pa'));
        $this->assertTrue($claims->contains('normalized_value', '54_db'));
        $this->assertTrue($claims->contains('normalized_value', '6.35_mm'));
    }

    public function test_verified_fact_registry_passes_verified_specs_without_case_specific_rules(): void
    {
        $product = Product::factory()->create([
            'specs_json' => [
                ['key' => 'esp', 'label' => 'ESP', 'value' => '160 Pa'],
                ['key' => 'external_static_pressure', 'label' => 'Ap suat tinh', 'value' => '50 Pa'],
                ['key' => 'recommended_area', 'label' => 'Dien tich khuyen nghi', 'value' => '60 m²'],
                ['key' => 'pipe_liquid', 'label' => 'Ong dong long', 'value' => '6.35 mm'],
                ['key' => 'outdoor_noise_db', 'label' => 'Do on dan nong', 'value' => '54 dB'],
            ],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>May co ESP 160 Pa, ap suat tinh 50 Pa, phu hop 60m², ong dong 6.35mm va do on 54 dB.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertSame([], $result['blocked_claims']);
        $this->assertContains('product.technical_specs_json.0', $result['used_facts']);
    }

    public function test_unverified_technical_claim_is_blocked_by_registry(): void
    {
        $product = Product::factory()->create([
            'specs_json' => [
                ['key' => 'esp', 'label' => 'ESP', 'value' => '120 Pa'],
            ],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>May co ESP 160 Pa.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('unverified_numeric_claim:160 Pa', $result['blocked_claims']);
    }

    public function test_forbidden_claim_engine_blocks_policy_claims_without_source(): void
    {
        $product = Product::factory()->create(['warranty_info' => null]);
        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $result = app(ForbiddenClaimEngine::class)->detect(
            'Gia da bao gom VAT, co day du CO/CQ va chinh hang 100%.',
            $context
        );

        $this->assertContains('vat', $result['blocked_claims']);
        $this->assertContains('co_cq', $result['blocked_claims']);
        $this->assertContains('percent_100', $result['blocked_claims']);
        $this->assertNotContains('chinh_hang', $result['blocked_claims']);
        $this->assertContains('claim_requires_rewrite:chinh_hang', $result['warnings']);
    }

    public function test_soft_marketing_claims_are_rewrite_warnings_not_hard_blocks(): void
    {
        $product = Product::factory()->create(['warranty_info' => null]);
        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $result = app(ForbiddenClaimEngine::class)->detect(
            'Mien phi lap dat, bao hanh, gia tot nhat va hieu suat vuot troi.',
            $context
        );

        $this->assertSame([], $result['blocked_claims']);
        $this->assertContains('claim_requires_rewrite:mien_phi', $result['warnings']);
        $this->assertContains('claim_requires_rewrite:bao_hanh', $result['warnings']);
        $this->assertContains('claim_requires_rewrite:gia_tot_nhat', $result['warnings']);
        $this->assertContains('claim_requires_rewrite:vuot_troi', $result['warnings']);
    }

    public function test_code_leak_detector_blocks_internal_language(): void
    {
        $result = app(AICodeLeakDetector::class)->detect('BTUCalculatorService dang doc product.capacity_btu.');

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('internal_layer_name', $result['blocked_claims']);
        $this->assertContains('internal_variable_path', $result['blocked_claims']);
    }

    public function test_utf8_validator_rejects_mojibake_when_required(): void
    {
        $this->expectException(RuntimeException::class);

        app(UTF8ContentValidator::class)->assertClean('phÃ­ lắp đặt', 'test_mojibake');
    }

    public function test_product_allowed_fields_are_single_source_of_truth(): void
    {
        $this->assertContains('content_html', config('ai_product_allowed_fields.content_layer_fields'));
        $this->assertContains('capacity_btu', config('ai_product_allowed_fields.blocked_product_data_fields'));
        $this->assertContains('technical_specs_json', config('ai_product_allowed_fields.blocked_product_data_fields'));
    }

    public function test_registry_format_contains_required_governance_fields(): void
    {
        $product = Product::factory()->create(['specs_json' => [['key' => 'noise', 'value' => '54 dB']]]);
        $registry = app(VerifiedFactRegistry::class)->buildForProduct($product);
        $fact = collect($registry)->firstWhere('normalized_value', '54_db');

        $this->assertNotNull($fact);
        $this->assertArrayHasKey('normalized_key', $fact);
        $this->assertArrayHasKey('original_value', $fact);
        $this->assertArrayHasKey('source', $fact);
        $this->assertArrayHasKey('source_field', $fact);
        $this->assertArrayHasKey('confidence', $fact);
    }

    public function test_unitless_esp_range_inherits_pa_from_adjacent_verified_spec(): void
    {
        $product = Product::factory()->create([
            'specs_json' => [
                ['key' => 'indoor_esp_nominal_pa', 'label' => 'ESP nominal', 'value' => '50'],
                ['key' => 'indoor_dn_lnh_phm_vi', 'label' => 'Pham vi dieu chinh', 'value' => '0-160'],
            ],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>ESP 50 Pa, pham vi dieu chinh 0-160 Pa va muc 160 Pa can doi chieu catalogue.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:50 Pa', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:0-160 Pa', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:160 Pa', $result['blocked_claims']);
    }

    public function test_area_claim_without_recommended_area_remains_blocked(): void
    {
        $product = Product::factory()->create([
            'recommended_area' => null,
            'specs_json' => [['key' => 'indoor_esp_nominal_pa', 'label' => 'ESP nominal', 'value' => '50 Pa']],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>Phu hop khong gian 60m2.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('unverified_numeric_claim:60m2', $result['blocked_claims']);
    }

    public function test_range_claim_passes_when_both_endpoints_exist_in_same_verified_fact(): void
    {
        $product = Product::factory()->create([
            'noise_level' => '52/50/46/42',
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>Do on hoat dong trong khoang 42 đến 52 dB theo du lieu ky thuat.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:42 đến 52 dB', $result['blocked_claims']);
    }

    public function test_thousand_separated_btu_does_not_emit_partial_numeric_claim(): void
    {
        $product = Product::factory()->create([
            'btu' => 36000,
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>Cong suat san pham la 36.000 BTU.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:000 BTU', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:36.000 BTU', $result['blocked_claims']);
    }

    public function test_decimal_current_does_not_emit_partial_amp_claim_when_html_text_is_joined(): void
    {
        $product = Product::factory()->create([
            'specs_json' => [
                ['key' => 'rated_current_a', 'label' => 'Dong dien dinh muc', 'value' => '15.3 A'],
            ],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<table><tr><td>Dòng điện định mức</td><td>15.3 A</td></tr></table>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:3 A', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:15.3 A', $result['blocked_claims']);
    }

    public function test_unitless_ranges_do_not_inherit_units_from_unrelated_article_text(): void
    {
        $product = Product::factory()->create([
            'btu' => 36000,
            'noise_level' => '52/50/46/42',
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>Cong suat 36.000 BTU. Bao tri dinh ky moi 3-6 thang va pham vi tham khao 1000-1200 tuy cong trinh.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:3-6', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:1000-1200', $result['blocked_claims']);
    }

    public function test_dimension_components_from_x_or_star_specs_verify_mm_claims(): void
    {
        $product = Product::factory()->create([
            'specs_json' => [
                ['key' => 'outdoor_package_dim_mm', 'label' => 'Outdoor package dimensions', 'value' => '951x431x620'],
                ['key' => 'panel_package_dim_mm', 'label' => 'Panel package dimensions', 'value' => '701*701*125'],
            ],
        ]);

        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText(
            '<p>Kich thuoc kien dan nong co canh 620 mm va kien panel day 125 mm.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:620 mm', $result['blocked_claims']);
        $this->assertNotContains('unverified_numeric_claim:125 mm', $result['blocked_claims']);
    }
}
