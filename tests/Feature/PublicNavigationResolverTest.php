<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Services\Navigation\PublicNavigationResolver;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_navigation_uses_catalog_products_route_not_floor_standing_landing(): void
    {
        $items = app(PublicNavigationResolver::class)->items('header_primary');

        $this->assertSame(['Trang chủ','Sản phẩm','Bảng giá','Blog','FAQ','Liên hệ'], array_column($items, 'label'));
        $this->assertSame(route('products.index'), $items[1]['url']);
    }

    public function test_category_target_is_resolved_by_id_and_hidden_when_inactive(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Cassette công khai',
            'is_active' => true,
            'is_indexable' => true,
        ]);

        app(SettingService::class)->set('header_primary', [[
            'label' => 'Cassette',
            'type' => 'product_category',
            'category_target' => $category->id,
            'sort_order' => 1,
            'is_active' => true,
        ]], 'navigation');

        $this->assertSame(route('category.show', $category->slug), app(PublicNavigationResolver::class)->items('header_primary')[0]['url']);

        $category->update(['is_active' => false]);
        $this->assertSame([], app(PublicNavigationResolver::class)->items('header_primary'));
    }

    public function test_unsafe_custom_url_is_not_rendered(): void
    {
        app(SettingService::class)->set('header_primary', [[
            'label' => 'Unsafe',
            'type' => 'custom_url',
            'custom_target' => 'javascript:alert(1)',
            'sort_order' => 1,
            'is_active' => true,
        ]], 'navigation');

        $this->assertSame([], app(PublicNavigationResolver::class)->items('header_primary'));
    }
}
