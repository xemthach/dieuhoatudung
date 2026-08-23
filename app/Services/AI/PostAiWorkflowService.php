<?php

namespace App\Services\AI;

use App\Enums\AIContentJobStatus;
use App\Enums\AIReviewStatus;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\SchemaColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PostAiWorkflowService
{
    public const OUTPUTS = [
        'content' => 'Nội dung bài viết',
        'seo' => 'SEO title và meta description',
        'faq' => 'FAQ',
        'tags' => 'Thẻ nội dung',
    ];

    public function __construct(
        private AIProviderPool $providerPool,
        private AIWorkerReadinessService $workerReadiness,
    ) {}

    /** @return array{job:AiContentJob,dispatched:bool,worker_disabled:bool,worker_ready:bool,worker_message:string} */
    public function createForPost(Post $post, array $formState, User $actor, bool $newlyCreated = false): array
    {
        abort_unless($actor->can('ai_content_job.create'), 403);
        abort_unless($actor->can($newlyCreated ? 'post.create' : 'post.edit'), 403);

        $intent = data_get($formState, 'search_intent');
        $intent = $intent instanceof \BackedEnum ? $intent->value : $intent;
        $payload = [
            'category' => (string) (data_get($formState, 'ai_content_category') ?: 'Kiến thức HVAC'),
            'audience' => data_get($formState, 'ai_audience'),
            'product_id' => data_get($formState, 'ai_related_product_id'),
            'brand_id' => data_get($formState, 'ai_related_brand_id'),
            'target_type' => Post::class,
            'target_post_id' => (int) $post->id,
            'operation' => filled($post->content) ? 'regenerate' : 'generate',
            'requested_fields' => array_values((array) (data_get($formState, 'ai_requested_fields') ?: array_keys(self::OUTPUTS))),
            'current_content_hash' => hash('sha256', (string) $post->content),
        ];

        $job = AiContentJob::create(array_merge([
            'topic' => (string) (data_get($formState, 'title') ?: $post->title),
            'primary_keyword' => data_get($formState, 'primary_keyword') ?: $post->primary_keyword,
            'intent' => $intent ?: $post->search_intent?->value,
            'post_category_id' => data_get($formState, 'post_category_id') ?: $post->post_category_id,
            'input_payload' => $payload,
            'status' => AIContentJobStatus::Pending,
            'created_by' => $actor->id,
        ], SchemaColumns::existing('ai_content_jobs', [
            'module' => 'ai_blog',
            'queue_name' => config('ai.governed_queue', 'ai_governed'),
        ])));

        $payload['context_id'] = 'hvac_blog_job_'.$job->id;
        $job->update(['input_payload' => $payload]);
        $dispatched = false;

        if ($this->providerPool->hasAvailableProviders()) {
            $job->update(['status' => AIContentJobStatus::Queued]);
            GenerateBlogDraftJob::dispatch($job->id)->onQueue(config('ai.governed_queue', 'ai_governed'));
            $dispatched = true;
        }

        $worker = $this->workerReadiness->snapshot();

        return [
            'job' => $job->refresh(),
            'dispatched' => $dispatched,
            'worker_disabled' => $worker['desired'] === AIWorkerDesiredStateService::DISABLED,
            'worker_ready' => $worker['ready'],
            'worker_message' => $worker['message'],
        ];
    }

    public function latestForPost(Post|int $post): ?AiContentJob
    {
        $id = $post instanceof Post ? $post->id : $post;

        return AiContentJob::query()
            ->where('input_payload->target_post_id', (int) $id)
            ->latest('id')
            ->first();
    }

    public function approve(Post $post, AiContentJob $job, User $actor): void
    {
        abort_unless($actor->can('post.edit') && $actor->can('ai_content_job.view'), 403);
        $this->assertTargets($post, $job);
        if (! in_array($job->status, [AIContentJobStatus::Completed, AIContentJobStatus::CompletedVerified, AIContentJobStatus::CompletedWithWarnings], true)) {
            throw new RuntimeException('AI_DRAFT_NOT_READY_FOR_REVIEW');
        }

        $job->update(['status' => AIContentJobStatus::Reviewed, 'reviewed_by' => $actor->id]);
    }

    /** @return array{result:string,fields:array<int,string>} */
    public function apply(Post $post, AiContentJob $job, User $actor): array
    {
        abort_unless($actor->can('post.edit') && $actor->can('ai_content_job.view'), 403);
        $this->assertTargets($post, $job);
        if ($job->status !== AIContentJobStatus::Reviewed) {
            throw new RuntimeException('AI_DRAFT_APPROVAL_REQUIRED');
        }

        $payload = (array) $job->input_payload;
        if (filled($payload['applied_at'] ?? null)) {
            return ['result' => 'NOOP_ALREADY_APPLIED', 'fields' => (array) ($payload['applied_fields'] ?? [])];
        }

        $requested = array_values((array) ($payload['requested_fields'] ?? array_keys(self::OUTPUTS)));
        $meta = (array) $job->output_meta;
        $changes = [];
        if (in_array('content', $requested, true)) {
            $changes['content'] = (string) $job->output_draft;
            $changes['excerpt'] = $meta['excerpt'] ?? $post->excerpt;
        }
        if (in_array('seo', $requested, true)) {
            $changes['seo_title'] = $meta['seo_title'] ?? $post->seo_title;
            $changes['seo_description'] = $meta['meta_description'] ?? $post->seo_description;
            $changes['og_title'] = $meta['og_title'] ?? $post->og_title;
            $changes['og_description'] = $meta['og_description'] ?? $post->og_description;
        }
        $changes['ai_generated'] = true;
        $changes['ai_review_status'] = AIReviewStatus::Approved;

        DB::transaction(function () use ($post, $job, $actor, $payload, $requested, $changes): void {
            $post->update($changes);
            if (in_array('tags', $requested, true)) {
                $this->syncTags($post, (array) $job->output_tags);
            }
            if (in_array('faq', $requested, true)) {
                $this->syncFaq($post, (array) $job->output_faq);
            }
            $job->update(['input_payload' => array_merge($payload, [
                'applied_at' => now()->toIso8601String(),
                'applied_by' => $actor->id,
                'applied_fields' => $requested,
            ])]);
        });

        return ['result' => 'APPLIED', 'fields' => $requested];
    }

    public function reject(Post $post, AiContentJob $job, User $actor): void
    {
        abort_unless($actor->can('post.edit') && $actor->can('ai_content_job.view'), 403);
        $this->assertTargets($post, $job);
        $job->update([
            'status' => AIContentJobStatus::Cancelled,
            'reviewed_by' => $actor->id,
            'failed_reason' => 'draft_rejected_by_reviewer',
            'finished_at' => $job->finished_at ?: now(),
        ]);
    }

    private function assertTargets(Post $post, AiContentJob $job): void
    {
        if ((int) data_get($job->input_payload, 'target_post_id') !== (int) $post->id) {
            throw new RuntimeException('AI_JOB_POST_SCOPE_MISMATCH');
        }
    }

    private function syncTags(Post $post, array $tags): void
    {
        $ids = collect($tags)->map(function ($item) {
            $name = is_string($item) ? $item : ($item['name'] ?? null);
            if (blank($name)) return null;
            return Tag::firstOrCreate(['name' => trim($name)], [
                'slug' => Str::slug($name),
                'type' => is_array($item) ? ($item['type'] ?? 'topic') : 'topic',
            ])->id;
        })->filter()->values()->all();
        if ($ids !== []) $post->tags()->sync($ids);
    }

    private function syncFaq(Post $post, array $items): void
    {
        $ids = collect($items)->map(function ($item, $index) {
            if (blank($item['question'] ?? null) || blank($item['answer'] ?? null)) return null;
            return Faq::create([
                'question' => $item['question'],
                'answer' => $item['answer'],
                'group' => 'blog',
                'sort_order' => $index + 1,
                'is_active' => true,
            ])->id;
        })->filter()->values();
        if ($ids->isNotEmpty()) {
            $post->faqs()->sync($ids->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index + 1]])->all());
        }
    }
}
