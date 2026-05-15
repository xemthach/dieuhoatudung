<?php

namespace App\Filament\Resources\AiContentJobs\Pages;

use App\Enums\AIContentJobStatus;
use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Jobs\GenerateBlogDraftJob;
use App\Services\AI\AIProviderPool;
use App\Support\SchemaColumns;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAiContentJob extends CreateRecord
{
    protected static string $resource = AiContentJobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $payload = $data['input_payload'] ?? [];
        $category = $payload['category'] ?? 'Kiến thức HVAC';

        if (blank($data['topic'] ?? null)) {
            $data['topic'] = 'AI tự tạo topic - '.$category;
        }

        if (blank($data['intent'] ?? null)) {
            $data['intent'] = null;
        }

        $payload['category'] = $category;
        $data['input_payload'] = $payload;
        $data['status'] = AIContentJobStatus::Pending->value;
        $data = array_merge($data, SchemaColumns::existing('ai_content_jobs', [
            'module' => 'ai_blog',
            'queue_name' => 'ai',
        ]));
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if (app(AIProviderPool::class)->hasAvailableProviders()) {
            $this->record->update(['status' => AIContentJobStatus::Queued]);
            GenerateBlogDraftJob::dispatch($this->record->id)->onQueue('ai');

            Notification::make()
                ->title('AI đang xử lý')
                ->body('Job đã được đưa vào queue. Làm mới trang AI Content Jobs sau 1-3 phút.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Chưa cấu hình AI Provider')
            ->body('Job đã tạo nhưng chưa chạy. Hãy cấu hình một AI Provider đang hoạt động trước.')
            ->warning()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
