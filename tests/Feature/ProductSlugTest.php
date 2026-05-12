<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_slug_is_generated_when_created_with_only_name(): void
    {
        $product = Product::create([
            'name' => 'Dieu hoa test',
        ]);

        $this->assertSame('dieu-hoa-test', $product->slug);
    }

    public function test_generated_product_slug_is_unique(): void
    {
        Product::create([
            'name' => 'Dieu hoa test',
        ]);

        $product = Product::create([
            'name' => 'Dieu hoa test',
        ]);

        $this->assertSame('dieu-hoa-test-1', $product->slug);
    }
}
