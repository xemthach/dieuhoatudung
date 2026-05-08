<?php

namespace App\Filament\Resources\AiContentJobs\Pages;

use App\Enums\AIContentJobStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Jobs\GenerateBlogDraftJob;
use App\Models\Post;
use App\Models\Tag;
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
            // Action 1: Dispatch job vào queue
            Action::make('dispatch')
                ->label('Chạy AI Generate')
                ->color('info')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Xác nhận chạy Gemini AI')
                ->modalDescription(fn () => 'Gemini sẽ tạo outline và draft cho: "' . $this->record->topic . '". Thời gian khoảng 1-3 phút.')
                ->modalSubmitActionLabel('Chạy ngay')
                ->visible(fn () => in_array($this->record->status, [
                    AIContentJobStatus::Pending,
                    AIContentJobStatus::Failed,
                ]))
                ->action(function () {
                    if (empty(config('gemini.api_key'))) {
                        Notification::make()
                            ->title('Thiếu GEMINI_API_KEY')
                            ->body('Vui lòng cấu hình GEMINI_API_KEY trong file .env trước khi chạy.')
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    GenerateBlogDraftJob::dispatch($this->record->id);

                    $this->record->update(['status' => AIContentJobStatus::Processing]);

                    Notification::make()
                        ->title('Job đã được đưa vào queue')
                        ->body('Gemini AI đang xử lý. Làm mới trang sau 1-3 phút để xem kết quả.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            // Action 2: Publish thành Post
            Action::make('publish_to_post')
                ->label('Publish thành bài viết')
                ->color('success')
                ->icon('heroicon-o-document-plus')
                ->requiresConfirmation()
                ->modalHeading('Publish thành bài viết blog?')
                ->modalDescription('Draft AI sẽ được tạo thành bài viết mới với trạng thái Draft. Bạn có thể chỉnh sửa trước khi đăng.')
                ->modalSubmitActionLabel('Tạo bài viết')
                ->visible(fn () => $this->record->status === AIContentJobStatus::Completed
                    && !empty($this->record->output_draft))
                ->action(function () {
                    $record = $this->record;

                    // Tạo excerpt từ 300 ký tự đầu của draft
                    $excerpt = Str::limit(strip_tags($record->output_draft), 300);

                    // Tạo Post với status draft
                    $post = Post::create([
                        'title' => $record->topic,
                        'slug' => Str::slug($record->topic),
                        'excerpt' => $excerpt,
                        'content' => $record->output_draft,
                        'status' => PostStatus::Draft,
                        'post_category_id' => $record->post_category_id,
                        'seo_title' => $record->topic,
                        'seo_description' => Str::limit($excerpt, 155),
                    ]);

                    // Gắn tags nếu có
                    if (!empty($record->output_tags)) {
                        $tagIds = [];
                        foreach ($record->output_tags as $tagData) {
                            if (!empty($tagData['name'])) {
                                $tag = Tag::firstOrCreate(
                                    ['name' => $tagData['name']],
                                    ['slug' => Str::slug($tagData['name'])]
                                );
                                $tagIds[] = $tag->id;
                            }
                        }
                        if ($tagIds) {
                            $post->tags()->sync($tagIds);
                        }
                    }

                    // Đánh dấu job đã reviewed
                    $record->update([
                        'status' => AIContentJobStatus::Reviewed,
                        'reviewed_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Bài viết đã được tạo!')
                        ->body("Draft \"{$post->title}\" đã được tạo. Vào Admin → Posts để chỉnh sửa và publish.")
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
}
