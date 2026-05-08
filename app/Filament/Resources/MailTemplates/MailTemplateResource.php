<?php

namespace App\Filament\Resources\MailTemplates;

use App\Filament\Resources\MailTemplates\Pages\EditMailTemplate;
use App\Filament\Resources\MailTemplates\Pages\ManageMailTemplates;
use App\Models\MailTemplate;
use App\Services\Mail\MailTemplateRenderer;
use App\Services\Settings\SettingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use App\Filament\Traits\HasResourcePermissions;

class MailTemplateResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'mail_template.view',
        'create' => 'mail_template.create',
        'edit' => 'mail_template.edit',
        'delete' => 'mail_template.delete',
    ];

    protected static ?string $model = MailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'Mẫu Email';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 52;

    protected static ?string $recordTitleAttribute = 'name';

    // ── Form (used by Edit page) ─────────────────────────────────────────
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 12])
            ->components([

                // ══════════════════════════════════════════════════════════
                // MAIN AREA — left 8/12
                // ══════════════════════════════════════════════════════════
                \Filament\Schemas\Components\Group::make([

                    // ── Section 1: Basic Info ────────────────────────────
                    Section::make('Thông tin cơ bản')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            TextInput::make('key')
                                ->label('Template Key')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->helperText('Khóa duy nhất. Vd: lead_admin_notification')
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            TextInput::make('name')
                                ->label('Tên template')
                                ->required()
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            TextInput::make('subject')
                                ->label('Tiêu đề email (Subject)')
                                ->required()
                                ->helperText('Hỗ trợ biến {{customer_name}}, {{product_name}}, v.v.')
                                ->columnSpanFull(),

                            Select::make('locale')
                                ->label('Ngôn ngữ')
                                ->options(['vi' => 'Tiếng Việt', 'en' => 'English'])
                                ->default('vi')
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            Toggle::make('is_active')
                                ->label('Kích hoạt')
                                ->default(true)
                                ->columnSpan(['default' => 'full', 'md' => 1]),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),

                    // ── Section 2: Email Content ─────────────────────────
                    Section::make('Nội dung email')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Toggle::make('use_visual_editor')
                                ->label('Chế độ soạn thảo')
                                ->onIcon('heroicon-o-eye')
                                ->offIcon('heroicon-o-code-bracket')
                                ->onColor('success')
                                ->offColor('warning')
                                ->helperText('BẬT = Soạn thảo trực quan (khuyến nghị) · TẮT = HTML nâng cao')
                                ->default(true)
                                ->live()
                                ->columnSpanFull(),

                            // Visual Editor (default mode)
                            RichEditor::make('content_html')
                                ->label('Nội dung email (Soạn thảo trực quan)')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'blockquote', 'link',
                                    'undo', 'redo',
                                ])
                                ->helperText('Soạn nội dung chính của email. Hệ thống sẽ tự thêm header/footer chuyên nghiệp.')
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'style' => 'min-height:500px;',
                                ])
                                ->visible(fn ($get) => (bool) $get('use_visual_editor')),

                            // HTML Source (advanced mode)
                            Textarea::make('body_html')
                                ->label('HTML nâng cao (Source)')
                                ->rows(24)
                                ->helperText(
                                    ' Dành cho kỹ thuật. Chỉnh sửa toàn bộ HTML email bao gồm header/footer. ' .
                                    'Dùng {{variable}} để chèn dữ liệu động.'
                                )
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'style' => 'min-height:500px; font-family:"Fira Code","Cascadia Code","Consolas",monospace; font-size:12.5px; line-height:1.7; tab-size:2; resize:vertical;',
                                    'spellcheck' => 'false',
                                ])
                                ->visible(fn ($get) => ! (bool) $get('use_visual_editor')),
                        ]),

                    // ── Section 3: Plain Text (collapsible) ──────────────
                    Section::make('Nội dung plain text')
                        ->icon('heroicon-o-document-minus')
                        ->description('Tuỳ chọn — fallback khi email client không hỗ trợ HTML.')
                        ->schema([
                            Textarea::make('body_text')
                                ->label('Nội dung plain text')
                                ->rows(6)
                                ->columnSpanFull(),
                        ])
                        ->collapsed(),

                ])->columnSpan(['default' => 'full', 'lg' => 8]),

                // ══════════════════════════════════════════════════════════
                // SIDEBAR — right 4/12
                // ══════════════════════════════════════════════════════════
                \Filament\Schemas\Components\Group::make([

                    // ── Variables Panel ───────────────────────────────────
                    Section::make('Biến khả dụng')
                        ->icon('heroicon-o-hashtag')
                        ->schema([
                            View::make('filament.mail-template-variables-panel')
                                ->viewData(fn () => [
                                    'formRecord' => request()->route()?->parameter('record'),
                                    'sidebarMode' => true,
                                ]),
                        ]),

                    // ── Variables JSON (advanced, collapsed) ──────────────
                    Section::make('Biến JSON (nâng cao)')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Textarea::make('variables_json')
                                ->label('Biến JSON')
                                ->rows(4)
                                ->helperText('JSON array. Để trống = dùng registry.')
                                ->afterStateHydrated(function ($component, $state) {
                                    if (is_array($state)) {
                                        $component->state(
                                            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                        );
                                    }
                                })
                                ->dehydrateStateUsing(function ($state): mixed {
                                    if (is_string($state) && !empty(trim($state))) {
                                        $decoded = json_decode($state, true);
                                        return $decoded ?? $state;
                                    }
                                    return $state;
                                })
                                ->columnSpanFull(),
                        ])
                        ->collapsed(),

                ])->columnSpan(['default' => 'full', 'lg' => 4]),

            ]);
    }

    // ── Table ────────────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable(['name', 'key'])
                    ->description(fn (MailTemplate $record): string => $record->key)
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->iconColor('primary')
                    ->grow(true)
                    ->wrap(false),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(55)
                    ->tooltip(fn (MailTemplate $record): string => $record->subject)
                    ->wrap(false)
                    ->grow(true),

                TextColumn::make('locale')
                    ->label('Ngôn ngữ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vi' => 'success',
                        'en' => 'info',
                        default => 'gray',
                    })
                    ->grow(false),

                ToggleColumn::make('is_active')
                    ->label('Hoạt động')
                    ->grow(false),

                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->since()
                    ->sortable()
                    ->grow(false),
            ])

            ->emptyStateIcon(Heroicon::OutlinedEnvelope)
            ->emptyStateHeading('Chưa có mẫu email nào')
            ->emptyStateDescription('Nhấn "Thêm template" để tạo mẫu email đầu tiên.')
            ->defaultSort('key')
            ->filters([])

            // ── Record actions ───────────────────────────────────────────
            ->recordActions([
                // Edit → dedicated page (no more modal)
                Action::make('edit')
                    ->label('Sửa')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (MailTemplate $record): string =>
                        static::getUrl('edit', ['record' => $record])
                    ),

                // Preview — read-only modal, never saves
                Action::make('preview')
                    ->label('Xem trước')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (MailTemplate $record): string => 'Preview: ' . $record->name)
                    ->modalContent(function (MailTemplate $record) {
                        $renderer = app(MailTemplateRenderer::class);
                        $sample = $renderer->getSamplePayload($record->key);
                        $rendered = [
                            'subject' => $renderer->renderSubject($record, $sample),
                            'html' => $renderer->renderHtml($record, $sample),
                        ];
                        return view('filament.mail-template-preview', [
                            'template' => $record,
                            'rendered' => $rendered,
                            'sample' => $sample,
                        ]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->closeModalByClickingAway(false),

                // Send test email — does NOT mutate record
                Action::make('send_test')
                    ->label('Gửi test')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->closeModalByClickingAway(false)
                    ->action(function (MailTemplate $record) {
                        $testRecipient = app(SettingService::class)->get('mail.mail_test_recipient', '');
                        if (empty($testRecipient)) {
                            Notification::make()
                                ->title('Thiếu email nhận test')
                                ->body('Vào Site Settings > Mail Server và điền "Email nhận test".')
                                ->danger()->send();
                            return;
                        }

                        $renderer = app(MailTemplateRenderer::class);
                        $sample = array_merge(
                            $renderer->getSamplePayload($record->key),
                            ['customer_email' => $testRecipient]
                        );

                        $mailResult = app(\App\Services\Mail\MailProviderService::class)->send(
                            payload: [
                                'to' => $testRecipient,
                                'subject' => '[TEST] ' . $renderer->renderSubject($record, $sample),
                                'html' => $renderer->renderHtml($record, $sample),
                                'text' => $renderer->renderText($record, $sample),
                            ],
                            eventKey: 'test',
                            templateKey: $record->key
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

                // Reset to default
                Action::make('reset_default')
                    ->label('Reset')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset về mặc định?')
                    ->modalDescription('Template sẽ được reset về nội dung mặc định ban đầu. Thao tác này không thể hoàn tác.')
                    ->closeModalByClickingAway(false)
                    ->action(function (MailTemplate $record) {
                        (new \Database\Seeders\MailTemplateSeeder())->run();
                        $record->update(['reset_at' => now()]);
                        Notification::make()
                            ->title('Đã reset templates về mặc định')
                            ->success()->send();
                    }),

                DeleteAction::make()->label('Xóa'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Xóa đã chọn'),
                ]),
            ]);
    }

    // ── Pages ────────────────────────────────────────────────────────────
    public static function getPages(): array
    {
        return [
            'index' => ManageMailTemplates::route('/'),
            'edit' => EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
