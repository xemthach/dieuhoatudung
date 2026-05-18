<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Jobs\AiProductContentBatchJob;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\DataExportJob;
use App\Models\Product;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AIProductHeaderActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_table_bulk_ai_generate_selected_scope_uses_checked_products_only(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $products = Product::factory()->count(12)->create();
        $selectedProducts = $products->take(2);
        $selectedIds = $selectedProducts->pluck('id')->map(fn ($id) => (int) $id)->all();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('ai_generate_content', $selectedProducts, $this->actionData(['scope' => 'selected']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('selected', $job->scope);
        $this->assertSame(2, $job->total);
        $this->assertSame($selectedIds, array_map('intval', $job->selected_product_ids_json));

        Bus::assertDispatched(AiProductContentBatchJob::class, function (AiProductContentBatchJob $batchJob) use ($selectedIds): bool {
            sort($batchJob->productIds);
            $expected = $selectedIds;
            sort($expected);

            return $batchJob->productIds === $expected;
        });
    }

    public function test_header_ai_generate_filter_scope_uses_filtered_products(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        Product::factory()->count(3)->create(['ai_score' => 80]);
        Product::factory()->count(4)->create(['ai_score' => 40]);

        Livewire::test(ListProducts::class)
            ->filterTable('seo_score_lt_70')
            ->callAction('ai_generate_by_filter', $this->actionData([
                'scope' => 'filter',
                'confirm_filter_scope' => true,
            ]));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('filter', $job->scope);
        $this->assertSame(4, $job->total);
    }

    public function test_header_ai_generate_current_page_scope_does_not_require_selected_records(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        Product::factory()->count(12)->create();

        Livewire::test(ListProducts::class)
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'current_page']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('current_page', $job->scope);
        $this->assertSame(10, $job->total);
    }

    public function test_header_filter_scope_requires_explicit_confirmation(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        Product::factory()->count(12)->create();

        Livewire::test(ListProducts::class)
            ->callAction('ai_generate_by_filter', $this->actionData(['scope' => 'filter']));

        $this->assertNull(AiProductJob::first());

        Bus::assertNotDispatched(AiProductContentBatchJob::class);
    }

    public function test_header_selected_scope_is_rejected_even_when_table_selection_exists(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $products = Product::factory()->count(12)->create();
        $selectedIds = $products->take(2)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(ListProducts::class)
            ->set('selectedTableRecords', $selectedIds)
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'selected']));

        $this->assertNull(AiProductJob::first());

        Bus::assertNotDispatched(AiProductContentBatchJob::class);
    }

    public function test_table_bulk_export_selected_products_exports_only_checked_products(): void
    {
        $this->actingAsAiProductUser(['product.export']);
        $products = Product::factory()->count(12)->create();
        $selectedProducts = $products->take(2);
        $selectedIds = $selectedProducts->pluck('id')->map(fn ($id) => (int) $id)->all();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('export_selected_products', $selectedProducts, [
                'file_type' => 'json',
                'field_groups' => [],
            ]);

        $job = DataExportJob::first();
        $this->assertNotNull($job);
        $this->assertSame('product', $job->module);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame($selectedIds, array_map('intval', $job->selected_ids_json));
    }

    public function test_header_export_selected_scope_uses_checked_products_only(): void
    {
        $this->actingAsAiProductUser(['product.export']);
        $products = Product::factory()->count(12)->create();
        $selectedIds = $products->take(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

        Livewire::test(ListProducts::class)
            ->set('selectedTableRecords', array_map('strval', $selectedIds))
            ->callAction('export_data', [
                'file_type' => 'json',
                'export_scope' => 'selected',
                'field_groups' => [],
            ]);

        $job = DataExportJob::first();
        $this->assertNotNull($job);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame($selectedIds, array_map('intval', $job->selected_ids_json));
        $this->assertStringContainsString('product_export_selected_2_', $job->file_name);
    }

    public function test_header_export_selected_scope_with_empty_selection_does_not_fallback_to_all(): void
    {
        $this->actingAsAiProductUser(['product.export']);
        Product::factory()->count(12)->create();

        Livewire::test(ListProducts::class)
            ->callAction('export_data', [
                'file_type' => 'json',
                'export_scope' => 'selected',
                'field_groups' => [],
            ]);

        $this->assertNull(DataExportJob::first());
    }

    public function test_header_export_current_page_scope_uses_visible_records_only(): void
    {
        $this->actingAsAiProductUser(['product.export']);
        Product::factory()->count(12)->create();

        Livewire::test(ListProducts::class)
            ->callAction('export_data', [
                'file_type' => 'json',
                'export_scope' => 'current_page',
                'field_groups' => [],
            ]);

        $job = DataExportJob::first();
        $this->assertNotNull($job);
        $this->assertSame(10, $job->total_rows);
        $this->assertCount(10, $job->selected_ids_json);
        $this->assertStringContainsString('product_export_current_page_10_', $job->file_name);
    }

    public function test_header_export_filter_scope_uses_filtered_records_even_when_rows_are_selected(): void
    {
        $this->actingAsAiProductUser(['product.export']);
        $unmatched = Product::factory()->count(3)->create(['ai_score' => 80]);
        Product::factory()->count(4)->create(['ai_score' => 40]);

        Livewire::test(ListProducts::class)
            ->set('selectedTableRecords', $unmatched->take(2)->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->filterTable('seo_score_lt_70')
            ->callAction('export_data', [
                'file_type' => 'json',
                'export_scope' => 'filter',
                'field_groups' => [],
            ]);

        $job = DataExportJob::first();
        $this->assertNotNull($job);
        $this->assertSame(4, $job->total_rows);
        $this->assertCount(4, $job->selected_ids_json);
        $this->assertStringContainsString('product_export_filter_4_', $job->file_name);
    }

    public function test_header_export_labels_are_valid_utf8_not_mojibake(): void
    {
        $source = file_get_contents(app_path('Filament/Traits/HasDataTransferActions.php'));

        $this->assertStringContainsString('Định dạng', $source);
        $this->assertStringContainsString('Nhóm dữ liệu', $source);
        $this->assertStringContainsString('Phạm vi', $source);
        $this->assertStringNotContainsString(json_decode('"\\u00c4\\u0090"'), $source);
        $this->assertStringNotContainsString(json_decode('"\\u00c3"'), $source);
    }

    public function test_retry_ai_product_items_resets_failed_items_and_dispatches_single_jobs(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $product = Product::factory()->create(['ai_status' => 'failed', 'ai_error_message' => 'No AI providers available.']);
        $job = AiProductJob::create([
            'type' => 'generate_ai_content',
            'scope' => 'selected',
            'status' => 'completed_with_errors',
            'total' => 1,
            'processed' => 1,
            'failed' => 1,
            'config_json' => $this->actionData(),
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'failed',
            'failed_reason' => 'missing_api_key',
            'last_error_code' => 'missing_api_key',
            'last_error_message' => 'No AI providers available.',
            'error_message' => 'No AI providers available.',
        ]);

        $count = ProductsTable::retryAiProductItems([$item]);

        $this->assertSame(1, $count);
        $this->assertSame('queued', $item->refresh()->status);
        $this->assertNull($item->failed_reason);
        $this->assertSame('queued', $product->refresh()->ai_status);
        $this->assertSame('processing', $job->refresh()->status);

        Bus::assertDispatched(AiProductContentSingleJob::class, fn (AiProductContentSingleJob $singleJob): bool => $singleJob->aiProductJobItemId === $item->id);
    }

    private function actingAsAiProductUser(array $extraPermissions = []): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        $permissions = array_values(array_unique(array_merge(['product.view', 'product.ai_generate'], $extraPermissions)));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);
        $this->actingAs($user);
    }

    private function actionData(array $overrides = []): array
    {
        return array_merge([
            'scope' => 'selected',
            'outputs' => ['content', 'seo'],
            'mode' => 'missing_only',
            'depth' => 'seo',
            'tone' => 'hvac_expert',
            'apply_mode' => 'needs_review',
            'batch_size' => 10,
        ], $overrides);
    }
}
