<?php

namespace App\Filament\Resources\ProductReviews\Pages;

use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Services\Mail\MailDispatchService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProductReview extends EditRecord
{
    protected static string $resource = ProductReviewResource::class;

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

        // Gửi mail cho khách khi admin duyệt đánh giá qua Edit form
        if (
            $record->status === 'approved' &&
            !empty($record->customer_email) &&
            $record->wasChanged('status')
        ) {
            try {
                app(MailDispatchService::class)->sendCustomerEvent(
                    event:         'review_customer',
                    customerEmail: $record->customer_email,
                    vars: [
                        'customer_name' => $record->customer_name,
                        'product_name'  => $record->product?->name ?? '—',
                    ],
                    relatedType: 'ProductReview',
                    relatedId:   $record->id
                );
            } catch (\Throwable $e) {
                Log::error('Review approved customer mail failed (edit): ' . $e->getMessage());
            }
        }
    }
}
