<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogModelField;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Product\ProductImportMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogSpecsGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_specs_match_catalog_after_normalization(): void
    {
        Storage::fake('local');

        [$product] = $this->catalogProductPair(
            product: ['btu' => 24000],
            fields: [['field_key' => 'btu', 'field_value' => '24.000 BTU', 'unit' => 'BTU']]
        );

        $this->artisan('products:audit-catalog-specs --report')
            ->expectsOutputToContain('Product vs Catalog Specs Audit')
            ->expectsOutputToContain('correct')
            ->assertSuccessful();

        $this->assertSame(24000, $product->fresh()->btu);
        $payload = json_decode(Storage::disk('local')->get(Storage::disk('local')->allFiles('reports')[0]), true);
        $this->assertSame(1, $payload['summary']['correct']);
        $this->assertFalse($payload['source_policy']['external_sources_used']);
        $this->assertFalse($payload['source_policy']['auto_fix_applied']);
    }

    public function test_product_value_differs_from_catalog_reports_mismatch(): void
    {
        $this->catalogProductPair(
            product: ['specs_json' => [['key' => 'esp', 'value' => '160 Pa']]],
            fields: [['field_key' => 'esp', 'field_value' => '50 Pa', 'unit' => 'Pa']]
        );

        $this->artisan('products:audit-catalog-specs')
            ->expectsOutputToContain('mismatched_value')
            ->assertSuccessful();
    }

    public function test_product_extra_field_is_suspicious_when_catalog_does_not_have_it(): void
    {
        $this->catalogProductPair(
            product: ['recommended_area' => '60m2'],
            fields: [['field_key' => 'btu', 'field_value' => '24000 BTU', 'unit' => 'BTU']]
        );

        $this->artisan('products:audit-catalog-specs')
            ->expectsOutputToContain('suspicious_ai_generated')
            ->assertSuccessful();
    }

    public function test_product_missing_catalog_field_is_reported(): void
    {
        $this->catalogProductPair(
            product: ['btu' => null],
            fields: [['field_key' => 'btu', 'field_value' => '24000 BTU', 'unit' => 'BTU']]
        );

        $this->artisan('products:audit-catalog-specs')
            ->expectsOutputToContain('product_missing_specs')
            ->assertSuccessful();
    }

    public function test_wrong_unit_is_reported_when_product_value_lacks_catalog_unit(): void
    {
        $this->catalogProductPair(
            product: ['weight' => '0.7'],
            fields: [['field_key' => 'weight', 'field_value' => '0.7 kg', 'unit' => 'kg']]
        );

        $this->artisan('products:audit-catalog-specs')
            ->expectsOutputToContain('wrong_unit')
            ->assertSuccessful();
    }

    public function test_unknown_import_field_is_rejected_in_catalog_governance_mode(): void
    {
        $result = app(ProductImportMapper::class)->mapWithGovernance([
            'btu' => '24000',
            'unknown_ai_area' => '60m2',
        ], ['btu']);

        $this->assertSame(['btu' => 24000], $result['attributes']);
        $this->assertArrayHasKey('unknown_ai_area', $result['rejected']);
        $this->assertContains('unknown_catalog_field: unknown_ai_area', $result['warnings']);
    }

    public function test_fix_from_catalog_is_dry_run_only(): void
    {
        Storage::fake('local');
        $this->catalogProductPair(
            product: ['specs_json' => [['key' => 'esp', 'value' => '160 Pa']]],
            fields: [['field_key' => 'esp', 'field_value' => '50 Pa', 'unit' => 'Pa', 'source_page' => 3]]
        );

        $this->artisan('products:fix-from-catalog --dry-run --report')
            ->expectsOutputToContain('Fix From Catalog')
            ->expectsOutputToContain('50 Pa')
            ->assertSuccessful();

        $payload = json_decode(Storage::disk('local')->get(Storage::disk('local')->allFiles('reports')[0]), true);
        $this->assertTrue($payload['dry_run']);
        $this->assertFalse($payload['auto_fix_applied']);
        $this->assertSame(3, $payload['rows'][0]['changes'][0]['source_page']);
    }

    private function catalogProductPair(array $product, array $fields): array
    {
        $brand = Brand::factory()->create();
        $category = ProductCategory::factory()->create();

        $source = CatalogSource::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'source_name' => 'Imported test catalog',
            'source_type' => 'xlsx',
            'version' => 'test',
            'parsed_status' => 'verified',
            'imported_status' => 'verified',
        ]);

        $catalogModel = CatalogModel::query()->create([
            'catalog_source_id' => $source->id,
            'model' => 'ABC-24',
            'sku' => 'SKU-24',
            'normalized_model' => 'ABC24',
            'normalized_sku' => 'SKU24',
            'source_page' => 1,
            'confidence_score' => 1,
            'import_status' => 'verified',
        ]);

        foreach ($fields as $field) {
            CatalogModelField::query()->create([
                'catalog_model_id' => $catalogModel->id,
                'field_key' => $field['field_key'],
                'field_label' => $field['field_label'] ?? $field['field_key'],
                'field_value' => $field['field_value'],
                'unit' => $field['unit'] ?? null,
                'source_page' => $field['source_page'] ?? 1,
                'confidence_score' => 1,
            ]);
        }

        $createdProduct = Product::factory()->create(array_merge([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'catalog_source_id' => $source->id,
            'catalog_model_id' => $catalogModel->id,
            'catalog_match_status' => 'matched',
            'model_code' => 'ABC-24',
            'sku' => 'SKU-24',
            'btu' => null,
            'capacity_kw' => null,
            'hp' => null,
            'inverter' => false,
            'weight' => null,
            'recommended_area' => null,
            'specs_json' => null,
        ], $product));

        return [$createdProduct, $catalogModel, $source];
    }
}
