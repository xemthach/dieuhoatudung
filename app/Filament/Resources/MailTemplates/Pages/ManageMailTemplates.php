<?php

namespace App\Filament\Resources\MailTemplates\Pages;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageMailTemplates extends ManageRecords
{
    protected static string $resource = MailTemplateResource::class;

    // ── Full width table ─────────────────────────────────────────────────
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Thêm template')
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalSubmitActionLabel('Tạo template')
                ->modalCancelActionLabel('Hủy'),
        ];
    }
}
