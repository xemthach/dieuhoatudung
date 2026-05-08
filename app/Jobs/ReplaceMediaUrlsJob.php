<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReplaceMediaUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(public int $jobId)
    {
    }

    public function handle(\App\Services\Media\MediaUrlReplaceService $replaceService): void
    {
        $job = \App\Models\R2SyncJob::find($this->jobId);
        if (!$job || $job->status === 'cancelled') return;

        $job->update(['started_at' => now()]);
        
        try {
            $replaceService->replaceUrls($job);
        } catch (\Exception $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
