<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use App\Services\Product\ProductTechnicalFactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase1F2ARegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_adapter_prefers_marketing_capacity_when_available(): void
    {
        $product = new Product;
        $product->setRawAttributes(['marketing_capacity_btu' => 42000, 'btu' => 42650]);

        $this->assertSame(42000, app(ProductMarketingCapacityQueryAdapter::class)->value($product));
    }

    public function test_capacity_adapter_marks_legacy_fallback_as_display_only(): void
    {
        $product = new Product;
        $product->setRawAttributes(['btu' => 42000]);
        $adapter = app(ProductMarketingCapacityQueryAdapter::class);

        $this->assertSame(42000, $adapter->value($product));
        $this->assertContains($adapter->mode(), ['LEGACY_DISPLAY_ONLY', 'MARKETING_CAPACITY_CANONICAL']);
    }

    public function test_legacy_btu_is_never_a_verified_technical_fact(): void
    {
        $product = new Product;
        $product->setRawAttributes(['btu' => 42650]);

        $this->assertArrayNotHasKey('technical_capacity_btu', app(ProductTechnicalFactResolver::class)->allVerified($product));
    }

    public function test_legacy_catalog_sync_command_is_hard_disabled(): void
    {
        $exitCode = Artisan::call('products:sync-specs-from-catalog', ['--batch' => 'test']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Legacy technical write path disabled', Artisan::output());
    }

    public function test_legacy_catalog_rollback_command_is_hard_disabled(): void
    {
        $exitCode = Artisan::call('products:rollback-catalog-specs');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Legacy technical write path disabled', Artisan::output());
    }
}
