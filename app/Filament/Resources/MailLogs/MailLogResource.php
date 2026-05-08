<?php

namespace App\Filament\Resources\MailLogs;

use App\Filament\Resources\MailLogs\Pages\ManageMailLogs;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Services\Mail\MailProviderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Filament\Traits\HasResourcePermissions;

class MailLogResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'mail_log.view',
        'create'  => null,
        'edit'    => null,
        'delete'  => 'mail_log.delete',
    ];

    protected static ?string $model = MailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Mail Logs';

    protected static ?int $navigationSort = 53;

    protected static ?string $recordTitleAttribute = 'subject';

    /** Show badge count of failed mails in nav */
    public static function getNavigationBadge(): ?string
    {
        $failed = MailLog::failed()->where('created_at', '>=', now()->subDays(7))->count();
        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        // Read-only view form — mail logs should not be edited
        return $schema->components([]);
    }    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                // Status badge
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent'    => 'success',
                        'failed'  => 'danger',
                        'skipped' => 'warning',
                        default   => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'sent'    => 'heroicon-o-check-circle',
                        'failed'  => 'heroicon-o-x-circle',
                        'skipped' => 'heroicon-o-minus-circle',
                        default   => 'heroicon-o-clock',
                    })
                    ->sortable()
                    ->grow(false),

                // Provider badge
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'brevo'    => 'success',
                        'smtp'     => 'info',
                        'mailgun'  => 'warning',
                        'sendgrid' => 'primary',
                        default    => 'gray',
                    })
                    ->sortable()
                    ->grow(false),

                // Recipient + event as secondary text
                TextColumn::make('to_email')
                    ->label('Gửi tới')
                    ->description(fn (MailLog $record): string =>
                        MailLog::eventLabels()[$record->event_key] ?? ($record->event_key ?? '—')
                    )
                    ->searchable()
                    ->copyable()
                    ->wrap(false)
                    ->grow(true),

                // Subject
                TextColumn::make('subject')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->limit(55)
                    ->tooltip(fn (MailLog $record): string => $record->subject ?? '')
                    ->wrap(false)
                    ->grow(true),

                // Error message — only visible/useful when failed
                TextColumn::make('error_message')
                    ->label('Lỗi')
                    ->limit(40)
                    ->tooltip(fn (MailLog $record): ?string => $record->error_message)
                    ->color('danger')
                    ->wrap(false)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->grow(true),

                // Sent at timestamp
                TextColumn::make('sent_at')
                    ->label('Gửi lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->wrap(false)
                    ->grow(false),

                // Created at
                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->since()
                    ->sortable()
                    ->wrap(false)
                    ->grow(false)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Status filter
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'sent'    => 'Đã gửi',
                        'failed'  => 'Thất bại',
                        'skipped' => 'Bỏ qua',
                    ]),

                // Event filter
                SelectFilter::make('event_key')
                    ->label('Loại sự kiện')
                    ->options(MailLog::eventLabels()),

                // Provider filter
                SelectFilter::make('provider')
                    ->label('Provider')
                    ->options([
                        'brevo'    => 'Brevo',
                        'smtp'     => 'SMTP',
                        'mailgun'  => 'Mailgun',
                        'sendgrid' => 'SendGrid',
                        'none'     => 'None (skipped)',
                    ]),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->recordActions([
                // ── View detail ─────────────────────────────────────────
                Action::make('view_detail')
                    ->label('Chi tiết')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalContent(fn (MailLog $record): \Illuminate\Contracts\View\View =>
                        view('filament.mail-log-detail', ['log' => $record])
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),

                // ── Resend action ────────────────────────────────────────
                Action::make('resend')
                    ->label('Gửi lại')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (MailLog $record): string =>
                        "Gửi lại mail tới: {$record->to_email}\nSự kiện: {$record->event_key}"
                    )
                    ->visible(fn (MailLog $record): bool =>
                        in_array($record->status, ['failed', 'skipped'])
                    )
                    ->action(function (MailLog $record, MailProviderService $mailService) {
                        if (empty($record->to_email)) {
                            Notification::make()
                                ->title('Không thể gửi lại: thiếu địa chỉ email')
                                ->danger()->send();
                            return;
                        }

                        // Re-render template with vars from related model
                        $html    = null;
                        $subject = '[Gửi lại] ' . ($record->subject ?? '');

                        if ($record->template_key) {
                            $template = MailTemplate::findActive($record->template_key);
                            if ($template) {
                                $vars = $this->buildResendVars($record);
                                $renderer = app(\App\Services\Mail\MailTemplateRenderer::class);
                                $subject  = '[Gửi lại] ' . $renderer->renderSubject($template, $vars);
                                $html     = $renderer->renderHtml($template, $vars);
                            }
                        }

                        $result = $mailService->send(
                            payload: [
                                'to'      => $record->to_email,
                                'subject' => $subject,
                                'html'    => $html ?? '<p>Nội dung không khả dụng — gửi lại thủ công.</p>',
                            ],
                            eventKey:    $record->event_key ?? 'resend',
                            templateKey: $record->template_key ?? '',
                            relatedType: $record->related_type,
                            relatedId:   $record->related_id
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title('Đã gửi lại thành công')
                                ->body("Tới: {$record->to_email}")
                                ->success()->send();
                        } else {
                            Notification::make()
                                ->title('Gửi lại thất bại')
                                ->body($result['message'])
                                ->danger()->send();
                        }
                    }),

                // ── Delete ───────────────────────────────────────────────
                DeleteAction::make()->label('Xóa'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Xóa đã chọn'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMailLogs::route('/'),
        ];
    }

    /**
     * Rebuild template variables from the related model for resend.
     */
    private static function buildResendVars(MailLog $record): array
    {
        $settingService = app(\App\Services\Settings\SettingService::class);
        $vars = [
            'site_name'   => $settingService->get('general.site_name', config('app.name')),
            'hotline'     => $settingService->get('contact.hotline', ''),
            'website_url' => config('app.url'),
            'admin_url'   => config('app.url') . '/admin',
        ];

        if (!$record->related_type || !$record->related_id) {
            return $vars;
        }

        // Resolve model from related_type
        $model = match ($record->related_type) {
            'QuoteRequest' => \App\Models\QuoteRequest::find($record->related_id),
            'Lead'         => \App\Models\Lead::find($record->related_id),
            'ProductReview'   => \App\Models\ProductReview::with('product')->find($record->related_id),
            'ProductQuestion' => \App\Models\ProductQuestion::with('product')->find($record->related_id),
            'BtuCalculation'  => \App\Models\BtuCalculation::find($record->related_id),
            default => null,
        };

        if (!$model) {
            return $vars;
        }

        return match ($record->related_type) {
            'QuoteRequest' => array_merge($vars, [
                'quote_id'       => $model->id,
                'customer_name'  => $model->full_name ?? '—',
                'customer_phone' => $model->phone ?? '—',
                'customer_email' => $model->email ?? '—',
                'project_type'   => $model->project_type ?? '—',
                'budget_range'   => $model->budget_range ?? '—',
                'btu'            => $model->btu ? number_format($model->btu) : '—',
                'message'        => $model->message ?? '—',
                'source'         => $model->source_page ?? '—',
            ]),
            'Lead' => array_merge($vars, [
                'customer_name'  => $model->full_name ?? '—',
                'customer_phone' => $model->phone ?? '—',
                'customer_email' => $model->email ?? '—',
                'need_type'      => $model->need_type ?? '—',
                'area'           => $model->area ? $model->area . 'm²' : '—',
                'message'        => $model->message ?? '—',
                'source'         => $model->source_page ?? '—',
            ]),
            'ProductReview' => array_merge($vars, [
                'customer_name' => $model->customer_name ?? '—',
                'product_name'  => $model->product?->name ?? '—',
                'rating'        => $model->rating ?? '—',
                'content'       => $model->content ?? '—',
                'status'        => $model->status ?? '—',
            ]),
            'ProductQuestion' => array_merge($vars, [
                'customer_name' => $model->customer_name ?? '—',
                'product_name'  => $model->product?->name ?? '—',
                'question'      => $model->question ?? '—',
                'answer'        => $model->answer ?? '—',
            ]),
            'BtuCalculation' => array_merge($vars, [
                'customer_name'  => '—',
                'area'           => ($model->area_m2 ?? '') . 'm²',
                'need_type'      => 'BTU Calculator',
                'message'        => 'BTU: ' . number_format($model->recommended_btu ?? 0),
            ]),
            default => $vars,
        };
    }
}

