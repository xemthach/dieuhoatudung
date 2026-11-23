<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Brand;
use App\Models\PolicyPage;
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

        $this->assertSame(['Trang chủ','Sản phẩm','Bảng giá','Blog','Đại lý','FAQ','Liên hệ'], array_column($items, 'label'));
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

        // Search indexability must not prevent people from visiting an active category.
        $category->update(['is_indexable' => false]);
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

    public function test_protocol_relative_and_insecure_external_urls_are_rejected(): void
    {
        $settings = app(SettingService::class);
        foreach (['//example.com/path', 'http://example.com/path', 'data:text/html,unsafe'] as $url) {
            $settings->set('header_primary', [['label' => 'Unsafe', 'type' => 'custom_url', 'custom_target' => $url]], 'navigation');
            $this->assertSame([], app(PublicNavigationResolver::class)->items('header_primary'));
        }
    }

    public function test_brand_and_policy_targets_resolve_only_active_records(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        $page = PolicyPage::factory()->create(['is_active' => true]);
        app(SettingService::class)->set('header_primary', [
            ['label' => 'Brand', 'type' => 'brand', 'brand_target' => $brand->id, 'sort_order' => 1],
            ['label' => 'Policy', 'type' => 'page', 'page_target' => $page->id, 'sort_order' => 2],
        ], 'navigation');
        $items = app(PublicNavigationResolver::class)->items('header_primary');
        $this->assertSame(route('brands.show', $brand->slug), $items[0]['url']);
        $this->assertSame(route('policy-pages.show', $page->slug), $items[1]['url']);
        $brand->update(['is_active' => false]);
        $page->update(['is_active' => false]);
        $this->assertSame([], app(PublicNavigationResolver::class)->items('header_primary'));
    }
}
