<?php

namespace Tests\Feature;

use App\Models\DataImportJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductDataEditContractTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file);
        }
        parent::tearDown();
    }

    private function preview(array $rows): DataImportJob
    {
        $path = 'test-edit-'.bin2hex(random_bytes(8)).'.csv';
        $this->files[] = $path;
        $stream = fopen(Storage::disk('local')->path($path), 'wb');
        fputcsv($stream, array_keys($rows[0]), escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), escape: '');
        }
        fclose($stream);
        $job = app(DataImportService::class)->uploadAndPreview('product', Storage::disk('local')->path($path), 'edit.csv', 'csv', 'update', 'sku', intent: 'business');
        $this->files[] = $job->file_path;

        return $job;
    }

    public function test_commercial_edit_does_not_require_catalog_schema_or_modify_governance(): void
    {
        $category = ProductCategory::factory()->create(['technical_schema_status' => 'missing', 'technical_schema_json' => null]);
        $product = Product::factory()->create(['product_category_id' => $category->id, 'ai_status' => 'approved', 'sale_price' => 500, 'technical_capacity_btu' => 16400]);
        $job = $this->preview([['sku' => $product->sku, 'sale_price' => '', 'short_description' => "First line\nSecond line"]]);
        $this->assertSame(0, $job->failed_rows, json_encode($job->error_report_json));
        $result = app(DataImportService::class)->confirmImport($job);
        $this->assertSame('completed', $result->status, json_encode($result->error_report_json));
        $this->assertNull($product->fresh()->sale_price);
        $this->assertSame("First line\nSecond line", $product->fresh()->short_description);
        $this->assertSame('approved', $product->fresh()->ai_status);
        $this->assertSame(16400, $product->fresh()->technical_capacity_btu);
    }

    public function test_runtime_failure_rolls_back_entire_product_edit_and_receipts(): void
    {
        $category = ProductCategory::factory()->create(['technical_schema_status' => 'active', 'technical_schema_json' => ['allowed_fields' => ['btu'], 'required_fields' => []]]);
        $first = Product::factory()->create(['product_category_id' => $category->id, 'regular_price' => 100]);
        $second = Product::factory()->create(['product_category_id' => $category->id, 'regular_price' => 100]);
        $job = $this->preview([['sku' => $first->sku, 'regular_price' => '200'], ['sku' => $second->sku, 'regular_price' => '300']]);
        Product::saving(function (Product $product) use ($second): void {
            if ($product->id === $second->id && (float) $product->regular_price === 300.0) {
                throw new \RuntimeException('Injected import write failure');
            }
        });
        try {
            $result = app(DataImportService::class)->confirmImport($job);
            $this->assertSame('failed', $result->status);
            $this->assertEquals(100, $first->fresh()->regular_price);
            $this->assertEquals(100, $second->fresh()->regular_price);
            $this->assertSame(0, DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count());
        } finally {
            Product::flushEventListeners();
            Product::clearBootedModels();
        }
    }

    public function test_normal_xlsx_export_keeps_formula_text_literal(): void
    {
        $product = Product::factory()->create(['name' => '=1+1']);
        $export = app(DataExportService::class)->export('product', 'xlsx', ['basic'], selectedIds: [$product->id], scope: 'selected', intent: 'normal');
        $this->files[] = $export->file_path;
        $book = IOFactory::load(Storage::disk('local')->path($export->file_path));
        $this->assertSame(DataType::TYPE_STRING, $book->getActiveSheet()->getCell('B2')->getDataType());
        $this->assertSame('=1+1', $book->getActiveSheet()->getCell('B2')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_selected_and_filtered_edit_exports_round_trip_the_expected_subset(): void
    {
        $category = ProductCategory::factory()->create();
        $first = Product::factory()->create(['product_category_id' => $category->id, 'is_active' => true]);
        $second = Product::factory()->create(['product_category_id' => $category->id, 'is_active' => false]);
        foreach ([['selected', [$second->id], [], $second], ['filtered', [], ['is_active' => true], $first]] as [$scope, $ids, $filters, $product]) {
            $export = app(DataExportService::class)->export('product', 'csv', filters: $filters, selectedIds: $ids, scope: $scope, intent: 'business_edit');
            $this->files[] = $export->file_path;
            $service = app(DataImportService::class);
            $job = $service->uploadAndPreview('product', Storage::disk('local')->path($export->file_path), 'edit.csv', 'csv', 'upsert', 'sku', intent: 'business');
            $this->files[] = $job->file_path;
            $this->assertSame(1, $job->total_rows);
            $this->assertSame($product->sku, $job->preview_data_json[0]['data']['sku']);
            $this->assertSame('completed', $service->confirmImport($job)->status, json_encode($job->error_report_json));
        }
    }

    public function test_product_import_waits_for_other_product_job_and_rejects_stale_preview(): void
    {
        $product = Product::factory()->create(['regular_price' => 100]);
        $first = $this->preview([['sku' => $product->sku, 'regular_price' => '200']]);
        $second = $this->preview([['sku' => $product->sku, 'regular_price' => '300']]);
        $service = app(DataImportService::class);
        $lock = Cache::lock('product-import:execute', 30);
        $lock->get();
        try {
            $service->confirmImport($second);
            $this->fail('Concurrent import must wait for the active Product import.');
        } catch (LockTimeoutException) {
            $this->assertSame('previewing', $second->fresh()->status);
        } finally {
            $lock->release();
        }
        $this->assertSame('completed', $service->confirmImport($first)->status);
        $this->assertSame('failed', $service->confirmImport($second)->status);
        $this->assertEquals(200, $product->fresh()->regular_price);
    }

    public function test_filtered_export_with_no_matches_is_empty(): void
    {
        Product::factory()->create(['is_active' => true]);
        $export = app(DataExportService::class)->export('product', 'csv', filters: ['is_active' => false], scope: 'filter', intent: 'business_edit');
        $this->files[] = $export->file_path;
        $contents = Storage::disk('local')->get($export->file_path);
        $this->assertSame(1, substr_count($contents, "\n"));
    }
}
