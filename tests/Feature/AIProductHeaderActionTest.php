<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Jobs\AiProductContentBatchJob;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
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

    public function test_header_ai_generate_selected_scope_uses_checked_products_only(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $products = Product::factory()->count(12)->create();
        $selectedIds = $products->take(10)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(ListProducts::class)
            ->set('selectedTableRecords', $selectedIds)
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'selected']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('selected', $job->scope);
        $this->assertSame(10, $job->total);

        Bus::assertDispatched(AiProductContentBatchJob::class, function (AiProductContentBatchJob $batchJob) use ($selectedIds): bool {
            sort($batchJob->productIds);
            $expected = array_map('intval', $selectedIds);
            sort($expected);

            return $batchJob->productIds === $expected;
        });
    }

    public function test_header_ai_generate_selected_scope_accepts_checkbox_boolean_state(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $products = Product::factory()->count(12)->create();
        $selectedIds = $products->take(10)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $checkboxState = array_fill_keys($selectedIds, true);

        Livewire::test(ListProducts::class)
            ->set('selectedTableRecords', $checkboxState)
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'selected']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('selected', $job->scope);
        $this->assertSame(10, $job->total);

        Bus::assertDispatched(AiProductContentBatchJob::class, function (AiProductContentBatchJob $batchJob) use ($selectedIds): bool {
            sort($batchJob->productIds);
            $expected = array_map('intval', $selectedIds);
            sort($expected);

            return $batchJob->productIds === $expected;
        });
    }

    public function test_header_ai_generate_selected_scope_supports_select_all_except_deselected_state(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        $products = Product::factory()->count(12)->create();
        $deselectedIds = $products->take(2)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(ListProducts::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->set('deselectedTableRecords', $deselectedIds)
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'selected']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('selected', $job->scope);
        $this->assertSame(10, $job->total);
    }

    public function test_header_ai_generate_all_filtered_scope_uses_filtered_products(): void
    {
        Bus::fake();
        $this->actingAsAiProductUser();
        Product::factory()->count(3)->create(['ai_score' => 80]);
        Product::factory()->count(4)->create(['ai_score' => 40]);

        Livewire::test(ListProducts::class)
            ->filterTable('seo_score_lt_70')
            ->callAction('ai_generate_filtered', $this->actionData(['scope' => 'all_filtered']));

        $job = AiProductJob::first();
        $this->assertNotNull($job);
        $this->assertSame('all_filtered', $job->scope);
        $this->assertSame(4, $job->total);
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

    private function actingAsAiProductUser(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);

        foreach (['product.view', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo(['product.view', 'product.ai_generate']);
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
