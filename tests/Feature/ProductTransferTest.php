<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\CatalogSource;
use App\Models\CatalogModel;
use App\Models\SiteSetting;
use App\Services\Settings\SettingService;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ProductTransferContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_product_transfer_is_signed_and_maps_brand_category_by_slug(): void
    {
        $brand = Brand::factory()->create(['slug' => 'transfer-brand']);
        $category = ProductCategory::factory()->create(['slug' => 'transfer-category', 'technical_schema_status' => 'missing']);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'sku' => 'TRANSFER-18000', 'slug' => 'transfer-18000', 'marketing_capacity_btu' => 18000, 'technical_capacity_btu' => 16400, 'capacity_kw' => '4.80']);

        $export = app(DataExportService::class)->export('product', 'xlsx', [], [], [$product->id], 'selected', null, 'product_transfer');
        $path = storage_path('app/private/'.$export->file_path);
        $book = IOFactory::load($path);
        $this->assertNotNull($book->getSheetByName(ProductTransferContract::METADATA_SHEET));
        $this->assertSame(ProductTransferContract::fields(), array_map('strval', $book->getActiveSheet()->toArray(null, true, true, false)[0]));
        $book->disconnectWorksheets();

        $product->forceDelete();
        $job = app(DataImportService::class)->uploadAndPreview('product', $path, 'transfer.xlsx', 'xlsx');
        $this->assertSame('product_transfer', $job->mode);
        $this->assertSame('PRODUCT_TRANSFER', data_get($job->format_context_json, 'contract'));
        $this->assertSame(1, $job->success_rows);
        $completed = app(DataImportService::class)->confirmImport($job);
        $this->assertSame('completed', $completed->status);
        $restored = Product::where('sku', 'TRANSFER-18000')->firstOrFail();
        $this->assertSame($brand->id, $restored->brand_id);
        $this->assertSame($category->id, $restored->product_category_id);
        $this->assertSame(18000, $restored->marketing_capacity_btu);
        $this->assertSame(16400, $restored->technical_capacity_btu);
        Storage::disk('local')->delete([$export->file_path, $job->file_path]);
    }

    public function test_catalog_lineage_is_blocked_until_governed_detach_then_provenance_is_transfer(): void
    {
        $brand = Brand::factory()->create(['slug' => 'lineage-brand']);
        $category = ProductCategory::factory()->create(['slug' => 'lineage-category', 'technical_schema_status' => 'missing']);
        $source = CatalogSource::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'source_name' => 'Source environment catalog',
            'source_type' => 'xlsx',
        ]);
        $model = CatalogModel::create([
            'catalog_source_id' => $source->id,
            'model' => 'LINEAGE-18',
            'normalized_model' => 'LINEAGE18',
        ]);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'catalog_source_id' => $source->id,
            'catalog_model_id' => $model->id,
            'sku' => 'LINEAGE-18',
            'slug' => 'lineage-18',
            'technical_specs_source' => 'catalog_verified_specs',
            'marketing_capacity_btu' => 18000,
            'technical_capacity_btu' => 17100,
            'capacity_kw' => '5.00',
        ]);

        $export = app(DataExportService::class)->export('product', 'xlsx', [], [], [$product->id], 'selected', null, 'product_transfer');
        $path = storage_path('app/private/'.$export->file_path);
        $product->forceDelete();

        $blocked = app(DataImportService::class)->uploadAndPreview('product', $path, 'lineage-blocked.xlsx', 'xlsx');
        $this->assertSame(1, $blocked->failed_rows);
        $this->assertSame(1, data_get($blocked->format_context_json, 'preview_summary.catalog_lineage.blocked'));

        SiteSetting::updateOrCreate(
            ['group' => 'product_transfer', 'key' => 'detach_catalog_lineage'],
            ['value' => 'ON', 'type' => 'text', 'is_encrypted' => false],
        );
        app(SettingService::class)->forgetCache('product_transfer.detach_catalog_lineage');

        $allowed = app(DataImportService::class)->uploadAndPreview('product', $path, 'lineage-allowed.xlsx', 'xlsx');
        $this->assertSame(0, $allowed->failed_rows);
        $this->assertSame(1, data_get($allowed->format_context_json, 'preview_summary.catalog_lineage.detach_required'));
        $result = app(DataImportService::class)->confirmImport($allowed);
        $this->assertSame('completed', $result->status);
        $restored = Product::where('sku', 'LINEAGE-18')->firstOrFail();
        $this->assertNull($restored->catalog_source_id);
        $this->assertNull($restored->catalog_model_id);
        $this->assertSame('PRODUCT_TRANSFER', $restored->technical_specs_source);

        Storage::disk('local')->delete([$export->file_path, $blocked->file_path, $allowed->file_path]);
    }

    public function test_transfer_contract_supports_full_filtered_selected_and_current_page_scopes(): void
    {
        $brand = Brand::factory()->create(['slug' => 'scope-brand']);
        $category = ProductCategory::factory()->create(['slug' => 'scope-category', 'technical_schema_status' => 'missing']);
        $products = Product::factory()->count(4)->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
        ]);
        $cases = [
            'all' => [[], 4],
            'selected' => [$products->take(2)->pluck('id')->all(), 2],
            'current_page' => [$products->take(3)->pluck('id')->all(), 3],
            'filter' => [$products->skip(1)->take(2)->pluck('id')->all(), 2],
        ];

        foreach ($cases as $scope => [$ids, $expected]) {
            $export = app(DataExportService::class)->export('product', 'xlsx', [], [], $ids, $scope, null, 'product_transfer');
            $book = IOFactory::load(storage_path('app/private/'.$export->file_path));
            $metadataSheet = $book->getSheetByName(ProductTransferContract::METADATA_SHEET);
            $this->assertNotNull($metadataSheet, $scope);
            $metadata = collect($metadataSheet->toArray())->skip(1)->mapWithKeys(fn (array $row) => [(string) ($row[0] ?? '') => $row[1] ?? null]);
            $this->assertSame($expected, (int) $metadata->get('product_count'), $scope);
            $this->assertSame($expected, count($book->getSheetByName('Data')->toArray()) - 1, $scope);
            $book->disconnectWorksheets();
            Storage::disk('local')->delete($export->file_path);
        }
    }
}
