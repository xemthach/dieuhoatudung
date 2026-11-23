<?php

namespace Tests\Feature;

use App\Jobs\ExecuteDataImport;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) if (is_file($file)) unlink($file);
        parent::tearDown();
    }

    private function actor(): User
    {
        $actor = User::factory()->create(['is_active'=>true]);
        $actor->assignRole(Role::firstOrCreate(['name'=>'super_admin', 'guard_name'=>'web']));
        return $actor;
    }

    private function category(): ProductCategory
    {
        return ProductCategory::factory()->create(['is_active'=>true, 'technical_schema_status'=>'active', 'technical_schema_json'=>['fields'=>[['key'=>'btu', 'label'=>'BTU', 'type'=>'number']]]]);
    }

    private function preview(array $rows, string $mode = 'update', array $mapping = []): \App\Models\DataImportJob
    {
        $file = storage_path('framework/testing/business-'.bin2hex(random_bytes(8)).'.csv');
        $stream = fopen($file, 'wb');
        fputcsv($stream, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($stream, array_values($row));
        fclose($stream);
        $this->files[] = $file;
        $job = app(DataImportService::class)->uploadAndPreview('product', $file, 'business.csv', 'csv', $mode, 'sku', $this->actor()->id, 'business', $mapping);
        $this->files[] = Storage::disk('local')->path($job->file_path);
        return $job;
    }

    public function test_edited_business_export_round_trips_without_restore_metadata(): void
    {
        $category = $this->category();
        $brand = Brand::factory()->create(['is_active'=>true]);
        $product = Product::factory()->create(['product_category_id'=>$category->id, 'brand_id'=>$brand->id, 'regular_price'=>100000]);
        $export = app(DataExportService::class)->export('product', 'csv', selectedIds:[$product->id], scope:'selected', intent:'business_edit');
        $path = Storage::disk('local')->path($export->file_path);
        $this->files[] = $path;
        $csv = file_get_contents($path);
        $this->assertStringContainsString('category_slug', $csv);
        $this->assertStringNotContainsString('technical_capacity_status', $csv);
        $job = $this->preview([['SKU'=>$product->sku, 'Giá bán'=>'250000', 'category_slug'=>$category->slug]], mapping:['SKU'=>'sku', 'Giá bán'=>'regular_price']);
        $this->assertSame(0, $job->failed_rows);
        $before = $product->technical_capacity_btu;
        $result = app(DataImportService::class)->confirmImport($job);
        $this->assertSame('completed', $result->status, json_encode($result->error_report_json));
        $this->assertEquals(250000, $product->fresh()->regular_price);
        $this->assertSame($before, $product->fresh()->technical_capacity_btu);
        $this->assertSame(1, DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count());
    }

    public function test_changed_file_and_changed_target_never_write_after_preview(): void
    {
        $product = Product::factory()->create(['product_category_id'=>$this->category()->id, 'regular_price'=>100000]);
        $job = $this->preview([['sku'=>$product->sku, 'regular_price'=>'200000']]);
        file_put_contents(Storage::disk('local')->path($job->file_path), "\n", FILE_APPEND);
        $this->assertSame('failed', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertEquals(100000, $product->fresh()->regular_price);
        $job = $this->preview([['sku'=>$product->sku, 'regular_price'=>'300000']]);
        $product->update(['regular_price'=>150000]);
        $this->assertSame('failed', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertEquals(150000, $product->fresh()->regular_price);
    }

    public function test_duplicate_keys_bad_fk_and_protected_status_are_reported_before_writes(): void
    {
        $product = Product::factory()->create(['product_category_id'=>$this->category()->id]);
        foreach ([
            [['sku'=>$product->sku], ['sku'=>$product->sku]],
            [['sku'=>$product->sku, 'category_slug'=>'missing-category']],
            [['sku'=>$product->sku, 'technical_capacity_status'=>'verified']],
            [['sku'=>$product->sku, 'regular_prce'=>'12345']],
            [['sku'=>$product->sku, 'regular_price'=>'-1']],
            [['sku'=>$product->sku, 'is_active'=>'invalid-boolean']],
        ] as $rows) {
            $job = $this->preview($rows);
            $this->assertSame('failed', $job->status);
            $this->assertSame(0, DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count());
        }
    }

    public function test_matching_key_is_not_replaced_by_a_different_unique_identifier(): void
    {
        $product = Product::factory()->create(['product_category_id'=>$this->category()->id]);
        $job = $this->preview([['sku'=>'different-sku', 'slug'=>$product->slug, 'name'=>'Must not overwrite']], 'upsert');
        $this->assertSame('failed', $job->status);
        $this->assertNotSame('Must not overwrite', $product->fresh()->name);
    }

    public function test_mapping_can_skip_multiple_columns_without_importing_them(): void
    {
        $product = Product::factory()->create(['product_category_id'=>$this->category()->id]);
        $job = $this->preview([['sku'=>$product->sku, 'old_btu'=>'untrusted', 'note'=>'ignored', 'price'=>'123456']], mapping:['old_btu'=>'_skip', 'note'=>'_skip', 'price'=>'regular_price']);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame('completed', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertEquals(123456, $product->fresh()->regular_price);
    }

    public function test_large_import_is_queued_and_resumed_receipts_are_not_applied_twice(): void
    {
        Queue::fake();
        config(['queue.default'=>'database']);
        $category = $this->category();
        $rows = array_map(fn ($index) => ['sku'=>'QUEUE-'.$index, 'name'=>'Queued product '.$index, 'category_slug'=>$category->slug, 'regular_price'=>'222222'], range(1, 501));
        $job = $this->preview($rows, 'create');
        $this->assertSame(501, $job->total_rows);
        $this->assertSame('queued', app(DataImportService::class)->confirmImport($job)->status);
        Queue::assertPushedOn('default', ExecuteDataImport::class);
        (new ExecuteDataImport($job->id))->handle(app(DataImportService::class));
        $this->assertSame('completed', $job->fresh()->status);
        $product = Product::where('sku', 'QUEUE-1')->firstOrFail();
        $product->refresh()->update(['regular_price'=>333333]);
        $job->update(['status'=>'importing']);
        (new ExecuteDataImport($job->id))->handle(app(DataImportService::class));
        $this->assertEquals(333333, $product->fresh()->regular_price);
        $this->assertSame(501, DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count());
    }

    public function test_revoked_operator_permission_blocks_execution(): void
    {
        $product = Product::factory()->create(['product_category_id'=>$this->category()->id]);
        $job = $this->preview([['sku'=>$product->sku, 'regular_price'=>'12345']]);
        User::findOrFail($job->created_by)->update(['is_active'=>false]);
        $this->assertSame('failed', app(DataImportService::class)->confirmImport($job)->status);
        $this->assertSame(0, DB::table('data_import_receipts')->where('data_import_job_id', $job->id)->count());
    }

    public function test_import_export_history_is_scoped_to_the_operator(): void
    {
        $actor = User::factory()->create(['is_active'=>true]);
        foreach (['product.import', 'product.export'] as $permission) {
            $actor->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name'=>$permission, 'guard_name'=>'web']));
        }
        $other = $this->actor();
        foreach ([[$actor, 'own-file.csv'], [$other, 'private-other.csv']] as [$owner, $name]) {
            \App\Models\DataImportJob::create(['module'=>'product', 'file_name'=>$name, 'file_path'=>'test.csv', 'file_type'=>'csv', 'mode'=>'update', 'matching_key'=>'sku', 'created_by'=>$owner->id, 'status'=>'completed']);
            \App\Models\DataExportJob::create(['module'=>'product', 'file_name'=>$name, 'file_path'=>'test.csv', 'file_type'=>'csv', 'created_by'=>$owner->id, 'status'=>'completed']);
        }
        $this->actingAs($actor)->get('/admin/data-transfer-page')->assertOk()->assertSee('own-file.csv')->assertDontSee('private-other.csv');
        $page = app(\App\Filament\Pages\DataTransferPage::class);
        $this->assertSame(1, $page->importJobsQuery()->count());
        $this->assertSame(1, $page->exportJobsQuery()->count());
    }
}
