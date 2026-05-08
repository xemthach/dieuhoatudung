<?php

namespace App\Console\Commands;

use App\Enums\AIContentJobStatus;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\AiContentJob;
use App\Models\PostCategory;
use Illuminate\Console\Command;

class GenerateBlogDraft extends Command
{
    protected $signature = 'ai:generate-blog
                            {topic? : Chủ đề bài viết}
                            {--keyword= : Từ khóa chính (mặc định = topic)}
                            {--intent=informational : Search intent (informational/commercial/transactional)}
                            {--category= : ID danh mục bài viết}
                            {--sync : Chạy đồng bộ thay vì queue}';

    protected $description = 'Tạo bài viết blog bằng Gemini AI';

    public function handle(): int
    {
        // Kiểm tra API key
        if (empty(config('gemini.api_key'))) {
            $this->error('GEMINI_API_KEY chưa được cấu hình trong .env');
            return self::FAILURE;
        }

        // Lấy topic
        $topic = $this->argument('topic')
            ?? $this->ask('Nhập chủ đề bài viết (vd: "Điều hòa tủ đứng Daikin 36000 BTU")');

        if (empty($topic)) {
            $this->error('Topic không được trống.');
            return self::FAILURE;
        }

        $keyword = $this->option('keyword') ?? $topic;
        $intent = $this->option('intent');
        $category = $this->option('category');

        // Xác nhận category nếu có
        $categoryId = null;
        if ($category) {
            $cat = PostCategory::find($category);
            if (!$cat) {
                $this->warn("Không tìm thấy category ID={$category}, bỏ qua.");
            } else {
                $categoryId = $cat->id;
            }
        }

        // Tạo AiContentJob record
        $job = AiContentJob::create([
            'topic' => $topic,
            'primary_keyword' => $keyword,
            'intent' => $intent,
            'post_category_id' => $categoryId,
            'status' => AIContentJobStatus::Pending,
            'input_payload' => [
                'topic' => $topic,
                'keyword' => $keyword,
                'intent' => $intent,
            ],
        ]);

        $this->info(" Đã tạo AiContentJob #{$job->id}: \"{$topic}\"");

        if ($this->option('sync')) {
            $this->info('Chạy đồng bộ (sync)...');
            GenerateBlogDraftJob::dispatchSync($job->id);
            $job->refresh();
            $this->line('');
            $this->info(" Hoàn thành! Status: {$job->status->label()}");
            $this->line(" Xem trong Admin → AI Content Jobs → #{$job->id}");
        } else {
            GenerateBlogDraftJob::dispatch($job->id);
            $this->info(" Job đã được đưa vào queue.");
            $this->line(" Chạy worker: php artisan queue:work --queue=default");
            $this->line(" Xem trong Admin → AI Content Jobs → #{$job->id}");
        }

        return self::SUCCESS;
    }
}
