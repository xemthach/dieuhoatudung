<?php

namespace App\Jobs;

use App\Models\AiProductJob;
use App\Models\Product;
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

    public function handle(): void
    {
        $job = AiProductJob::findOrFail($this->aiProductJobId);
        $config = is_array($job->config_json) ? $job->config_json : [];
        $batchSize = max(1, min((int) ($config['batch_size'] ?? 10), 50));
        $productIds = Product::query()->whereKey($this->productIds)->pluck('id')->all();

        $job->update([
            'status' => 'processing',
            'total' => count($productIds),
            'started_at' => $job->started_at ?? now(),
        ]);

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
                    ->onQueue('default')
                    ->delay(now()->addSeconds($chunkIndex * 5));
            }
        }

        if ($productIds === []) {
            $job->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }
}
