<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Services\AI\AISeoContentGenerator;
use App\Services\SlugGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vietnamese_title_is_normalized_to_slug(): void
    {
        $slug = app(SlugGeneratorService::class)->normalize('Điều hòa đặt sàn/áp trần');

        $this->assertSame('dieu-hoa-dat-san-ap-tran', $slug);
    }

    public function test_duplicate_category_slug_gets_suffix_before_database_error(): void
    {
        ProductCategory::create(['name' => 'Điều hòa tủ đứng']);

        $category = ProductCategory::create(['name' => 'Điều hòa tủ đứng']);

        $this->assertSame('dieu-hoa-tu-dung-2', $category->slug);
    }

    public function test_manual_slug_is_not_overwritten_when_title_changes(): void
    {
        $category = ProductCategory::create([
            'name' => 'Điều hòa tủ đứng',
            'slug' => 'slug-tu-nhap',
        ]);

        $category->update(['name' => 'Điều hòa đặt sàn áp trần']);

        $this->assertSame('slug-tu-nhap', $category->fresh()->slug);
    }

    public function test_generate_product_category_seo_content(): void
    {
        $output = app(AISeoContentGenerator::class)->generate('product_category', [
            'title' => 'Điều hòa đặt sàn/áp trần',
            'category_type' => 'main',
        ], ['short_description', 'detailed_content', 'seo_title', 'meta_description', 'og_title', 'og_description']);

        $this->assertArrayHasKey('seo_title', $output);
        $this->assertStringContainsString('Điều hòa đặt sàn/áp trần', $output['short_description']);
        $this->assertStringContainsString('HVAC', $output['detailed_content']);
    }

    public function test_generate_brand_intro(): void
    {
        Brand::create(['name' => 'GREE']);

        $output = app(AISeoContentGenerator::class)->generate('brand', [
            'title' => 'GREE',
        ], ['brand_introduction', 'seo_title', 'meta_description', 'og_title', 'og_description']);

        $this->assertStringContainsString('GREE', $output['brand_introduction']);
        $this->assertArrayHasKey('meta_description', $output);
    }

    public function test_generate_promotion_cta(): void
    {
        $output = app(AISeoContentGenerator::class)->generate('promotion', [
            'title' => 'Ưu đãi điều hòa công trình',
            'placement' => 'banner',
        ], ['promotion_description', 'cta_content', 'banner_copy', 'seo_title', 'og_description']);

        $this->assertStringContainsString('Ưu đãi điều hòa công trình', $output['promotion_description']);
        $this->assertArrayHasKey('cta_content', $output);
        $this->assertArrayHasKey('banner_copy', $output);
    }

    public function test_ai_output_is_clean_utf8(): void
    {
        $output = app(AISeoContentGenerator::class)->generate('brand', [
            'title' => 'GREE',
        ], ['brand_introduction']);

        $this->assertTrue(mb_check_encoding($output['brand_introduction'], 'UTF-8'));
        $this->assertStringNotContainsString('Ã', $output['brand_introduction']);
    }

    public function test_ai_output_does_not_invent_specs_discount_or_vat(): void
    {
        $output = app(AISeoContentGenerator::class)->generate('promotion', [
            'title' => 'Chiến dịch HVAC tháng này',
        ], ['promotion_description', 'cta_content', 'banner_copy', 'meta_description']);

        $text = mb_strtolower(implode(' ', $output));

        $this->assertDoesNotMatchRegularExpression('/\d+\s*(btu|kw|hp|m2|%)/iu', $text);
        $this->assertStringNotContainsString('vat', $text);
        $this->assertStringNotContainsString('miễn phí', $text);
        $this->assertStringNotContainsString('co/cq', $text);
    }
}
