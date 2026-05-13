<?php

namespace App\Jobs;

use App\Enums\AIContentJobStatus;
use App\Models\AiContentJob;
use App\Models\AiProvider;
use App\Services\AI\AIManager;
use App\Services\AI\HVACSeoContentEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateBlogDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly int $aiContentJobId
    ) {}

    public function handle(AIManager $aiManager, HVACSeoContentEngine $contentEngine): void
    {
        $job = AiContentJob::findOrFail($this->aiContentJobId);

        if (in_array($job->status, [AIContentJobStatus::Completed, AIContentJobStatus::Reviewed])) {
            return;
        }

        if (AiProvider::where('status', 'active')->count() === 0) {
            $job->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => 'Không có AI Provider nào đang hoạt động.',
            ]);

            return;
        }

        $job->update(['status' => AIContentJobStatus::Processing]);

        $contextId = 'hvac_blog_'.$job->id.'_'.Str::random(8);

        try {
            Log::info('GenerateBlogDraftJob: Bắt đầu tạo nội dung HVAC SEO', [
                'job_id' => $job->id,
                'topic' => $job->topic,
            ]);

            $output = $contentEngine->generate($aiManager, $job, $contextId);

            $job->update([
                'topic' => $output['title'],
                'primary_keyword' => $job->primary_keyword ?: ($output['tags'][0]['name'] ?? null),
                'output_outline' => json_encode([
                    'title' => $output['title'],
                    'slug' => $output['slug'],
                    'excerpt' => $output['excerpt'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'output_draft' => $output['content'],
                'output_tags' => $output['tags'],
                'output_meta' => [
                    'title' => $output['title'],
                    'slug' => $output['slug'],
                    'excerpt' => $output['excerpt'],
                    'seo_title' => $output['seo_title'],
                    'meta_description' => $output['meta_description'],
                    'og_title' => $output['og_title'],
                    'og_description' => $output['og_description'],
                ],
                'output_faq' => $output['faq'],
                'output_internal_links' => $output['internal_links'],
                'status' => AIContentJobStatus::Completed,
                'error_message' => null,
            ]);

            Log::info('GenerateBlogDraftJob: Hoàn thành', ['job_id' => $job->id]);
        } catch (\Throwable $e) {
            Log::error('GenerateBlogDraftJob: Thất bại', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            $job->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        AiContentJob::where('id', $this->aiContentJobId)
            ->where('status', AIContentJobStatus::Processing)
            ->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
