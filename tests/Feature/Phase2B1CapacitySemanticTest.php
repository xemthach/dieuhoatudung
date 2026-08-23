<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AI\AIContentGovernance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2B1CapacitySemanticTest extends TestCase
{
    use RefreshDatabase;

    public function test_gcc42_marketing_group_is_not_technical_claim(): void
    {
        $this->assertClaim('thuộc nhóm công suất 42.000 BTU', 42000, 42650, 'verified', 'marketing_capacity_claim');
    }

    public function test_gcc42_technical_wording_rejects_marketing_number(): void
    {
        $result = $this->claim('công suất danh định 42.000 BTU', 42000, 42650);

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('contradicted_technical_capacity:42.000 BTU', $result['blocked_claims']);
    }

    public function test_gcc42_verified_technical_number_passes(): void
    {
        $this->assertClaim('công suất kỹ thuật 42.650 BTU', 42000, 42650, 'verified', 'technical_capacity_claim');
        $this->assertClaim('công suất lạnh 42.650 BTU', 42000, 42650, 'verified', 'technical_capacity_claim');
    }

    public function test_generic_capacity_wording_is_blocked_as_ambiguous(): void
    {
        $result = $this->claim('máy có công suất 42.000 BTU', 42000, 42650);

        $this->assertSame('blocked', $result['status']);
        $this->assertContains('ambiguous_capacity_claim:42.000 BTU', $result['blocked_claims']);
    }

    public function test_gdc24_uses_24200_for_technical_wording(): void
    {
        $this->assertClaim('công suất định mức 24.200 BTU', 24000, 24200, 'verified', 'technical_capacity_claim');
        $this->assertClaim('dòng máy 24.000 BTU', 24000, 24200, 'verified', 'marketing_capacity_claim');
    }

    public function test_gud50_does_not_infer_between_marketing_and_technical_values(): void
    {
        $this->assertClaim('công suất thực 16.400 BTU', 18000, 16400, 'verified', 'technical_capacity_claim');

        $result = $this->claim('công suất lạnh 18.000 BTU', 18000, 16400);
        $this->assertSame('blocked', $result['status']);
    }

    public function test_product_name_and_legacy_btu_cannot_satisfy_technical_capacity(): void
    {
        $product = Product::factory()->create([
            'name' => 'GREE 42000 BTU',
            'btu' => 42000,
            'marketing_capacity_btu' => 42000,
            'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $result = app(AIContentGovernance::class)->validateText(
            'Công suất danh định 42.000 BTU.',
            app(AIContentGovernance::class)->buildProductContext($product)
        );

        $this->assertContains('contradicted_technical_capacity:42.000 BTU', $result['blocked_claims']);
    }

    public function test_product_name_capacity_is_only_a_generic_identity_mention(): void
    {
        $product = Product::factory()->create([
            'name' => 'GREE 42000 BTU', 'btu' => 42000,
            'marketing_capacity_btu' => 42000, 'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $governance = app(AIContentGovernance::class);
        $result = $governance->validateText($product->name.'.', $governance->buildProductContext($product));

        $this->assertSame('generic_capacity_mention', $result['technical_claims'][0]['classification']);
        $this->assertNotContains('product.marketing_capacity_btu', $result['used_facts']);
        $this->assertNotContains('product.rated_cooling_capacity_btu', $result['used_facts']);
    }

    public function test_structured_context_keeps_capacity_semantics_separate(): void
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => 42000,
            'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $this->assertSame(42000, $context['marketing_identity_facts']['capacity_group_btu']);
        $this->assertSame(42650, $context['verified_technical_facts']['rated_cooling_capacity_btu']);
        $this->assertSame('COMMERCIAL_GROUPING_ONLY', $context['capacity_semantics']['marketing_capacity_btu']['meaning']);
        $this->assertSame('AUTHORITATIVE_TECHNICAL_RATED_CAPACITY', $context['capacity_semantics']['technical_capacity_btu']['meaning']);
    }

    private function assertClaim(string $text, int $marketing, int $technical, string $status, string $classification): void
    {
        $result = $this->claim($text, $marketing, $technical);
        $this->assertSame($status, $result['status']);
        $this->assertSame($classification, $result['technical_claims'][0]['classification']);
    }

    private function claim(string $text, int $marketing, int $technical): array
    {
        $product = Product::factory()->create([
            'marketing_capacity_btu' => $marketing,
            'technical_capacity_btu' => $technical,
            'technical_capacity_status' => 'verified_candidate',
        ]);
        $governance = app(AIContentGovernance::class);

        return $governance->validateText($text, $governance->buildProductContext($product));
    }
}
