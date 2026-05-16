<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AITechnicalLogger;
use App\Services\Product\AIProductContentSystem;
use App\Support\SchemaColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AiProductContentSingleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public int $productId,
        public ?int $aiProductJobId = null,
        public ?int $aiProductJobItemId = null,
    ) {}

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public function handle(AIProductContentSystem $system, ?AITechnicalLogger $technicalLogger = null): void
    {
        $technicalLogger ??= app(AITechnicalLogger::class);
        $product = Product::with(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts'])->findOrFail($this->productId);
        $job = $this->aiProductJobId ? AiProductJob::find($this->aiProductJobId) : null;
        $item = $this->resolveItem($job, $product);

        $this->updateItem($item, [
            'status' => 'processing',
            'module' => 'ai_product_content',
            'queue_name' => $this->queue ?: 'ai',
            'attempts' => $this->attempts(),
            'started_at' => $item->started_at ?? now(),
            'finished_at' => null,
            'error_message' => null,
            'failed_reason' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);
        $technicalLogger->event('ai_product_content', 'job_started', 'AI product item job started.', [
            'queue' => $this->queue ?: 'ai',
            'attempts' => $this->attempts(),
            'product_id' => $product->id,
        ], $item);

        try {
            $system->generate($product, $job?->config_json ?? [], $job, $item, $job?->created_by);
        } catch (\Throwable $e) {
            if ($this->isRateLimit($e) && $this->attempts() < $this->tries) {
                $this->updateItem($item, [
                    'status' => 'queued',
                    'retry_count' => (int) ($item->retry_count ?? 0) + 1,
                    'failed_reason' => 'provider_rate_limit',
                    'last_error_code' => 'provider_rate_limit',
                    'last_error_message' => 'Rate limited, retrying later.',
                    'error_message' => 'Rate limited, retrying later.',
                ]);
                $product->update(['ai_status' => 'queued', 'ai_error_message' => 'Rate limited, retrying later.']);
                $technicalLogger->event('ai_product_content', 'job_retried', 'Provider rate limit; job released for retry.', [
                    'failed_reason' => 'provider_rate_limit',
                    'attempts' => $this->attempts(),
                    'product_id' => $product->id,
                ], $item, 'warning');
                $this->release(60 * $this->attempts());

                return;
            }

            $technical = $technicalLogger->exception('ai_product_content', $e, $item, [
                'queue' => $this->queue ?: 'ai',
                'attempts' => $this->attempts(),
                'product_id' => $product->id,
            ]);
            $message = $e->getMessage();
            $this->updateItem($item, [
                'status' => 'failed',
                'error_message' => $message,
                'finished_at' => now(),
                'duration_ms' => (int) $item?->started_at?->diffInMilliseconds(now()),
                ...$technical,
            ]);
            $product->update([
                'ai_status' => 'failed',
                'ai_error_message' => $message,
                'ai_last_run_at' => now(),
            ]);
            Log::error('AI product content job failed', [
                'ai_product_job_id' => $job?->id,
                'product_id' => $product->id,
                'error' => $message,
            ]);
        } finally {
            if ($job) {
                $this->refreshJobStats($job->refresh());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $item = $this->aiProductJobItemId ? AiProductJobItem::find($this->aiProductJobItemId) : null;
        $technical = app(AITechnicalLogger::class)->exception('ai_product_content', $exception, $item, [
            'product_id' => $this->productId,
        ]);

        $this->updateItem($item, [
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
            ...$technical,
        ]);

        Product::whereKey($this->productId)->update([
            'ai_status' => 'failed',
            'ai_error_message' => $exception->getMessage(),
            'ai_last_run_at' => now(),
        ]);

        if ($this->aiProductJobId && ($job = AiProductJob::find($this->aiProductJobId))) {
            $this->refreshJobStats($job);
        }
    }

    private function resolveItem(?AiProductJob $job, Product $product): ?AiProductJobItem
    {
        if ($this->aiProductJobItemId) {
            return AiProductJobItem::find($this->aiProductJobItemId);
        }

        return $job?->items()->updateOrCreate(
            ['product_id' => $product->id],
            ['status' => 'queued']
        );
    }

    private function updateItem(?AiProductJobItem $item, array $attributes): void
    {
        $item?->update(SchemaColumns::existing('ai_product_job_items', $attributes));
    }

    private function refreshJobStats(AiProductJob $job): void
    {
        $completedStatuses = ['completed', 'completed_verified', 'completed_with_warnings'];
        $processed = $job->items()->whereIn('status', array_merge($completedStatuses, ['failed', 'needs_review', 'blocked']))->count();
        $success = $job->items()->whereIn('status', $completedStatuses)->count();
        $failed = $job->items()->whereIn('status', ['failed', 'blocked'])->count();
        $needsReview = $job->items()->where('status', 'needs_review')->count();
        $status = $processed >= $job->total
            ? ($failed > 0 ? 'completed_with_errors' : 'completed')
            : 'processing';

        $job->update([
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'needs_review' => $needsReview,
            'status' => $status,
            'finished_at' => $processed >= $job->total ? now() : null,
        ]);
    }

    private function isRateLimit(\Throwable $e): bool
    {
        $message = $e->getMessage();
        $decoded = json_decode($message, true);

        return (is_array($decoded) && ! empty($decoded['is_rate_limit']))
            || str_contains($message, '429')
            || stripos($message, 'rate limit') !== false;
    }
}
