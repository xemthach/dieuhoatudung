<?php

namespace App\Console\Commands;

use App\Enums\AIContentJobStatus;
use App\Models\AiContentJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIJobsCancelCurrent extends Command
{
    protected $signature = 'ai:jobs-cancel-current {--flush-queue : Delete pending Laravel queue rows for ai/default queues}';

    protected $description = 'Cancel current queued/processing AI jobs and optionally clear queued rows.';

    public function handle(): int
    {
        $content = 0;
        $productJobs = 0;
        $items = 0;
        $queueRows = 0;

        if (Schema::hasTable('ai_content_jobs')) {
            $content = AiContentJob::whereIn('status', [
                AIContentJobStatus::Pending->value,
                AIContentJobStatus::Queued->value,
                AIContentJobStatus::Processing->value,
                AIContentJobStatus::Stuck->value,
            ])->update([
                'status' => AIContentJobStatus::Cancelled,
                'failed_reason' => 'job_cancelled',
                'last_error_code' => 'job_cancelled',
                'last_error_message' => 'Cancelled by admin command.',
                'error_message' => 'Cancelled by admin command.',
                'finished_at' => now(),
            ]);
        }

        if (Schema::hasTable('ai_product_jobs')) {
            $productJobs = AiProductJob::whereIn('status', ['queued', 'processing', 'stuck'])
                ->update([
                    'status' => 'cancelled',
                    'failed_reason' => 'job_cancelled',
                    'last_error_code' => 'job_cancelled',
                    'last_error_message' => 'Cancelled by admin command.',
                    'finished_at' => now(),
                ]);
        }

        if (Schema::hasTable('ai_product_job_items')) {
            $items = AiProductJobItem::whereIn('status', ['queued', 'processing', 'stuck'])
                ->update([
                    'status' => 'cancelled',
                    'failed_reason' => 'job_cancelled',
                    'last_error_code' => 'job_cancelled',
                    'last_error_message' => 'Cancelled by admin command.',
                    'error_message' => 'Cancelled by admin command.',
                    'finished_at' => now(),
                ]);
        }

        Product::whereIn('ai_status', ['queued', 'processing', 'stuck'])->update([
            'ai_status' => 'cancelled',
            'ai_error_message' => 'Cancelled by admin command.',
            'ai_last_run_at' => now(),
        ]);

        if ($this->option('flush-queue') && Schema::hasTable('jobs')) {
            $queueRows = DB::table('jobs')->whereIn('queue', ['ai', 'default'])->delete();
        }

        $this->table(['AI content jobs', 'AI product jobs', 'AI product items', 'Queue rows deleted'], [[
            $content,
            $productJobs,
            $items,
            $queueRows,
        ]]);

        return self::SUCCESS;
    }
}
