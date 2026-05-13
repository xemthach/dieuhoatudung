<?php

namespace App\Filament\Resources\AiContentJobs\Pages;

use App\Enums\AIContentJobStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Tag;
use App\Services\AI\AIProviderPool;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditAiContentJob extends EditRecord
{
    protected static string $resource = AiContentJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dispatch')
                ->label('Chạy AI Generate')
                ->color('info')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Xác nhận chạy AI')
                ->modalDescription(fn () => 'AI sẽ tạo nội dung HVAC SEO cho: "'.$this->record->topic.'". Thời gian khoảng 1-3 phút.')
                ->modalSubmitActionLabel('Chạy ngay')
                ->visible(fn () => in_array($this->record->status, [
                    AIContentJobStatus::Pending,
                    AIContentJobStatus::Failed,
                ]))
                ->action(function () {
                    if (! app(AIProviderPool::class)->hasAvailableProviders()) {
                        Notification::make()
                            ->title('Chưa cấu hình AI Provider')
                            ->body('Hãy cấu hình một AI Provider đang hoạt động trước khi chạy.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    GenerateBlogDraftJob::dispatch($this->record->id);

                    Notification::make()
                        ->title('Job đã được đưa vào queue')
                        ->body('AI đang xử lý. Làm mới trang sau 1-3 phút để xem kết quả.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('publish_to_post')
                ->label('Publish thành bài viết')
                ->color('success')
                ->icon('heroicon-o-document-plus')
                ->requiresConfirmation()
                ->modalHeading('Publish thành bài viết blog?')
                ->modalDescription('Draft AI sẽ được tạo thành bài viết mới với trạng thái Draft. Bạn có thể chỉnh sửa trước khi đăng.')
                ->modalSubmitActionLabel('Tạo bài viết')
                ->visible(fn () => $this->record->status === AIContentJobStatus::Completed
                    && ! empty($this->record->output_draft))
                ->action(function () {
                    $record = $this->record;
                    $meta = is_array($record->output_meta) ? $record->output_meta : [];
                    $payload = is_array($record->input_payload) ? $record->input_payload : [];
                    $title = $meta['title'] ?? $record->topic;
                    $excerpt = $meta['excerpt'] ?? Str::limit(strip_tags($record->output_draft), 300);

                    $post = Post::create([
                        'title' => $title,
                        'slug' => $this->uniquePostSlug($meta['slug'] ?? $title),
                        'excerpt' => $excerpt,
                        'content' => $record->output_draft,
                        'status' => PostStatus::Draft,
                        'post_category_id' => $record->post_category_id,
                        'primary_keyword' => $record->primary_keyword,
                        'search_intent' => $record->intent,
                        'seo_title' => $meta['seo_title'] ?? $title,
                        'seo_description' => $meta['meta_description'] ?? Str::limit($excerpt, 155),
                        'og_title' => $meta['og_title'] ?? ($meta['seo_title'] ?? $title),
                        'og_description' => $meta['og_description'] ?? ($meta['meta_description'] ?? Str::limit($excerpt, 155)),
                        'ai_generated' => true,
                    ]);

                    $this->syncTags($post, $record->output_tags ?? []);
                    $this->syncFaq($post, $record->output_faq ?? []);

                    if (! empty($payload['product_id'])) {
                        $post->products()->syncWithoutDetaching([(int) $payload['product_id']]);
                    }

                    $record->update([
                        'status' => AIContentJobStatus::Reviewed,
                        'reviewed_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Bài viết đã được tạo')
                        ->body("Draft \"{$post->title}\" đã được tạo. Vào Admin -> Posts để chỉnh sửa và publish.")
                        ->success()
                        ->persistent()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            DeleteAction::make()
                ->label('Xoá')
                ->icon('heroicon-o-trash'),
        ];
    }

    private function uniquePostSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'ai-blog';
        $slug = $base;
        $counter = 1;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function syncTags(Post $post, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tagData) {
            $name = is_string($tagData) ? $tagData : ($tagData['name'] ?? null);
            if (blank($name)) {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['name' => trim($name)],
                [
                    'slug' => Str::slug($name),
                    'type' => is_array($tagData) ? ($tagData['type'] ?? 'topic') : 'topic',
                ]
            );
            $tagIds[] = $tag->id;
        }

        if ($tagIds) {
            $post->tags()->sync($tagIds);
        }
    }

    private function syncFaq(Post $post, array $faqItems): void
    {
        $sort = 1;

        foreach ($faqItems as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $faq = Faq::create([
                'question' => $item['question'],
                'answer' => $item['answer'],
                'group' => 'blog',
                'sort_order' => $sort,
                'is_active' => true,
            ]);

            $post->faqs()->attach($faq->id, ['sort_order' => $sort++]);
        }
    }
}
