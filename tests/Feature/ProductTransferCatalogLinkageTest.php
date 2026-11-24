<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ProductSystemRestoreContract;
use App\Services\DataTransfer\ProductTransferContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductTransferCatalogLinkageTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        Storage::disk('local')->delete($this->files);
        parent::tearDown();
    }

    public function test_catalog_linkage_maps_different_ids_by_source_hash_and_normalized_identity_without_losing_provenance(): void
    {
        [$product, $source, $model, $brand, $category] = $this->fixture();
        $specs = $product->specs_json;
        $path = $this->export($product);
        $product->forceDelete();
        $source->delete();
        $brand->forceDelete();
        $category->forceDelete();
        $brand = Brand::factory()->create(['slug' => 'catalog-transfer-brand']);
        $category = ProductCategory::factory()->create(['slug' => 'catalog-transfer-category']);
        [$targetSource, $targetModel] = $this->targetCatalog($brand, $category);

        $job = $this->preview($path);
        $this->assertSame(0, $job->failed_rows, json_encode($job->error_report_json));
        $this->assertSame(1, data_get($job->format_context_json, 'preview_summary.catalog_lineage.remapped'));
        $this->assertArrayHasKey('target_hashes', $job->format_context_json);
        $result = app(DataImportService::class)->confirmImport($job);
        $this->assertSame('completed', $result->status, json_encode($result->error_report_json));
        $target = Product::where('sku', 'CATALOG-TRANSFER-18')->firstOrFail();
        $this->assertNotSame($source->id, $targetSource->id);
        $this->assertNotSame($model->id, $targetModel->id);
        $this->assertSame($targetSource->id, $target->catalog_source_id);
        $this->assertSame($targetModel->id, $target->catalog_model_id);
        $this->assertSame('catalog_verified_specs', $target->technical_specs_source);
        $this->assertSame('verified', $target->catalog_match_status);
        $this->assertSame('verified', $target->technical_capacity_status);
        $this->assertSame($specs, $target->specs_json);
        $this->assertSame(17100, $target->technical_capacity_btu);
        $this->assertSame(18000, $target->marketing_capacity_btu);
    }

    public function test_missing_or_ambiguous_catalog_sources_fail_closed(): void
    {
        [$product, $source, $_model, $brand, $category] = $this->fixture();
        $path = $this->export($product);
        $product->forceDelete();
        $source->delete();
        $missing = $this->preview($path);
        $this->assertSame(1, $missing->failed_rows);
        $this->assertSame(1, data_get($missing->format_context_json, 'preview_summary.catalog_lineage.blocked'));
        $this->targetCatalog($brand, $category);
        $this->targetCatalog($brand, $category);
        $ambiguous = $this->preview($path);
        $this->assertSame(1, $ambiguous->failed_rows);
        $this->assertSame('blocked', app(DataImportService::class)->confirmImport($ambiguous)->status);
        $this->assertSame(0, Product::count());
    }

    public function test_ambiguous_or_conflicting_normalized_model_and_sku_are_rejected(): void
    {
        [$product, $source, $model] = $this->fixture();
        $path = $this->export($product);
        $product->forceDelete();
        CatalogModel::create([
            'catalog_source_id' => $source->id, 'normalized_model' => $model->normalized_model,
            'normalized_sku' => 'OTHER-SKU',
        ]);
        $ambiguous = $this->preview($path);
        $this->assertSame(1, $ambiguous->failed_rows);
        CatalogModel::whereKey($model->id)->update(['normalized_sku' => 'REPLACED-SKU']);
        CatalogModel::where('normalized_sku', 'OTHER-SKU')->update([
            'normalized_model' => 'OTHER-MODEL', 'normalized_sku' => 'CATALOGTRANSFER18',
        ]);
        $conflict = $this->preview($path);
        $this->assertSame(1, $conflict->failed_rows);
        $this->assertStringContainsString('different models', json_encode($conflict->error_report_json));
    }

    public function test_same_hash_in_a_different_brand_or_category_is_not_a_match(): void
    {
        [$product, $source] = $this->fixture();
        $path = $this->export($product);
        $product->forceDelete();
        $source->delete();
        $otherBrand = Brand::factory()->create();
        $otherCategory = ProductCategory::factory()->create();
        $this->targetCatalog($otherBrand, $otherCategory);

        $job = $this->preview($path);
        $this->assertSame(1, $job->failed_rows);
        $this->assertSame('blocked', app(DataImportService::class)->confirmImport($job)->status);
    }

    public function test_missing_model_cannot_fall_back_to_a_numeric_id_or_another_catalog_source(): void
    {
        [$product, $_source, $model, $brand, $category] = $this->fixture();
        $path = $this->export($product);
        $product->forceDelete();
        $model->delete();
        // The same normalized model exists elsewhere. Its evidence belongs to
        // a different source hash and must never satisfy the exported link.
        [$otherSource] = $this->targetCatalog($brand, $category);
        $otherSource->update(['source_sha256' => str_repeat('b', 64)]);
        $job = $this->preview($path);
        $this->assertSame(1, $job->failed_rows);
        $this->assertStringContainsString('within the verified source', json_encode($job->error_report_json));
        $this->assertSame('blocked', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertSame(0, Product::count());
    }

    public function test_deleted_reference_or_changed_catalog_after_preview_blocks_the_entire_transfer(): void
    {
        [$product, $source, $model, $brand] = $this->fixture();
        $path = $this->export($product);
        $product->forceDelete();
        $job = $this->preview($path);
        $this->assertSame(0, $job->failed_rows);
        $model->update(['verification_status' => 'modified-after-preview']);
        $result = app(DataImportService::class)->confirmImport($job);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('Preview', json_encode($result->error_report_json));
        $this->assertSame(0, Product::count());
        $brand->delete();
        $deleted = $this->preview($path);
        $this->assertSame(1, $deleted->failed_rows);
    }

    public function test_v1_lineage_cannot_use_numeric_id_coincidence_but_unlinked_v1_remains_compatible(): void
    {
        [$product] = $this->fixture();
        $path = $this->legacyV1($this->export($product));
        $product->forceDelete();
        $legacy = $this->preview($path);
        $this->assertSame(1, $legacy->failed_rows);
        $this->assertStringContainsString('v1 lineage cannot be verified', json_encode($legacy->error_report_json));
        $this->assertSame('blocked', app(DataImportService::class)->confirmImport($legacy)->status);

        $unlinked = Product::factory()->create(['sku' => 'LEGACY-UNLINKED', 'slug' => 'legacy-unlinked']);
        $path = $this->legacyV1($this->export($unlinked));
        $unlinked->forceDelete();
        $job = $this->preview($path);
        $this->assertSame(0, $job->failed_rows, json_encode($job->error_report_json));
        $this->assertSame('completed', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertSame('PRODUCT_TRANSFER', Product::where('sku', 'LEGACY-UNLINKED')->sole()->technical_specs_source);
    }

    public function test_export_refuses_catalog_linkage_without_stable_source_hash(): void
    {
        [$product, $source] = $this->fixture();
        $source->update(['source_sha256' => null]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source_sha256');
        $this->export($product->fresh());
    }

    private function fixture(): array
    {
        $brand = Brand::factory()->create(['slug' => 'catalog-transfer-brand']);
        $category = ProductCategory::factory()->create(['slug' => 'catalog-transfer-category']);
        [$source, $model] = $this->targetCatalog($brand, $category);
        $product = Product::factory()->create([
            'sku' => 'CATALOG-TRANSFER-18', 'slug' => 'catalog-transfer-18',
            'brand_id' => $brand->id, 'product_category_id' => $category->id,
            'catalog_source_id' => $source->id, 'catalog_model_id' => $model->id,
            'catalog_match_status' => 'verified', 'technical_specs_source' => 'catalog_verified_specs',
            'technical_capacity_status' => 'verified', 'technical_capacity_btu' => 17100, 'marketing_capacity_btu' => 18000,
            'specs_json' => [['key' => 'capacity_kw', 'value' => '5.00', 'source_pdf' => 'catalog.pdf',
                'source_sha256' => str_repeat('a', 64), 'source_section' => 'TECHNICAL_APPENDIX', 'source_page' => 12]],
        ]);
        return [$product, $source, $model, $brand, $category];
    }

    private function targetCatalog(Brand $brand, ProductCategory $category): array
    {
        $source = CatalogSource::create([
            'brand_id' => $brand->id, 'category_id' => $category->id,
            'source_name' => 'Catalog Transfer Evidence', 'source_type' => 'pdf', 'source_sha256' => str_repeat('a', 64),
        ]);
        $model = CatalogModel::create([
            'catalog_source_id' => $source->id, 'model' => 'CATALOG-18', 'sku' => 'CATALOG-TRANSFER-18',
            'normalized_model' => 'CATALOG18', 'normalized_sku' => 'CATALOGTRANSFER18',
        ]);
        return [$source, $model];
    }

    private function export(Product $product): string
    {
        $export = app(DataExportService::class)->export('product', 'xlsx', selectedIds: [$product->id], scope: 'selected', intent: 'product_transfer');
        $this->files[] = $export->file_path;
        return Storage::disk('local')->path($export->file_path);
    }

    private function preview(string $path): \App\Models\DataImportJob
    {
        $job = app(DataImportService::class)->uploadAndPreview('product', $path, 'catalog-transfer.xlsx', 'xlsx');
        $this->files[] = $job->file_path;
        return $job;
    }

    /** Construct a genuine v1 contract to lock down compatibility behavior. */
    private function legacyV1(string $path): string
    {
        $book = IOFactory::load($path);
        $data = $book->getSheetByName('Data');
        $fields = ProductTransferContract::fields(1);
        $values = $data->toArray(null, false, false, false);
        $rows = collect(array_slice($values, 1))->map(fn (array $row): array => array_combine($fields, array_slice($row, 0, count($fields))));
        $data->removeColumnByIndex(count($fields) + 1, 5);
        $metadata = ProductTransferContract::metadata($fields, $rows, 'selected');
        $metadata['format_version'] = '1';
        $metadata['content_sha256'] = ProductSystemRestoreContract::contentChecksum($fields, $rows, 1);
        $metaSheet = $book->getSheetByName(ProductTransferContract::METADATA_SHEET);
        foreach (array_values($metadata) as $index => $value) {
            $metaSheet->setCellValueExplicit([2, $index + 2], (string) $value, DataType::TYPE_STRING);
        }
        IOFactory::createWriter($book, 'Xlsx')->save($path);
        $book->disconnectWorksheets();
        return $path;
    }
}
