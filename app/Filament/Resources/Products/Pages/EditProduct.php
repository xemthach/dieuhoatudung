<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Services\Product\AIProductContentSystem;
use App\Services\Seo\InternalLinkSuggestionService;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ai_product_generate')
                ->label('Generate AI Product')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->form($this->aiConfigForm())
                ->action(function (array $data) {
                    $config = $this->normalizeAiConfig($data);
                    $job = AiProductJob::create(array_merge([
                        'type' => 'single_product_preview',
                        'scope' => 'selected',
                        'status' => 'queued',
                        'total' => 1,
                        'config_json' => $config,
                        'created_by' => auth()->id(),
                    ], SchemaColumns::existing('ai_product_jobs', [
                        'module' => 'ai_product_content',
                        'queue_name' => 'ai',
                    ])));
                    $item = $job->items()->create(array_merge([
                        'product_id' => $this->record->id,
                        'status' => 'queued',
                    ], SchemaColumns::existing('ai_product_job_items', [
                        'module' => 'ai_product_content',
                        'queue_name' => 'ai',
                    ])));

                    $this->record->update(['ai_status' => 'queued', 'ai_error_message' => null]);
                    AiProductContentSingleJob::dispatch($this->record->id, $job->id, $item->id)->onQueue('ai');

                    Notification::make()
                        ->title('Đã tạo AI Product Job')
                        ->body("Job #{$job->id} đang chờ queue. Draft sẽ không ghi đè cho tới khi bạn bấm Apply.")
                        ->success()
                        ->persistent()
                        ->send();
                }),

            Action::make('ai_apply_latest_draft')
                ->label('Preview / Apply AI Draft')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->modalHeading('Preview AI Product Draft')
                ->modalContent(fn () => view('filament.product-ai-preview', [
                    'item' => $this->record->aiProductJobItems()->whereNotNull('generated_payload_json')->latest('id')->first(),
                ]))
                ->modalSubmitActionLabel('Apply latest draft')
                ->action(function () {
                    $item = app(AIProductContentSystem::class)->applyLatestDraft($this->record, auth()->id());
                    $notification = Notification::make()
                        ->title($item ? 'Đã apply AI draft mới nhất' : 'Chưa có AI draft để apply');

                    ($item ? $notification->success() : $notification->warning())->send();
                }),

            Action::make('ai_rollback_latest')
                ->label('Rollback AI Content')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Khôi phục bản backup gần nhất trước khi AI ghi đè nội dung sản phẩm.')
                ->action(function () {
                    $version = app(AIProductContentSystem::class)->rollback($this->record);
                    $notification = Notification::make()
                        ->title($version ? 'Đã rollback nội dung sản phẩm' : 'Không có bản backup để rollback');

                    ($version ? $notification->success() : $notification->warning())->send();
                }),

            Action::make('generate_link_suggestions')
                ->label('Gợi ý Internal Links')
                ->icon('heroicon-o-link')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Tạo gợi ý liên kết nội bộ')
                ->modalDescription('Hệ thống sẽ phân tích sản phẩm này và gợi ý các trang liên quan (bài viết, sản phẩm cùng BTU, cùng thương hiệu...)')
                ->action(function () {
                    try {
                        $service = app(InternalLinkSuggestionService::class);
                        $suggestions = $service->generateForModel($this->record, force: true);

                        Notification::make()
                            ->title("Đã tạo {$suggestions->count()} gợi ý internal link.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Lỗi khi tạo gợi ý: '.$e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    private function aiConfigForm(): array
    {
        return [
            CheckboxList::make('outputs')
                ->label('Output cần tạo')
                ->options([
                    'content' => 'Nội dung',
                    'seo' => 'SEO',
                    'merchant' => 'Google Merchant',
                    'tags' => 'Tags',
                    'faq' => 'FAQ kỹ thuật',
                    'internal_links' => 'Internal links',
                    'og' => 'OG metadata',
                ])
                ->default(['content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og'])
                ->columns(2),
            Select::make('mode')
                ->label('Mode')
                ->options([
                    'missing_only' => 'Generate only missing fields',
                    'rewrite_all' => 'Rewrite all',
                    'rewrite_weak' => 'Rewrite only weak content',
                    'force_overwrite' => 'Force overwrite',
                ])
                ->default('missing_only')
                ->required(),
            Select::make('depth')
                ->label('Depth')
                ->options([
                    'basic' => 'Basic',
                    'seo' => 'SEO chuẩn',
                    'deep_hvac' => 'Chuyên sâu HVAC',
                ])
                ->default('seo')
                ->required(),
            Select::make('tone')
                ->label('Tone')
                ->options([
                    'hvac_expert' => 'Chuyên gia HVAC',
                    'technical_consulting' => 'Tư vấn kỹ thuật',
                    'soft_sales' => 'Bán hàng nhẹ',
                    'b2b_project' => 'B2B công trình',
                ])
                ->default('hvac_expert')
                ->required(),
        ];
    }

    private function normalizeAiConfig(array $data): array
    {
        $selectedOutputs = array_fill_keys($data['outputs'] ?? [], true);

        return [
            'action' => 'single_product_preview',
            'mode' => $data['mode'] ?? 'missing_only',
            'depth' => $data['depth'] ?? 'seo',
            'tone' => $data['tone'] ?? 'hvac_expert',
            'batch_size' => 1,
            'apply_mode' => 'needs_review',
            'outputs' => [
                'content' => ! empty($selectedOutputs['content']),
                'seo' => ! empty($selectedOutputs['seo']),
                'merchant' => ! empty($selectedOutputs['merchant']),
                'tags' => ! empty($selectedOutputs['tags']),
                'faq' => ! empty($selectedOutputs['faq']),
                'internal_links' => ! empty($selectedOutputs['internal_links']),
                'og' => ! empty($selectedOutputs['og']),
            ],
        ];
    }
}
