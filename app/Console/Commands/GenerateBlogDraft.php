<?php

namespace App\Console\Commands;

use App\Enums\AIContentJobStatus;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\PostCategory;
use App\Services\AI\AIProviderPool;
use Illuminate\Console\Command;

class GenerateBlogDraft extends Command
{
    protected $signature = 'ai:generate-blog
                            {topic? : Chủ đề bài viết, để trống để AI tự tạo}
                            {--keyword= : Từ khóa chính}
                            {--intent= : informational hoặc commercial}
                            {--content-category=Kiến thức HVAC : Kiến thức HVAC / So sánh / Giải pháp / Lỗi / sửa chữa}
                            {--audience= : nhà xưởng / văn phòng / showroom / dân dụng}
                            {--product= : Product ID liên quan}
                            {--brand= : Brand ID liên quan}
                            {--category= : ID danh mục blog}
                            {--bulk=1 : Số bài cần tạo trong cùng category}
                            {--sync : Chạy đồng bộ thay vì queue}';

    protected $description = 'Tạo bài viết blog HVAC SEO bằng AI Provider đã cấu hình';

    public function handle(): int
    {
        if (! app(AIProviderPool::class)->hasAvailableProviders()) {
            $this->error('Chưa có AI Provider nào khả dụng. Vui lòng cấu hình AI Providers.');

            return self::FAILURE;
        }

        $bulk = max(1, min((int) $this->option('bulk'), 50));
        $postCategoryId = $this->resolvePostCategoryId();
        $jobs = [];

        for ($index = 1; $index <= $bulk; $index++) {
            $topic = $this->argument('topic') ?: 'AI tự tạo topic - '.$this->option('content-category').' #'.$index;

            $job = AiContentJob::create([
                'topic' => $topic,
                'primary_keyword' => $this->option('keyword'),
                'intent' => $this->option('intent'),
                'post_category_id' => $postCategoryId,
                'status' => AIContentJobStatus::Pending,
                'input_payload' => [
                    'category' => $this->option('content-category') ?: 'Kiến thức HVAC',
                    'topic' => $this->argument('topic'),
                    'keyword' => $this->option('keyword'),
                    'intent' => $this->option('intent'),
                    'audience' => $this->option('audience'),
                    'product_id' => $this->option('product') ? (int) $this->option('product') : null,
                    'brand_id' => $this->option('brand') ? (int) $this->option('brand') : null,
                    'bulk_index' => $index,
                    'bulk_total' => $bulk,
                ],
            ]);

            $jobs[] = $job;
            $this->info("Đã tạo AiContentJob #{$job->id}: \"{$job->topic}\"");

            if ($this->option('sync')) {
                GenerateBlogDraftJob::dispatchSync($job->id);
            } else {
                GenerateBlogDraftJob::dispatch($job->id);
            }
        }

        if (! $this->option('sync')) {
            $this->line('Chạy worker: php artisan queue:work --queue=default');
        }

        $this->info('Hoàn tất tạo '.count($jobs).' job AI content.');

        return self::SUCCESS;
    }

    private function resolvePostCategoryId(): ?int
    {
        $category = $this->option('category');
        if (! $category) {
            return null;
        }

        $postCategory = PostCategory::find($category);
        if (! $postCategory) {
            $this->warn("Không tìm thấy category ID={$category}, bỏ qua.");

            return null;
        }

        return $postCategory->id;
    }
}
