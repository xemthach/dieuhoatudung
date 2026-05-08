<?php

namespace App\Filament\Resources\ProductQuestions\Pages;

use App\Filament\Resources\ProductQuestions\ProductQuestionResource;
use App\Services\Mail\MailDispatchService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProductQuestion extends EditRecord
{
    protected static string $resource = ProductQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        // Gửi mail cho khách khi admin trả lời + status = answered/approved + khách có email
        if (
            !empty($record->answer) &&
            !empty($record->customer_email) &&
            in_array($record->status, ['answered', 'approved'])
        ) {
            // Chỉ gửi nếu answer vừa được thêm/thay đổi
            if ($record->wasChanged('answer') || $record->wasChanged('status')) {
                try {
                    app(MailDispatchService::class)->sendCustomerEvent(
                        event:         'question_customer',
                        customerEmail: $record->customer_email,
                        vars: [
                            'customer_name' => $record->customer_name,
                            'question'      => $record->question,
                            'answer'        => $record->answer,
                            'product_name'  => $record->product?->name ?? '—',
                        ],
                        relatedType: 'ProductQuestion',
                        relatedId:   $record->id
                    );
                } catch (\Throwable $e) {
                    Log::error('Question answered customer mail failed: ' . $e->getMessage());
                }
            }
        }
    }
}
