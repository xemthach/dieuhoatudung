<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Services\Product\PromotionPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailNumericFormattingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_resolver_accepts_only_plain_numeric_domain_values(): void
    {
        $cases = [
            [12500000, 12500000.0],
            [12500000.5, 12500000.5],
            ['12500000.00', 12500000.0],
            [null, null],
            ['', null],
            ['12.500.000', null],
            ['12,500,000', null],
            ['Liên hệ', null],
        ];

        foreach ($cases as [$input, $expected]) {
            $product = new class extends Product {
                protected function casts(): array
                {
                    return [];
                }
            };
            $product->setRawAttributes([
                'regular_price' => $input,
                'sale_price' => null,
                'discount_percent' => null,
                'price_includes_vat' => false,
            ]);

            $resolved = app(PromotionPriceResolver::class)->resolve($product);

            $this->assertSame($expected, $resolved['regular_price'], 'Unexpected normalization for '.var_export($input, true));
            $this->assertSame($expected, $resolved['final_price'], 'Unexpected final price for '.var_export($input, true));
        }
    }

    public function test_btu_formatter_handles_scalars_ranges_and_rejects_ambiguous_text(): void
    {
        $formatter = app(ProductTechnicalFactResolver::class);

        $this->assertSame('35,800', $formatter->formatBtuDisplay(35800));
        $this->assertSame('35,800.5', $formatter->formatBtuDisplay(35800.5));
        $this->assertSame('35,800', $formatter->formatBtuDisplay('35800'));
        $this->assertSame('24,225.2 / 28,660.8', $formatter->formatBtuDisplay('24225.2 / 28660.8'));
        $this->assertNull($formatter->formatBtuDisplay(null));
        $this->assertNull($formatter->formatBtuDisplay(''));
        $this->assertNull($formatter->formatBtuDisplay('12.500.000'));
        $this->assertNull($formatter->formatBtuDisplay('Liên hệ'));
    }

    public function test_product_detail_renders_actual_range_shaped_capacity_without_type_error(): void
    {
        $product = Product::factory()->create([
            'name' => 'Regression range capacity Product',
            'slug' => 'regression-range-capacity-product',
            'is_active' => true,
            'btu' => 24225,
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'regular_price' => '32990000.00',
            'sale_price' => null,
            'specs_json' => [[
                'key' => 'capacity_btu',
                'value' => '24225.2 / 28660.8',
            ]],
        ]);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('24,225.2 / 28,660.8 BTU')
            ->assertSee('32,990,000₫');
    }
}
