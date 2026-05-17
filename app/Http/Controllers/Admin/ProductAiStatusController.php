<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Http\Controllers\Controller;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AIQueueMonitor;
use App\Services\Product\AIProductContentSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAiStatusController extends Controller
{
    private const ACTIVE_STATUSES = ['queued', 'processing', 'retrying', 'stuck'];

    public function index(Request $request, AIQueueMonitor $queueMonitor): JsonResponse
    {
        abort_unless($request->user()?->can('product.view'), 403);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(100)
            ->values();

        $latestItemIds = $ids->isEmpty()
            ? collect()
            : AiProductJobItem::query()
                ->selectRaw('MAX(id) as id')
                ->whereIn('product_id', $ids->all())
                ->groupBy('product_id')
                ->pluck('id');

        $itemsByProduct = AiProductJobItem::query()
            ->whereIn('id', $latestItemIds)
            ->with('job:id,total,processed,success,failed,status')
            ->get()
            ->keyBy('product_id');

        $products = Product::query()
            ->whereKey($ids->all())
            ->select(['id', 'ai_status', 'ai_score', 'ai_warning_count', 'ai_last_run_at', 'ai_error_message'])
            ->latest('id')
            ->get()
            ->map(function (Product $product) use ($itemsByProduct): array {
                $item = $itemsByProduct->get($product->id);
                $job = $item?->job;
                $total = max(1, (int) ($job?->total ?? 1));
                $processed = (int) ($job?->processed ?? 0);

                return [
                    'id' => (int) $product->id,
                    'ai_status' => $product->ai_status ?: 'not_generated',
                    'ai_status_label' => $this->statusLabel($product->ai_status, $item?->failed_reason),
                    'seo_score' => (int) ($product->ai_score ?? 0),
                    'warnings_count' => (int) ($product->ai_warning_count ?? 0),
                    'last_ai_run' => $product->ai_last_run_at?->diffForHumans() ?? '-',
                    'last_ai_run_iso' => $product->ai_last_run_at?->toIso8601String(),
                    'progress_percent' => in_array($product->ai_status, self::ACTIVE_STATUSES, true)
                        ? (int) min(100, round(($processed / $total) * 100))
                        : null,
                    'failed_reason' => $item?->failed_reason ?: $product->ai_error_message,
                    'last_error_message' => $item?->last_error_message ?: $item?->error_message ?: $product->ai_error_message,
                    'retry_url' => route('admin.products.ai-retry', $product),
                    'is_active' => in_array($product->ai_status, self::ACTIVE_STATUSES, true),
                ];
            })
            ->values();

        $health = $queueMonitor->health();

        return response()->json([
            'products' => $products,
            'queue_health' => [
                'worker_online' => (bool) data_get($health, 'worker_heartbeat.is_running'),
                'pending_jobs' => (int) (data_get($health, 'pending_jobs_count') ?? 0),
                'processing_jobs' => (int) (data_get($health, 'ai_product_processing_count') ?? 0),
                'failed_jobs' => (int) (data_get($health, 'failed_jobs_count') ?? 0),
                'scheduler_online' => (bool) data_get($health, 'scheduler_is_running'),
            ],
            'auto_refresh' => [
                'should_continue' => $products->contains(fn (array $product): bool => (bool) $product['is_active']),
                'interval_ms' => 5000,
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
            'status' => $product->refresh()->ai_status,
        ]);
    }

    private function statusLabel(?string $status, ?string $failedReason = null): string
    {
        $label = AIProductContentSystem::AI_STATUSES[$status ?: 'not_generated'] ?? (string) $status;

        return $status === 'failed' && filled($failedReason) ? "{$label}: {$failedReason}" : $label;
    }
}
