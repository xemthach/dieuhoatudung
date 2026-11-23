<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Brand;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompareScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_category_comparison_includes_both_schemas_and_missing_values(): void
    {
        $firstCategory = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => ['fields' => [['key' => 'airflow', 'label' => 'Lưu lượng gió', 'type' => 'number', 'unit' => 'm3/h']]],
        ]);
        $secondCategory = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => ['fields' => [['key' => 'panel_dimensions_mm', 'label' => 'Kích thước mặt nạ', 'type' => 'text', 'unit' => 'none']]],
        ]);
        $first = Product::factory()->create(['product_category_id' => $firstCategory->id, 'is_active' => true, 'specs_json' => [['key' => 'airflow', 'value' => '900']]]);
        $second = Product::factory()->create(['product_category_id' => $secondCategory->id, 'is_active' => true, 'specs_json' => [['key' => 'panel_dimensions_mm', 'value' => '950 × 950']]]);
        $service = app(\App\Services\Product\ProductComparisonService::class);
        $rows = collect($service->buildGroupedSpecs($service->getProducts([$first->slug, $second->slug])))->flatten(1)->keyBy('key');
        $this->assertArrayHasKey('airflow', $rows->all());
        $this->assertArrayHasKey('panel_dimensions_mm', $rows->all());
        $this->assertSame('—', $rows['airflow']['values'][1]);
        $this->assertSame('—', $rows['panel_dimensions_mm']['values'][0]);
        $this->assertStringContainsString('950', $rows['panel_dimensions_mm']['values'][1]);
    }

    public function test_clear_and_safe_get_fallback_never_display_json(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $this->withSession(['compare.products' => [$product->slug]])
            ->get(route('compare.remove'))->assertRedirect(route('compare.index'))
            ->assertSessionHas('compare.products', [$product->slug]);
        $this->get(route('compare.clear'))->assertRedirect(route('compare.index'))
            ->assertSessionHas('compare.products', [$product->slug]);
        $this->post(route('compare.clear'))->assertRedirect(route('compare.index'))
            ->assertSessionMissing('compare.products')->assertSessionHas('status');
        $this->withSession(['compare.products' => [$product->slug]])
            ->postJson(route('compare.clear'))->assertOk()->assertJsonPath('count', 0)->assertJsonPath('items', []);
    }

    public function test_invalid_removal_and_csrf_leave_comparison_intact(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        foreach ([[], ['product_id' => -1], ['product_id' => 999999], ['slug' => 'missing'], ['slug' => ['invalid']]] as $input) {
            $this->withSession(['compare.products' => [$product->slug]])->postJson(route('compare.remove'), $input)
                ->assertUnprocessable()->assertSessionHas('compare.products', [$product->slug]);
        }

        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, fn ($app) =>
            new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests() { return false; }
            });
        foreach (['compare.remove', 'compare.clear'] as $route) {
            foreach (['', 'invalid-token'] as $token) {
                $this->withSession(['_token' => 'valid-session-token', 'compare.products' => [$product->slug]])
                    ->postJson(route($route), ['slug' => $product->slug, '_token' => $token])
                    ->assertStatus(419)->assertSessionHas('compare.products', [$product->slug]);
            }
        }
    }

    public function test_compare_session_deduplicates_and_drops_inactive_products(): void
    {
        $active = Product::factory()->create(['is_active' => true]);
        $inactive = Product::factory()->create(['is_active' => false]);
        $this->get(route('compare.index', ['products' => implode(',', [$active->slug, $active->slug, $inactive->slug, 'missing'])]))
            ->assertOk()->assertSessionHas('compare.products', [$active->slug]);
        $this->postJson(route('compare.add'), ['slug' => $inactive->slug])->assertUnprocessable();
        $this->postJson(route('compare.add'), ['slug' => $active->slug])->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('items.0.slug', $active->slug);
    }

    public function test_scope_changes_and_overrides_do_not_require_code_changes(): void
    {
        $settings = app(SettingService::class);
        $scope = app(\App\Services\Navigation\SiteProductScope::class);
        $this->assertSame('So sánh sản phẩm', $scope->compareTitle());
        $this->get(route('home'))->assertOk()->assertSee('Cần tư vấn chọn điều hòa?');
        $settings->set('product_scope', 'primary_category', 'site');
        foreach (['Điều hòa treo tường', 'Điều hòa cassette'] as $name) {
            $category = ProductCategory::factory()->create(['name' => $name, 'is_active' => true, 'is_indexable' => true]);
            $settings->set('primary_product_category_id', (string) $category->id, 'site');
            $this->assertSame('So sánh '.$name, $scope->compareTitle());
            $this->assertSame('Cần tư vấn '.$name.'?', $scope->consultationTitle());
            $this->assertSame(route('category.show', $category->slug), $scope->productUrl());
        }
        $category->delete();
        $this->assertSame('So sánh sản phẩm', $scope->compareTitle());
        $this->assertSame(route('products.index'), $scope->productUrl());
        $settings->set('compare_title', 'Bảng so sánh tùy chỉnh', 'site');
        $settings->set('consultation_title', 'Hỗ trợ chọn sản phẩm', 'site');
        $settings->set('product_cta_label', 'Khám phá sản phẩm', 'site');
        $this->assertSame('Bảng so sánh tùy chỉnh', $scope->compareTitle());
        $this->assertSame('Hỗ trợ chọn sản phẩm', $scope->consultationTitle());
        $this->assertSame('Khám phá sản phẩm', $scope->productCtaLabel());
    }

    public function test_normal_remove_redirects_and_json_remove_returns_remaining_items(): void
    {
        $first = Product::factory()->create(['is_active' => true]);
        $second = Product::factory()->create(['is_active' => true]);
        $this->withSession(['compare.products' => [$first->slug, $second->slug]])
            ->post(route('compare.remove'), ['slug' => $first->slug])
            ->assertRedirect(route('compare.index'))->assertSessionHas('compare.products', [$second->slug]);

        $this->withSession(['compare.products' => [$first->slug, $second->slug]])
            ->postJson(route('compare.remove'), ['slug' => $first->slug])
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.slug', $second->slug);
        $this->withSession(['compare.products' => [$second->slug]])
            ->postJson(route('compare.remove'), ['slug' => $second->slug])
            ->assertOk()->assertJsonPath('count', 0)->assertJsonPath('items', []);
        $this->withSession(['compare.products' => [$first->slug]])
            ->postJson(route('compare.remove'), ['product_id' => $first->id])
            ->assertOk()->assertJsonPath('count', 0);
        $this->postJson(route('compare.remove'), ['product_id' => 'invalid'])->assertUnprocessable();
    }

    public function test_scope_uses_active_category_and_falls_back_when_inactive(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Cassette', 'is_active' => true, 'is_indexable' => true]);
        $settings = app(SettingService::class);
        $settings->set('product_scope', 'primary_category', 'site');
        $settings->set('primary_product_category_id', (string) $category->id, 'site');
        $this->get(route('compare.index'))->assertOk()->assertSee('So sánh Cassette')
            ->assertSee(route('category.show', $category->slug));
        $first = Product::factory()->create(['product_category_id' => $category->id, 'is_active' => true]);
        $second = Product::factory()->create(['is_active' => true]);
        $this->withSession(['compare.products' => [$first->slug, $second->slug]])
            ->get(route('compare.index'))->assertOk()->assertSee('<h1 class="text-2xl font-extrabold text-surface-900 sm:text-3xl">So sánh sản phẩm</h1>', false);
        $category->update(['is_active' => false]);
        $this->get(route('compare.index'))->assertOk()->assertSee('So sánh sản phẩm')
            ->assertSee(route('products.index'));
    }

    public function test_product_cta_resolves_semantic_targets_and_rejects_unsafe_urls(): void
    {
        $brand = Brand::factory()->create(['is_active' => true]);
        $settings = app(SettingService::class);
        $scope = app(\App\Services\Navigation\SiteProductScope::class);
        $settings->set('product_cta_type', 'brand', 'site');
        $settings->set('product_cta_brand', (string) $brand->id, 'site');
        $this->assertSame(route('brands.show', $brand->slug), $scope->productUrl());
        $brand->update(['is_active' => false]);
        $this->assertSame(route('products.index'), $scope->productUrl());
        $settings->set('product_cta_type', 'custom_url', 'site');
        $settings->set('product_cta_custom_url', '//example.org', 'site');
        $this->assertSame(route('products.index'), $scope->productUrl());
        $settings->set('product_cta_type', 'phone', 'site');
        $settings->set('product_cta_phone', '+84 123 456 789', 'site');
        $this->assertSame('tel:+84123456789', $scope->productUrl());
        $settings->set('consultation_cta_type', 'route', 'site');
        $settings->set('consultation_cta_route', 'contact', 'site');
        $this->assertSame(route('contact'), $scope->consultationUrl());
        $settings->set('consultation_cta_type', 'custom_url', 'site');
        $settings->set('consultation_cta_custom_url', 'javascript:alert(1)', 'site');
        $settings->set('quote_cta_link', '', 'cta');
        $this->assertSame(route('quote.index'), $scope->consultationUrl());
    }

    public function test_generic_product_pages_and_feed_do_not_label_all_categories_floor_standing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/cassette.jpg', 'test');
        $category = ProductCategory::factory()->create(['name' => 'Cassette', 'is_active' => true]);
        Product::factory()->create([
            'name' => 'Cassette test', 'product_category_id' => $category->id,
            'main_image' => 'products/cassette.jpg', 'regular_price' => 100000,
            'product_type' => null, 'is_active' => true,
        ]);
        $this->get(route('products.index'))->assertOk()->assertDontSee('Điều hòa tủ đứng - Danh sách sản phẩm');
        $xml = app(\App\Services\Merchant\MerchantFeedService::class)->generateXml();
        $this->assertStringContainsString('<g:product_type>Điều Hòa > Cassette</g:product_type>', $xml);
        $this->assertStringNotContainsString('<g:product_type>Điều Hòa Tủ Đứng', $xml);
    }
}
