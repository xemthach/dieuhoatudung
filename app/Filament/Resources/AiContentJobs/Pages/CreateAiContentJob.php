<?php

namespace App\Filament\Resources\AiContentJobs\Pages;

use App\Enums\AIContentJobStatus;
use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Jobs\GenerateBlogDraftJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAiContentJob extends CreateRecord
{
    protected static string $resource = AiContentJobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = AIContentJobStatus::Pending->value;
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        // Tự động dispatch vào queue sau khi tạo
        if (!empty(config('gemini.api_key'))) {
            GenerateBlogDraftJob::dispatch($this->record->id);

            $this->record->update(['status' => AIContentJobStatus::Processing]);

            Notification::make()
                ->title('AI đang xử lý')
                ->body('Job đã được đưa vào queue. Làm mới trang AI Content Jobs sau 1-3 phút.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Chưa cấu hình GEMINI_API_KEY')
                ->body('Job đã tạo nhưng chưa chạy. Vào Edit → "Chạy AI Generate" sau khi cấu hình API key.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
