<?php

namespace App\Filament\Resources\MailTemplates\Pages;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Services\Mail\MailTemplateRenderer;
use App\Services\Settings\SettingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditMailTemplate extends EditRecord
{
    protected static string $resource = MailTemplateResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Preview action — read-only, never saves
            Actions\Action::make('preview')
                ->label('Xem trước')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn () => 'Preview: ' . $this->record->name)
                ->modalContent(function () {
                    $renderer = app(MailTemplateRenderer::class);

                    // Build a temporary model with current form state for live preview
                    $tempTemplate = $this->record->replicate();
                    $data = $this->form->getState();
                    $tempTemplate->fill($data);

                    $sample   = $renderer->getSamplePayload($tempTemplate->key);
                    $rendered = [
                        'subject' => $renderer->renderSubject($tempTemplate, $sample),
                        'html'    => $renderer->renderHtml($tempTemplate, $sample),
                    ];
                    return view('filament.mail-template-preview', [
                        'template' => $tempTemplate,
                        'rendered' => $rendered,
                        'sample'   => $sample,
                    ]);
                })
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->closeModalByClickingAway(false),

            // Send test email — does NOT save the template
            Actions\Action::make('send_test')
                ->label('Gửi test')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->closeModalByClickingAway(false)
                ->action(function () {
                    $testRecipient = app(SettingService::class)->get('mail.mail_test_recipient', '');
                    if (empty($testRecipient)) {
                        Notification::make()
                            ->title('Thiếu email nhận test')
                            ->body('Vào Site Settings > Mail Server và điền "Email nhận test".')
                            ->danger()->send();
                        return;
                    }

                    $renderer = app(MailTemplateRenderer::class);

                    // Use current form state (not yet saved) for the test
                    $tempTemplate = $this->record->replicate();
                    $tempTemplate->fill($this->form->getState());

                    $sample = array_merge(
                        $renderer->getSamplePayload($tempTemplate->key),
                        ['customer_email' => $testRecipient]
                    );

                    $mailResult = app(\App\Services\Mail\MailProviderService::class)->send(
                        payload: [
                            'to'      => $testRecipient,
                            'subject' => '[TEST] ' . $renderer->renderSubject($tempTemplate, $sample),
                            'html'    => $renderer->renderHtml($tempTemplate, $sample),
                            'text'    => $renderer->renderText($tempTemplate, $sample),
                        ],
                        eventKey:    'test',
                        templateKey: $tempTemplate->key
                    );

                    if ($mailResult['success']) {
                        Notification::make()
                            ->title('Đã gửi test mail thành công')
                            ->body("Tới: {$testRecipient}")
                            ->success()->send();
                    } else {
                        Notification::make()
                            ->title('Gửi thất bại')
                            ->body($mailResult['message'])
                            ->danger()->send();
                    }
                }),
        ];
    }
}
