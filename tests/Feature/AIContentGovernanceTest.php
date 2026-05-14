<?php

namespace Tests\Feature;

use App\Enums\AIContentJobStatus;
use App\Models\AiContentJob;
use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIContentGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_capacity_btu_from_database_is_allowed(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>San pham co cong suat 24.000 BTU.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertSame([], $result['blocked_claims']);
    }

    public function test_product_without_airflow_cannot_claim_strong_airflow(): void
    {
        $product = Product::factory()->create(['airflow' => null]);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>May co luu luong gio manh cho khong gian rong.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('airflow_claim_without_source', $result['blocked_claims']);
    }

    public function test_blog_btu_request_missing_inputs_cannot_publish_specific_btu(): void
    {
        $job = AiContentJob::create([
            'topic' => 'Cach tinh BTU cho nha xuong',
            'primary_keyword' => 'tinh btu nha xuong',
            'status' => AIContentJobStatus::Pending,
            'input_payload' => [],
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildBlogContext($job);

        $result = $governance->validateText(
            '<p>Nha xuong 100m2 can 60.000 BTU.</p>',
            $context
        );

        $this->assertContains('missing_btu_inputs', $context['missing_facts']);
        $this->assertNotEmpty($result['blocked_claims']);
    }

    public function test_blog_area_with_unsupported_btu_rule_cannot_use_external_formula(): void
    {
        $job = AiContentJob::create([
            'topic' => 'Tinh BTU phong dac biet',
            'primary_keyword' => 'tinh btu',
            'status' => AIContentJobStatus::Pending,
            'input_payload' => [
                'btu_inputs' => [
                    'area_m2' => 50,
                    'ceiling_height' => 3,
                    'space_type' => 'clean_room_unknown',
                ],
            ],
        ]);
        $governance = app(AIContentGovernance::class);
        $context = $governance->buildBlogContext($job);

        $result = $governance->validateText(
            '<p>Phong 50m2 can 30.000 BTU theo cong thuc thong dung.</p>',
            $context
        );

        $this->assertContains('missing_btu_rule', $context['missing_facts']);
        $this->assertNotEmpty($result['blocked_claims']);
    }

    public function test_unverified_btu_number_is_blocked(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>San pham nay co cong suat 30.000 BTU.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('unverified_numeric_claim:30.000 BTU', $result['blocked_claims']);
    }

    public function test_refrigerant_code_is_not_treated_as_ampere_claim(): void
    {
        $product = Product::factory()->create(['refrigerant_gas' => 'R410A']);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>San pham su dung moi chat lanh R410A.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertNotContains('unverified_numeric_claim:410A', $result['blocked_claims']);
    }

    public function test_recommended_area_range_allows_area_inside_verified_range(): void
    {
        $product = Product::factory()->create(['recommended_area' => '50-70 m2']);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>San pham phu hop khu vuc 60m2 khi dieu kien lap dat dap ung yeu cau.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertSame('verified', $result['status']);
        $this->assertSame([], $result['blocked_claims']);
    }

    public function test_warranty_claim_without_policy_is_blocked(): void
    {
        $product = Product::factory()->create(['warranty_info' => null]);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>San pham duoc bao hanh 5 nam.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('bao_hanh', $result['blocked_claims']);
    }

    public function test_vat_claim_without_config_is_blocked(): void
    {
        $product = Product::factory()->create();
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>Gia ban da bao gom VAT.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('vat', $result['blocked_claims']);
    }

    public function test_official_100_percent_claim_without_source_is_blocked(): void
    {
        $product = Product::factory()->create();
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>Hang chinh hang 100%.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('chinh_hang', $result['blocked_claims']);
        $this->assertContains('percent_100', $result['blocked_claims']);
    }

    public function test_internal_service_or_variable_names_are_blocked(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);

        $result = $governance->validateText(
            '<p>Cong suat duoc tinh bang BTUCalculatorService va product.capacity_btu.</p>',
            $governance->buildProductContext($product)
        );

        $this->assertContains('internal_layer_name', $result['blocked_claims']);
        $this->assertContains('internal_variable_path', $result['blocked_claims']);
    }

    public function test_public_governance_context_does_not_expose_internal_fact_keys(): void
    {
        $product = Product::factory()->create(['btu' => 24000]);
        $governance = app(AIContentGovernance::class);

        $publicContext = json_encode(
            $governance->publicContext($governance->buildProductContext($product)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $this->assertStringNotContainsString('product.capacity_btu', $publicContext);
        $this->assertStringNotContainsString('BtuCalculatorService', $publicContext);
        $this->assertStringContainsString('công suất BTU', $publicContext);
    }
}
