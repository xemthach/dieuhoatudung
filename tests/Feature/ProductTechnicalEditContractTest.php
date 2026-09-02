<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Services\Product\ProductTechnicalSpecWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductTechnicalEditContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_btu_is_canonical_and_not_the_legacy_btu_column(): void
    {
        $product = Product::factory()->create([
            'btu' => null,
            'marketing_capacity_btu' => 12000,
            'technical_capacity_btu' => 11942,
            'technical_capacity_status' => 'verified_candidate',
        ]);

        $this->assertSame(11942, app(ProductTechnicalFactResolver::class)->value($product, 'technical_capacity_btu'));
        $this->assertSame(12000, $product->marketing_capacity_btu);
        $this->assertNull($product->btu);
    }

    public function test_manual_edit_requires_a_reason_preserves_catalog_evidence_and_becomes_current_value(): void
    {
        $catalogSpecs = [[
            'key' => 'capacity_kw',
            'value' => '3.6',
            'source_pdf' => 'catalog.pdf',
            'source_sha256' => str_repeat('a', 64),
            'source_page' => '42',
            'source_section' => 'TECHNICAL_APPENDIX',
            'verification_status' => 'verified_candidate',
        ]];
        $product = Product::factory()->create([
            'btu' => null,
            'technical_capacity_btu' => 12300,
            'technical_capacity_status' => 'verified_candidate',
            'capacity_kw' => 3.6,
            'hp' => 1.5,
            'specs_json' => $catalogSpecs,
            'technical_specs_source' => 'catalog_verified_specs',
            'technical_specs_override_reason' => null,
            'technical_specs_overridden_at' => null,
        ]);
        $writer = app(ProductTechnicalSpecWriter::class);

        try {
            $writer->manualOverrideAttributes($product, ['technical_capacity_btu' => 12400], null);
            $this->fail('A technical override must require an audit reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('technical_specs_override_reason', $exception->errors());
        }

        $changes = $writer->manualOverrideAttributes($product, [
            'technical_capacity_btu' => 12400,
            'capacity_kw' => 3.7,
            'hp' => 1.6,
            'voltage' => '220V',
        ], 'Corrected from installation verification.');
        $product->update($changes);
        $product->refresh();

        $this->assertSame(12400, $product->technical_capacity_btu);
        $this->assertSame('3.70', $product->capacity_kw);
        $this->assertSame('1.6', $product->hp);
        $this->assertSame('manual_override', $product->technical_specs_source);
        $this->assertSame('Corrected from installation verification.', $product->technical_specs_override_reason);
        $this->assertNotNull($product->technical_specs_overridden_at);
        $this->assertSame($catalogSpecs, $product->specs_json, 'Source-native catalog evidence must not be overwritten.');
        $this->assertSame('3.70', app(ProductTechnicalFactResolver::class)->value($product, 'capacity_kw'));
    }

    public function test_legacy_btu_is_not_promoted_when_technical_capacity_is_missing(): void
    {
        $product = Product::factory()->create([
            'btu' => 18000,
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'specs_json' => null,
        ]);

        $this->assertNull(app(ProductTechnicalFactResolver::class)->value($product, 'technical_capacity_btu'));
        $this->assertSame(18000, app(ProductTechnicalFactResolver::class)->getDisplay($product, 'technical_capacity_btu')['value']);
    }
}
