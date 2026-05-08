<?php

namespace App\Jobs;

use App\Enums\AIContentJobStatus;
use App\Models\AiContentJob;
use App\Services\AI\AIManager;
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

    /**
     * Số lần thử lại nếu job thất bại.
     */
    public int $tries = 2;

    /**
     * Timeout tối đa (giây) — AI có thể chậm với bài dài.
     */
    public int $timeout = 300;

    public function __construct(
        public readonly int $aiContentJobId
    ) {}

    public function handle(AIManager $aiManager): void
    {
        $job = AiContentJob::findOrFail($this->aiContentJobId);

        // Tránh chạy lại nếu đã processing hoặc completed
        if (in_array($job->status, [AIContentJobStatus::Processing, AIContentJobStatus::Completed])) {
            return;
        }

        // Check AI enabled = có provider active
        $activeProviders = \App\Models\AiProvider::where('status', 'active')->count();
        if ($activeProviders === 0) {
            $job->update([
                'status' => AIContentJobStatus::Failed,
                'error_message' => 'Khong co AI Provider nao dang hoat dong.',
            ]);
            return;
        }

        $job->update(['status' => AIContentJobStatus::Processing]);

        // Context ID duy trì cùng provider/model xuyên suốt job
        $contextId = 'blog_draft_' . $job->id . '_' . Str::random(8);

        try {
            $topic   = $job->topic;
            $keyword = $job->primary_keyword ?? $topic;
            $intent  = $job->intent ?? 'informational';

            Log::info('GenerateBlogDraftJob: Bat dau tao outline', [
                'job_id' => $job->id,
                'topic'  => $topic,
            ]);

            // Bước 1: Tạo outline
            $outlineResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia SEO Content Writer tieng Viet.',
                'prompt' => str_replace(
                    ['{topic}', '{keyword}', '{intent}'],
                    [$topic, $keyword, $intent],
                    config('gemini.prompts.blog_outline', "Viet outline cho bai viet ve {topic}, keyword chinh: {keyword}, search intent: {intent}. Tra ve outline dang markdown.")
                ),
            ], [
                'task_type' => 'blog_outline',
                'context_id' => $contextId,
            ]);
            $outline = $outlineResult['content'];

            $job->update(['output_outline' => $outline]);

            Log::info('GenerateBlogDraftJob: Outline xong, tao Meta & FAQ', ['job_id' => $job->id]);

            // Bước 2: Meta & FAQ (cùng context)
            $metaResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia SEO. Tra ve JSON.',
                'prompt' => str_replace('{outline}', $outline, config('gemini.prompts.blog_meta', "Tao SEO meta cho bai viet sau:\n{outline}\nTra ve JSON: {\"seo_title\": \"...\", \"seo_description\": \"...\", \"og_title\": \"...\", \"og_description\": \"...\"}")),
            ], [
                'task_type' => 'blog_meta',
                'context_id' => $contextId,
                'require_json' => true,
            ]);
            $meta = $metaResult['json'];

            $faqResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia noi dung. Tra ve JSON array.',
                'prompt' => str_replace('{outline}', $outline, config('gemini.prompts.blog_faq', "Tao 5 cau hoi FAQ lien quan den bai viet:\n{outline}\nTra ve JSON array: [{\"question\": \"...\", \"answer\": \"...\"}]")),
            ], [
                'task_type' => 'blog_faq',
                'context_id' => $contextId,
                'require_json' => true,
            ]);
            $faq = $faqResult['json'];

            Log::info('GenerateBlogDraftJob: Bat dau viet draft', ['job_id' => $job->id]);

            // Bước 3: Tạo draft từ outline
            $draftResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia viet noi dung SEO tieng Viet.',
                'prompt' => str_replace('{outline}', $outline, config('gemini.prompts.blog_draft', "Viet bai viet day du tu outline sau:\n{outline}\nViet bang HTML sach (h2, h3, p, ul/li). KHONG dung inline style, emoji, script.")),
            ], [
                'task_type' => 'blog_draft',
                'context_id' => $contextId,
            ]);
            $draft = $draftResult['content'];

            Log::info('GenerateBlogDraftJob: Draft xong, goi y tags & links', ['job_id' => $job->id]);

            // Bước 4: Gợi ý tags & links
            $excerpt = substr(strip_tags($draft), 0, 500);

            $tagsResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia SEO. Tra ve JSON array.',
                'prompt' => str_replace(
                    ['{title}', '{excerpt}'],
                    [$topic, $excerpt],
                    config('gemini.prompts.tag_suggestions', "Goi y tags cho bai viet:\nTitle: {title}\nExcerpt: {excerpt}\nTra ve JSON array: [{\"name\": \"...\", \"type\": \"topic\"}]")
                ),
            ], [
                'task_type' => 'blog_tags',
                'context_id' => $contextId,
                'require_json' => true,
            ]);
            $tags = $tagsResult['json'];

            $linksResult = $aiManager->generate([
                'system' => 'Ban la chuyen gia SEO internal linking. Tra ve JSON array.',
                'prompt' => str_replace('{draft}', substr($draft, 0, 2000), config('gemini.prompts.blog_internal_links', "Goi y internal links cho bai viet:\n{draft}\nTra ve JSON array: [{\"anchor\": \"...\", \"suggested_url\": \"...\"}]")),
            ], [
                'task_type' => 'blog_internal_links',
                'context_id' => $contextId,
                'require_json' => true,
            ]);
            $links = $linksResult['json'];

            $job->update([
                'output_draft'          => $draft,
                'output_tags'           => $tags,
                'output_meta'           => $meta,
                'output_faq'            => $faq,
                'output_internal_links' => $links,
                'status'                => AIContentJobStatus::Completed,
                'error_message'         => null,
            ]);

            Log::info('GenerateBlogDraftJob: Hoan thanh', ['job_id' => $job->id]);

        } catch (\Throwable $e) {
            Log::error('GenerateBlogDraftJob: That bai', [
                'job_id' => $job->id,
                'error'  => $e->getMessage(),
            ]);

            $job->update([
                'status'        => AIContentJobStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw để Laravel retry logic hoạt động
            throw $e;
        }
    }

    /**
     * Xử lý khi job thất bại sau tất cả các lần thử.
     */
    public function failed(\Throwable $exception): void
    {
        AiContentJob::where('id', $this->aiContentJobId)
            ->where('status', AIContentJobStatus::Processing)
            ->update([
                'status'        => AIContentJobStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
