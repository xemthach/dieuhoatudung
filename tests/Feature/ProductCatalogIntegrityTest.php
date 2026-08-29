<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_breadcrumb_does_not_link_to_an_inactive_category(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Danh mục tạm ẩn',
            'slug' => 'danh-muc-tam-an',
            'is_active' => false,
        ]);
        $product = Product::factory()->create([
            'name' => 'Sản phẩm kiểm tra breadcrumb',
            'product_category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk()->assertSee('Danh mục tạm ẩn');
        $response->assertDontSee(route('category.show', $category->slug));
    }

    public function test_product_breadcrumb_links_to_an_active_category(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Danh mục công khai',
            'slug' => 'danh-muc-cong-khai',
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee(route('category.show', $category->slug), false);
    }
}
