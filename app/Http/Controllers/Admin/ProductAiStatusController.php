<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AI\AIQueueMonitor;
use App\Services\AI\AiProductLiveStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAiStatusController extends Controller
{
    public function index(
        Request $request,
        AIQueueMonitor $queueMonitor,
        AiProductLiveStatusService $liveStatus,
    ): JsonResponse
    {
        abort_unless($request->user()?->can('product.view'), 403);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(100)
            ->values();

        // Product-table polling does not need article processing totals. Avoid
        // paying for an unrelated aggregate every ten seconds.
        $health = $queueMonitor->liveStatusHealth(includeContentProcessing: false);
        $products = $liveStatus->forProductIds($ids->all(), $health)
            ->map(function (array $status) use ($request): array {
                $status['retry_url'] = $status['retry_allowed'] && $request->user()?->can('product.ai_generate')
                    ? route('admin.products.ai-retry', $status['id'])
                    : null;

                return $status;
            })
            ->values();

        return response()->json([
            'products' => $products,
            'queue_health' => [
                'worker_online' => (bool) data_get($health, 'worker_heartbeat.is_running'),
                'worker_health' => data_get($health, 'worker_heartbeat.health_status', 'UNKNOWN'),
                'desired_state' => data_get($health, 'worker_desired_state', 'DISABLED'),
                'pending_jobs' => (int) (data_get($health, 'pending_jobs_count') ?? 0),
                'processing_jobs' => (int) (data_get($health, 'ai_product_processing_count') ?? 0),
                'failed_jobs' => (int) (data_get($health, 'failed_jobs_count') ?? 0),
                'scheduler_online' => (bool) data_get($health, 'scheduler_is_running'),
            ],
            'auto_refresh' => [
                'should_continue' => $products->contains(fn (array $product): bool => (bool) $product['should_poll']),
                'interval_ms' => 10000,
            ],
        ]);
    }

    public function retry(Product $product, Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('product.ai_generate'), 403);

        $items = $product->aiProductJobItems()
            ->whereIn('status', ['failed', 'stuck', 'cancelled'])
            ->latest('id')
            ->get();

        $count = ProductsTable::retryAiProductItems($items);

        return response()->json([
            'retried' => (int) $count,
            'product_id' => (int) $product->id,
            'status' => $product->aiProductJobItems()->latest('id')->value('status') ?: 'not_generated',
        ]);
    }
}
