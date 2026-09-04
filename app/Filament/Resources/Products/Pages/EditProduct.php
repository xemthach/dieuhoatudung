<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Filament\Resources\Products\ProductResource;
use App\Jobs\AiProductContentSingleJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\AiProductDraft;
use App\Models\Product;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductDraftApplyService;
use App\Services\Product\ProductTechnicalSpecWriter;
use App\Services\AI\AIWorkerReadinessService;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\ProductAiApplyReadiness;
use App\Services\AI\ProductAiActionResolver;
use App\Services\AI\ProductAiGenerationReadiness;
use App\Services\AI\SingleOperatorControlledRolloutPolicy;
use App\Services\Seo\InternalLinkSuggestionService;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return (string) ($this->record->name ?? 'Product');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl('index') => 'Product',
            'Edit',
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $technicalSubmission = $data;
        $technicalSubmission['phase'] = $data['technical_phase'] ?? null;
        $technicalSubmission['frequency'] = $data['technical_frequency'] ?? null;

        $override = app(ProductTechnicalSpecWriter::class)->manualOverrideAttributes(
            $this->record,
            $technicalSubmission,
            $data['technical_specs_override_reason'] ?? null,
        );

        // These virtual form fields are persisted by the technical override
        // service in specs_json, never as phantom Product columns.
        unset($data['technical_phase'], $data['technical_frequency']);

        return array_replace($data, $override);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ai_product_generate')
                ->label('Tạo nội dung AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->authorize(fn (): bool => auth()->user()?->can('product.ai_generate') ?? false)
                ->visible(fn (): bool => $this->aiActionPolicy()['can_generate_primary'] && $this->canRunAiMutation('GENERATE'))
                ->modalDescription('AI chỉ tạo Nội dung, SEO, Google Merchant, Tags, FAQ và Internal links. AI không tạo hoặc sửa Thông tin cơ bản, giá, model/SKU, brand/category hay Thông số kỹ thuật.')
                ->form($this->aiConfigForm())
                ->action(fn (array $data) => $this->queueAiGeneration($data)),
            Action::make('ai_preview_latest_draft')
                ->label(fn (): string => match ($this->aiActionPolicy()['current_state']) {
                    'BLOCKED', 'HARD_BLOCKED' => 'Xem lý do bị chặn',
                    'APPLIED' => 'Xem nội dung AI',
                    default => 'Xem bản nháp',
                })
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize(fn (): bool => (auth()->user()?->can('product.ai_generate') ?? false)
                    || (auth()->user()?->can('bulk_ai_approve') ?? false)
                    || (auth()->user()?->can('bulk_ai_apply') ?? false)
                    || (auth()->user()?->can('bulk_ai_view') ?? false))
                ->visible(fn (): bool => $this->aiActionPolicy()['can_preview']
                    || $this->aiActionPolicy()['can_view_block_reason'])
                ->modalHeading('Xem trước bản nháp AI')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Đóng')
                ->modalContent(fn () => view('filament.product-ai-preview', [
                    'item' => $this->latestAiDraftItem(),
                    'readiness' => $this->latestApplyReadiness(),
                ])),
            Action::make('ai_approve_latest_draft')
                ->label(fn (): string => $this->latestDraftWarnings() === []
                    ? 'Duyệt'
                    : 'Duyệt kèm cảnh báo')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->aiActionPolicy()['can_approve'] && $this->canRunAiMutation('APPROVE'))
                ->modalDescription(fn (): string => $this->latestDraftWarnings() === []
                    ? 'Approval chỉ ghi nhận quyết định review; draft vẫn chưa được apply cho tới khi bấm Apply.'
                    : 'Nội dung còn cảnh báo chất lượng: '.implode(', ', $this->latestDraftWarnings()).'. Bạn vẫn muốn duyệt? Cảnh báo sẽ được giữ trong audit.')
                ->modalContent(fn () => view('filament.product-ai-preview', [
                    'item' => $this->latestAiDraftItem(),
                    'readiness' => $this->latestApplyReadiness(),
                ]))
                ->action(function () {
                    $draft = app(AiProductContentStateResolver::class)->reviewableDraft($this->record);
                    if (! $draft) {
                        Notification::make()->title('Chưa có AI draft để duyệt')->warning()->send();
                        return;
                    }
                    try {
                        $warnings = (array) ($draft->warnings_json ?? []);
                        $note = $warnings === []
                            ? 'Approved from Product review UI'
                            : '[WARNING_OVERRIDE] Approved with warnings: '.implode(', ', $warnings);
                        app(AIProductDraftApplyService::class)->approve($draft, (int) auth()->id(), auth()->user(), $note, null, $warnings !== []);
                        Notification::make()->title('Draft đã được duyệt, chưa apply')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Không thể duyệt draft')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('ai_processing_status')
                ->label('AI đang tạo nội dung…')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->disabled()
                ->visible(fn (): bool => $this->aiActionPolicy()['show_processing_status']),
            Action::make('ai_apply_latest_draft')
                ->label('Áp dụng')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('bulk_ai_apply') ?? false)
                ->visible(fn (): bool => $this->aiActionPolicy()['can_apply'] && $this->canRunAiMutation('APPLY'))
                ->requiresConfirmation()
                ->modalHeading('Xác nhận áp dụng nội dung AI')
                ->modalDescription('Đây là bước xác nhận ghi dữ liệu riêng biệt với quyết định Duyệt. Product sẽ được khóa và kiểm tra stale-content trước khi cập nhật.')
                ->modalContent(fn () => view('filament.product-ai-preview', [
                    'item' => $this->latestAiDraftItem(),
                    'readiness' => $this->latestApplyReadiness(),
                    'applyConfirmation' => true,
                ]))
                ->form([
                    Placeholder::make('confirmation_instruction')
                        ->label('Xác nhận bắt buộc')
                        ->content(fn (): string => 'Nhập chính xác: '.($this->latestApplyReadiness()['confirmation'] ?? '')),
                    TextInput::make('apply_confirmation')
                        ->label('Mã xác nhận áp dụng')
                        ->required()
                        ->autocomplete(false),
                ])
                ->modalSubmitActionLabel('Xác nhận và áp dụng')
                ->action(function (array $data) {
                    $draftModel = app(AiProductContentStateResolver::class)
                        ->approvedUnappliedDraft($this->record);

                    if (! $draftModel) {
                        Notification::make()
                            ->title('Chưa có AI draft để apply')
                            ->warning()
                            ->send();

                        return;
                    }
                    try {
                        $result = app(AIProductDraftApplyService::class)->apply(
                            $draftModel,
                            (int) auth()->id(),
                            false,
                            (string) ($data['apply_confirmation'] ?? ''),
                        );
                        Notification::make()->title($result['result'] === 'NOOP_ALREADY_APPLIED' ? 'Nội dung AI đã được áp dụng trước đó.' : 'Nội dung AI đã được áp dụng.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title($this->applyErrorMessage($e->getMessage()))
                            ->body('Mã kỹ thuật: '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            ActionGroup::make([
                $this->generateNewAiAction(),
                $this->regenerateAiAction(),
                $this->viewAiJobAction(),
                $this->rejectAiDraftAction(),
                $this->discardAiDraftAction(),
                $this->recoverAiRequestAction(),
                Action::make('ai_rollback_latest')
                    ->label('Rollback AI Content')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Khôi phục bản backup gần nhất trước khi AI ghi đè nội dung sản phẩm.')
                    ->action(function () {
                        $version = app(AIProductContentSystem::class)->rollback($this->record, auth()->user());
                        $notification = Notification::make()
                            ->title($version ? 'Đã rollback nội dung sản phẩm' : 'Không có bản backup để rollback');

                        ($version ? $notification->success() : $notification->warning())->send();
                    }),
                Action::make('generate_link_suggestions')
                    ->label('Gợi ý Internal Links')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Tạo gợi ý liên kết nội bộ')
                    ->modalDescription('Hệ thống sẽ phân tích sản phẩm này và gợi ý các trang liên quan: bài viết, sản phẩm cùng BTU, cùng thương hiệu...')
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
            ])
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /** @return array<string,mixed> */
    private function aiActionPolicy(): array
    {
        return app(ProductAiActionResolver::class)->resolve($this->record);
    }

    private function canRunAiMutation(string $action): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        $rollout = app(SingleOperatorControlledRolloutPolicy::class);
        if (! $rollout->active()) {
            return true;
        }

        try {
            $rollout->assertAction($actor, $action);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    private function latestApplyReadiness(): array
    {
        $draft = $this->latestAiDraftItem()?->draft;

        return app(ProductAiApplyReadiness::class)->resolve($draft);
    }

    private function applyErrorMessage(string $code): string
    {
        return match (true) {
            str_contains($code, 'APPLY_CONFIRMATION_REQUIRED') => 'Bạn cần xác nhận trước khi áp dụng nội dung AI.',
            str_contains($code, 'STALE_PRODUCT_CONTENT') => 'Sản phẩm đã được chỉnh sửa sau khi AI tạo bản nháp.',
            str_contains($code, 'STALE_TECHNICAL_CONTEXT') => 'Thông số kỹ thuật đã thay đổi sau khi bản nháp được duyệt.',
            str_contains($code, 'FACT_CHECK_BLOCKED') => 'Bản nháp còn claim kỹ thuật chưa được xác minh.',
            str_contains($code, 'FORBIDDEN') => 'Bạn không có quyền áp dụng nội dung AI.',
            default => 'Không thể áp dụng nội dung AI.',
        };
    }

    private function generateNewAiAction(): Action
    {
        return Action::make('ai_product_generate_new')
            ->label('Tạo nội dung AI mới')
            ->icon('heroicon-o-sparkles')
            ->authorize(fn (): bool => auth()->user()?->can('product.ai_generate') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_generate_more'] && $this->canRunAiMutation('GENERATE'))
            ->form($this->aiConfigForm())
            ->action(fn (array $data) => $this->queueAiGeneration($data));
    }

    private function regenerateAiAction(): Action
    {
        return Action::make('ai_regenerate_latest_draft')
            ->label('Tạo lại')
            ->icon('heroicon-o-arrow-path')
            ->authorize(fn (): bool => auth()->user()?->can('product.ai_generate') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_regenerate'] && $this->canRunAiMutation('GENERATE'))
            ->form($this->aiConfigForm())
            ->requiresConfirmation()
            ->modalHeading('Tạo bản nháp AI mới')
            ->modalDescription('Bản nháp hiện tại sẽ được đánh dấu REJECTED với lý do tạo lại; lịch sử và provider evidence vẫn được giữ nguyên.')
            ->action(function (array $data): void {
                $draft = app(AiProductContentStateResolver::class)->reviewableDraft($this->record);
                if (! $draft) {
                    Notification::make()->title('Không còn draft đang chờ duyệt')->warning()->send();
                    return;
                }

                $this->queueAiGeneration($data, $draft);
            });
    }

    private function viewAiJobAction(): Action
    {
        return Action::make('ai_view_job')
            ->label('Xem công việc AI')
            ->icon('heroicon-o-clipboard-document-list')
            ->authorize(fn (): bool => auth()->user()?->can('bulk_ai_view') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_view_job'])
            ->url(function (): ?string {
                $policy = $this->aiActionPolicy();
                $item = $policy['item'] ?: $policy['latest_history']['item'];

                return $item
                    ? AiProductJobResource::getUrl('edit', ['record' => $item->ai_product_job_id])
                    : null;
            });
    }

    private function rejectAiDraftAction(): Action
    {
        return Action::make('ai_reject_latest_draft')
            ->label('Từ chối')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_reject'] && $this->canRunAiMutation('REVIEW'))
            ->form([
                Textarea::make('review_note')
                    ->label('Lý do từ chối')
                    ->required()
                    ->minLength(3)
                    ->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->modalHeading('Từ chối bản nháp AI')
            ->modalSubmitActionLabel('Từ chối')
            ->action(function (array $data): void {
                $draft = app(AiProductContentStateResolver::class)->reviewableDraft($this->record);
                if (! $draft) {
                    Notification::make()->title('Chưa có AI draft để từ chối')->warning()->send();
                    return;
                }

                app(AIProductDraftApplyService::class)->reject(
                    $draft,
                    (int) auth()->id(),
                    (string) $data['review_note'],
                    auth()->user(),
                );
                Notification::make()->title('Đã từ chối draft AI')->success()->send();
            });
    }

    private function discardAiDraftAction(): Action
    {
        return Action::make('ai_discard_latest_draft')
            ->label('Loại bỏ')
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('bulk_ai_approve') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_discard'] && $this->canRunAiMutation('REVIEW'))
            ->form([
                Textarea::make('review_note')
                    ->label('Lý do loại bỏ')
                    ->required()
                    ->minLength(3)
                    ->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->modalDescription('Chỉ lưu trạng thái loại bỏ logic. Draft, job, token và provider evidence không bị xóa.')
            ->action(function (array $data): void {
                $draft = app(AiProductContentStateResolver::class)->reviewableDraft($this->record);
                if (! $draft) {
                    Notification::make()->title('Không còn draft đang chờ duyệt')->warning()->send();
                    return;
                }

                app(AIProductDraftApplyService::class)->discard(
                    $draft,
                    (int) auth()->id(),
                    (string) $data['review_note'],
                    auth()->user(),
                );
                Notification::make()->title('Đã loại bỏ draft AI; lịch sử vẫn được giữ')->success()->send();
            });
    }

    private function recoverAiRequestAction(): Action
    {
        return Action::make('ai_cancel_active_requests')
            ->label('Giải phóng yêu cầu đang treo')
            ->icon('heroicon-o-stop-circle')
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()?->can('product.ai_generate') ?? false)
            ->visible(fn (): bool => $this->aiActionPolicy()['can_recover'] && $this->canRunAiMutation('GENERATE'))
            ->requiresConfirmation()
            ->modalHeading('Hủy yêu cầu AI đang treo')
            ->modalDescription('Chỉ các yêu cầu queued/processing/stuck của Product này sẽ được đánh dấu đã hủy. Lịch sử AI và Product content không bị xóa.')
            ->action(function (): void {
                $jobs = \App\Models\AiProductJob::query()
                    ->whereHas('items', fn ($query) => $query
                        ->where('product_id', $this->record->id)
                        ->whereIn('canonical_status', \App\Services\AI\AiProductStateCompatibility::ACTIVE))
                    ->get();
                foreach ($jobs as $job) {
                    app(\App\Services\AI\AiProductLifecycleService::class)->requestCancel(
                        $job,
                        auth()->id(),
                        'Cancelled by operator from Product Edit recovery action.',
                    );
                }

                Notification::make()->title('Đã giải phóng yêu cầu AI đang treo')->success()->send();
            });
    }

    private function latestAiDraftItem(): ?AiProductJobItem
    {
        return app(AiProductContentStateResolver::class)->resolve($this->record)['item'];
    }

    /** @return array<int,string> */
    private function latestDraftWarnings(): array
    {
        $draft = app(AiProductContentStateResolver::class)->reviewableDraft($this->record);

        return array_values(array_filter(array_map('strval', (array) ($draft?->warnings_json ?? []))));
    }

    private function queueAiGeneration(array $data, ?AiProductDraft $supersededDraft = null): ?AiProductJob
    {
        if (! $supersededDraft) {
            $readiness = app(ProductAiGenerationReadiness::class)->resolve(
                $this->record->fresh(['brand', 'category']),
                \App\Services\Product\ProductContentEligibilityPolicy::LONG_DESCRIPTION,
            );
            if (! $readiness['can_generate']) {
                $blockers = $readiness['mandatory_blockers'] ?: [['message' => 'AI chưa sẵn sàng.', 'code' => 'NOT_READY']];
                app(\App\Services\AI\AITechnicalLogger::class)->event(
                    'ai_product_preflight',
                    'generation_preflight_blocked',
                    'Single Product generation stopped before job creation.',
                    [
                        'product_id' => $this->record->id,
                        'actor_id' => auth()->id(),
                        'guard_codes' => array_values(array_column($blockers, 'code')),
                        'next_actions' => $readiness['next_actions'] ?? [],
                        'provider_called' => false,
                    ],
                );
                Notification::make()
                    ->title('Chưa thể tạo nội dung AI')
                    ->body(collect($blockers)->map(
                        fn (array $blocker, int $index): string => ($index + 1).'. '.$blocker['message'].' ['.$blocker['code'].']'
                    )->implode("\n"))
                    ->warning()->persistent()->send();
                return null;
            }
        }

        [$job, $item, $created] = app(\App\Services\AI\AiProductLifecycleService::class)
            ->createGenerationOperation(
                $this->record,
                $this->normalizeAiConfig($data),
                auth()->user(),
                $supersededDraft,
            );

        if (! $created) {
            Notification::make()
                ->title('Product đang có yêu cầu AI hoạt động')
                ->body("Job #{$job->id} đã được tạo trước đó; không tạo thêm job trùng.")
                ->warning()
                ->persistent()
                ->send();

            return $job;
        }

        AiProductContentSingleJob::dispatch($this->record->id, $job->id, $item->id, $item->dispatch_uuid)
            ->onQueue(config('ai.governed_queue', 'ai_governed'));
        $worker = app(AIWorkerReadinessService::class)->snapshot();
        $notification = Notification::make()
            ->title('Đã gửi yêu cầu tạo nội dung')
            ->body("Job #{$job->id} đang chờ xử lý. Draft không ghi đè Product cho tới khi được duyệt và Apply. {$worker['message']}")
            ->status($worker['ready'] ? 'success' : 'warning')
            ->persistent();
        if (! $worker['ready'] && auth()->user()?->can('ai_worker.manage')) {
            $notification->actions([
                Action::make('manage_ai_worker')->label('Bật AI Worker')->url(\App\Filament\Pages\AIQueueHealth::getUrl()),
            ]);
        }
        $notification->send();

        return $job;
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
            Placeholder::make('ai_content_layer_notice')
                ->label('Phạm vi AI')
                ->content('AI chỉ tạo Nội dung, SEO, Google Merchant, Tags, FAQ, Internal links. AI không tạo/sửa Thông tin cơ bản, model/SKU, giá, brand/category hoặc Thông số kỹ thuật.'),
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
