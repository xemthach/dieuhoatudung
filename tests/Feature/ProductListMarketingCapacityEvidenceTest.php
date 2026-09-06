<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CatalogModelField;
use App\Models\Product;
use App\Services\Product\ProductListMarketingCapacityEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductListMarketingCapacityEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) @unlink($file);
        parent::tearDown();
    }

    public function test_exact_product_list_rows_create_read_only_source_backed_proposals(): void
    {
        $brand = Brand::factory()->create(['slug' => 'gree']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id, 'sku' => 'GUD50-A', 'model_code' => 'GUD50/A',
            'marketing_capacity_btu' => null, 'technical_capacity_btu' => 16400,
        ]);
        $path = $this->workbook([
            ['brand_slug', 'sku', 'model_code', 'marketing_capacity_btu', 'source_text'],
            ['gree', 'GUD50-A', 'GUD50/A', '18000', 'Official commercial tier 18,000 BTU'],
        ]);

        $before = $product->fresh()->getAttributes();
        $result = app(ProductListMarketingCapacityEvidenceService::class)->inspect($path);

        $this->assertTrue($result['read_only']);
        $this->assertSame(1, $result['stats']['propose_evidence']);
        $this->assertSame('PROPOSE_EVIDENCE', $result['rows'][0]['action']);
        $this->assertSame(18000, $result['rows'][0]['proposed_marketing']);
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(0, CatalogModelField::count());
    }

    public function test_persisted_evidence_is_idempotent_and_does_not_mutate_product(): void
    {
        $brand = Brand::factory()->create(['slug' => 'gree']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id, 'sku' => 'GUD71-A', 'model_code' => 'GUD71/A',
            'marketing_capacity_btu' => null, 'technical_capacity_btu' => 24200,
        ]);
        $path = $this->workbook([
            ['brand_slug', 'sku', 'model_code', 'marketing_capacity_btu', 'source_text'],
            ['gree', 'GUD71-A', 'GUD71/A', '24000', 'Official commercial tier 24,000 BTU'],
        ]);
        $service = app(ProductListMarketingCapacityEvidenceService::class);
        $inspection = $service->inspect($path);

        $first = $service->persist($inspection, 'Official Gree Product List', '2026', 'batch-one');
        $second = $service->persist($inspection, 'Official Gree Product List', '2026', 'batch-two');

        $this->assertSame(1, $first['createdFields']);
        $this->assertSame(1, $second['keptFields']);
        $this->assertNull($product->fresh()->marketing_capacity_btu);
        $this->assertSame(1, CatalogModelField::where('field_key', 'marketing_capacity_btu')->count());
        $audit = app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit($product->id);
        $this->assertSame('PROPOSE_UPDATE', $audit['products'][0]['action']);
        $this->assertSame(24000, $audit['products'][0]['proposed_marketing']);
    }

    public function test_missing_ambiguous_duplicate_and_conflicting_rows_are_blocked(): void
    {
        $brand = Brand::factory()->create(['slug' => 'gree']);
        Product::factory()->create(['brand_id' => $brand->id, 'sku' => 'DUP-A', 'model_code' => 'DUP-MODEL', 'marketing_capacity_btu' => null]);
        Product::factory()->create(['brand_id' => $brand->id, 'sku' => 'DUP-B', 'model_code' => 'DUP-MODEL', 'marketing_capacity_btu' => null]);
        Product::factory()->create(['brand_id' => $brand->id, 'sku' => 'EXISTING', 'model_code' => 'C', 'marketing_capacity_btu' => 18000]);
        Product::factory()->create(['brand_id' => $brand->id, 'sku' => 'TWICE', 'model_code' => 'D', 'marketing_capacity_btu' => null]);
        $path = $this->workbook([
            ['brand_slug', 'sku', 'model_code', 'marketing_capacity_btu'],
            ['gree', 'MISSING', '', '18000'],
            ['gree', '', 'DUP-MODEL', '18000'],
            ['gree', 'EXISTING', 'C', '24000'],
            ['gree', 'TWICE', 'D', '18000'],
            ['gree', 'TWICE', 'D', '18000'],
        ]);

        $result = app(ProductListMarketingCapacityEvidenceService::class)->inspect($path);

        $this->assertSame(1, $result['stats']['no_evidence']);
        $this->assertSame(1, $result['stats']['ambiguous']);
        $this->assertSame(3, $result['stats']['conflict']);
        $this->expectException(\RuntimeException::class);
        app(ProductListMarketingCapacityEvidenceService::class)->persist($result, 'Blocked list', '2026', 'blocked');
    }

    private function workbook(array $rows): string
    {
        $path = storage_path('app/private/test-product-list-'.bin2hex(random_bytes(5)).'.xlsx');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('PRODUCT_LIST')->fromArray($rows);
        IOFactory::createWriter($book, 'Xlsx')->save($path);
        $book->disconnectWorksheets();
        $this->files[] = $path;
        return $path;
    }
}
