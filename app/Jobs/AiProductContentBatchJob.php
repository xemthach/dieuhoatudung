<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\Product;
use App\Services\AI\AITechnicalLogger;
use App\Support\SchemaColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AiProductContentBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $aiProductJobId,
        public array $productIds,
    ) {}

    public function handle(?AITechnicalLogger $technicalLogger = null): void
    {
        $technicalLogger ??= app(AITechnicalLogger::class);
        $job = AiProductJob::findOrFail($this->aiProductJobId);
        $config = is_array($job->config_json) ? $job->config_json : [];
        $batchSize = max(1, min((int) ($config['batch_size'] ?? 10), 50));
        $productIds = Product::query()->whereKey($this->productIds)->pluck('id')->all();

        $job->update(SchemaColumns::existing('ai_product_jobs', [
            'status' => 'processing',
            'module' => 'ai_product_bulk',
            'queue_name' => $this->queue ?: 'ai',
            'attempts' => $this->attempts(),
            'total' => count($productIds),
            'started_at' => $job->started_at ?? now(),
            'failed_reason' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]));
        $technicalLogger->event('ai_product_bulk', 'job_started', 'AI product batch job started.', [
            'queue' => $this->queue ?: 'ai',
            'total' => count($productIds),
            'batch_size' => $batchSize,
        ], $job);

        Product::whereKey($productIds)->update([
            'ai_status' => 'queued',
            'ai_error_message' => null,
            'ai_last_run_at' => now(),
        ]);

        foreach (array_chunk($productIds, $batchSize) as $chunkIndex => $chunk) {
            foreach ($chunk as $productId) {
                $item = $job->items()->updateOrCreate(
                    ['product_id' => $productId],
                    ['status' => 'queued', 'error_message' => null]
                );

                AiProductContentSingleJob::dispatch($productId, $job->id, $item->id)
                    ->onQueue('ai')
                    ->delay(now()->addSeconds($chunkIndex * 5));
            }
        }

        if ($productIds === []) {
            $job->update(['status' => 'completed', 'finished_at' => now()]);
        }

        $technicalLogger->event('ai_product_bulk', 'job_dispatched', 'AI product item jobs dispatched.', [
            'total' => count($productIds),
            'queue' => 'ai',
        ], $job);
    }
}
