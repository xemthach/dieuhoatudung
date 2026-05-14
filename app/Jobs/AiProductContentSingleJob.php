<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\Product\AIProductContentSystem;
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

    public function handle(AIProductContentSystem $system): void
    {
        $product = Product::with(['brand', 'category', 'tags', 'faqs', 'relatedProducts', 'posts'])->findOrFail($this->productId);
        $job = $this->aiProductJobId ? AiProductJob::find($this->aiProductJobId) : null;
        $item = $this->resolveItem($job, $product);

        $item?->update([
            'status' => 'processing',
            'started_at' => $item->started_at ?? now(),
            'error_message' => null,
        ]);

        try {
            $system->generate($product, $job?->config_json ?? [], $job, $item, $job?->created_by);
        } catch (\Throwable $e) {
            if ($this->isRateLimit($e) && $this->attempts() < $this->tries) {
                $item?->update(['status' => 'queued', 'error_message' => 'Rate limited, retrying later.']);
                $product->update(['ai_status' => 'queued', 'ai_error_message' => 'Rate limited, retrying later.']);
                $this->release(60 * $this->attempts());

                return;
            }

            $message = $e->getMessage();
            $item?->update([
                'status' => 'failed',
                'error_message' => $message,
                'finished_at' => now(),
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
