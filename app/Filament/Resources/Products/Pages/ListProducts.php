<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Traits\HasDataTransferActions;
use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AIQueueMonitor;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ListProducts extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = ProductResource::class;

    protected string $transferModule = 'product';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tạo mới product')
                ->icon('heroicon-o-plus')
                ->color('primary'),
            $this->aiToolsActionGroup(),
            $this->aiQueueHealthAction(),
            $this->dataActionGroup(),
        ];
    }

    private function aiToolsActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->aiGenerateFilteredAction(),
            $this->aiGenerateFilterAction(),
            $this->aiRetryAllFailedAction(),
            $this->aiRefreshStatusAction(),
            Action::make('ai_open_jobs')
                ->label('Open AI jobs')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn () => AiProductJobResource::getUrl('index'))
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false),
        ])
            ->label('AI Tools')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->button()
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false);
    }

    private function aiGenerateFilteredAction(): Action
    {
        return Action::make('ai_generate_filtered')
            ->label('Generate AI cho trang hiện tại')
            ->icon('heroicon-o-cpu-chip')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Xác nhận tạo nội dung AI')
            ->modalDescription('Scope: trang hiện tại. Action này chỉ chạy các sản phẩm đang hiển thị trên trang hiện tại.')
            ->modalSubmitActionLabel('Bắt đầu chạy AI')
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
            ->form([
                ...ProductsTable::aiConfigForm([
                    'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                ], 'missing_only', 'current_page', [
                    'current_page' => 'Trang hiện tại',
                ]),
            ])
            ->action(fn (array $data) => $this->dispatchAiGenerateFromHeader($data));
    }

    private function aiGenerateFilterAction(): Action
    {
        return Action::make('ai_generate_by_filter')
            ->label('Generate AI theo bộ lọc hiện tại')
            ->icon('heroicon-o-funnel')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Xác nhận tạo nội dung AI theo filter')
            ->modalDescription('Scope: theo bộ lọc hiện tại. Tác vụ này bỏ qua checkbox đang tick và có thể chạy rất nhiều sản phẩm.')
            ->modalSubmitActionLabel('Bắt đầu chạy AI theo filter')
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
            ->form([
                ...ProductsTable::aiConfigForm([
                    'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                ], 'missing_only', 'filter', [
                    'filter' => 'Theo bộ lọc hiện tại',
                ]),
                Checkbox::make('confirm_filter_scope')
                    ->label('Tôi hiểu action này sẽ chạy tất cả sản phẩm theo bộ lọc hiện tại và bỏ qua checkbox đang tick.')
                    ->accepted(),
            ])
            ->action(fn (array $data) => $this->dispatchAiGenerateFromHeader($data));
    }

    private function dispatchAiGenerateFromHeader(array $data): void
    {
        abort_unless(auth()->user()?->can('product.ai_generate'), 403);

        $scope = $data['scope'] ?? null;

        if (! in_array($scope, ['current_page', 'filter', 'all_filtered'], true)) {
            $this->logAiEmptySelection((string) $scope, $data);

            Notification::make()
                ->title('Scope không hợp lệ cho AI Tools')
                ->body('AI Tools không nhận checkbox selection. Hãy dùng Tác vụ hàng loạt > AI Product System để chạy sản phẩm đã chọn.')
                ->warning()
                ->send();

            return;
        }

        if (in_array($scope, ['filter', 'all_filtered'], true) && empty($data['confirm_filter_scope'])) {
            Log::warning('ai_filter_scope_confirmation_missing', [
                'source' => 'products_header_action',
                'user_id' => auth()->id(),
                'action' => 'generate_ai_content',
                'scope' => $scope,
                'route' => request()?->route()?->getName(),
                'timestamp' => now()->toIso8601String(),
            ]);

            Notification::make()
                ->title('Cần xác nhận phạm vi filter')
                ->body('Action theo filter có thể chạy rất nhiều sản phẩm và không dùng checkbox đang tick.')
                ->warning()
                ->send();

            return;
        }

        $productIds = $this->resolveAiProductIds($scope);
        $this->logAiSelectionPayload($scope, $productIds, $data);

        if ($productIds === []) {
            $this->logAiEmptySelection($scope, $data);

            Notification::make()
                ->title('Không có sản phẩm để xử lý')
                ->body('No valid product IDs were resolved for this scope.')
                ->warning()
                ->send();

            return;
        }

        $config = ProductsTable::normalizeAiActionData($data, 'generate_ai_content');
        $job = AiProductJob::create(array_merge([
            'type' => 'generate_ai_content',
            'scope' => $scope === 'all_filtered' ? 'filter' : $scope,
            'status' => 'queued',
            'total' => count($productIds),
            'config_json' => $config,
            'created_by' => auth()->id(),
        ], SchemaColumns::existing('ai_product_jobs', [
            'module' => 'ai_product_bulk',
            'queue_name' => 'ai',
            'current_page_ids_json' => $scope === 'current_page' ? $productIds : null,
            'filter_json' => in_array($scope, ['filter', 'all_filtered'], true) ? [
                'source' => 'products_header_action',
                'resolved_product_ids_sample' => array_slice($productIds, 0, 25),
            ] : null,
            'confirm_filter_scope' => in_array($scope, ['filter', 'all_filtered'], true) && ! empty($data['confirm_filter_scope']),
        ])));

        AiProductContentBatchJob::dispatch($job->id, $productIds)->onQueue('ai');
        $workerOffline = ! (bool) data_get(app(AIQueueMonitor::class)->health(), 'worker_heartbeat.is_running');

        Notification::make()
            ->title('Đã tạo AI Product Job')
            ->body("Job #{$job->id} sẽ xử lý ".count($productIds).' sản phẩm.'
                .($workerOffline ? ' AI worker offline: job đã vào queue nhưng cần bật worker để xử lý.' : ''))
            ->status($workerOffline ? 'warning' : 'success')
            ->persistent()
            ->send();
    }

    private function aiRefreshStatusAction(): Action
    {
        return Action::make('ai_refresh_status')
            ->label('Refresh AI status')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->url('#')
            ->extraAttributes([
                'data-ai-refresh-button' => '1',
                'onclick' => 'event.preventDefault(); window.ProductAiStatusPoller?.refreshNow?.();',
            ])
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false);
    }

    private function aiQueueHealthAction(): Action
    {
        return Action::make('ai_queue_health_badge')
            ->label('AI Queue')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->url('#')
            ->extraAttributes([
                'data-ai-queue-widget' => '1',
                'onclick' => 'event.preventDefault(); window.ProductAiStatusPoller?.refreshNow?.();',
            ])
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false);
    }

    private function aiRetryAllFailedAction(): Action
    {
        return Action::make('ai_retry_all_failed')
            ->label('Retry failed AI theo filter')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
            ->requiresConfirmation()
            ->modalDescription('Retry tất cả AI product items đang failed/stuck/cancelled trong filter hiện tại.')
            ->action(function () {
                abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                $productIds = $this->getFilteredTableQuery()
                    ->pluck('products.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $items = AiProductJobItem::query()
                    ->whereIn('product_id', $productIds)
                    ->whereIn('status', ['failed', 'stuck', 'cancelled'])
                    ->latest('id')
                    ->get();

                $count = ProductsTable::retryAiProductItems($items);

                Log::info('AI product retry all failed payload', [
                    'scope' => 'filter',
                    'filtered_count' => count($productIds),
                    'item_count' => $count,
                    'product_ids_sample' => array_slice($productIds, 0, 25),
                ]);

                Notification::make()
                    ->title($count > 0 ? "Đã retry {$count} AI item" : 'Không có AI item lỗi để retry')
                    ->status($count > 0 ? 'success' : 'warning')
                    ->send();
            });
    }

    private function dataActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
        ])
            ->label('Data')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->button();
    }

    private function resolveAiProductIds(string $scope): array
    {
        if (in_array($scope, ['filter', 'all_filtered'], true)) {
            return $this->getFilteredTableQuery()
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($scope === 'current_page') {
            $records = $this->getTableRecords();
            if ($records instanceof Paginator) {
                $records = collect($records->items());
            }

            return $records instanceof Collection
                ? $records->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [];
        }

        $selectedIds = $this->normalizeSelectedTableRecordIds($this->selectedTableRecords ?? []);

        if ($selectedIds === [] && ($this->isTrackingDeselectedTableRecords ?? false)) {
            return $this->getFilteredTableQuery()
                ->whereKeyNot($this->deselectedTableRecords ?? [])
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($selectedIds === []) {
            $selectedIds = $this->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false)
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($selectedIds === []) {
            return [];
        }

        return Product::query()
            ->whereKey($selectedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeSelectedTableRecordIds(array $state): array
    {
        $ids = [];

        foreach ($state as $key => $value) {
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
                continue;
            }

            if (is_numeric($key) && (int) $key > 0) {
                $ids[] = (int) $key;
            }
        }

        return array_values(array_unique($ids));
    }

    private function logAiSelectionPayload(string $scope, array $productIds, array $formData): void
    {
        Log::info('AI product bulk selection payload', [
            'source' => 'products_header_action',
            'user_id' => auth()->id(),
            'action' => 'generate_ai_content',
            'scope' => $scope,
            'selected_state_count' => count($this->selectedTableRecords ?? []),
            'selected_state_sample' => array_slice((array) ($this->selectedTableRecords ?? []), 0, 25, true),
            'deselected_state_count' => count($this->deselectedTableRecords ?? []),
            'is_tracking_deselected' => (bool) ($this->isTrackingDeselectedTableRecords ?? false),
            'resolved_count' => count($productIds),
            'resolved_ids_sample' => array_slice($productIds, 0, 25),
            'route' => request()?->route()?->getName(),
            'timestamp' => now()->toIso8601String(),
            'form_scope' => $formData['scope'] ?? null,
            'outputs' => $formData['outputs'] ?? [],
        ]);
    }

    private function logAiEmptySelection(string $scope, array $formData): void
    {
        Log::warning('bulk_selection_empty', [
            'source' => 'products_header_action',
            'user_id' => auth()->id(),
            'action' => 'generate_ai_content',
            'scope' => $scope,
            'selected_state_count' => count($this->selectedTableRecords ?? []),
            'selected_state_sample' => array_slice((array) ($this->selectedTableRecords ?? []), 0, 25, true),
            'deselected_state_count' => count($this->deselectedTableRecords ?? []),
            'is_tracking_deselected' => (bool) ($this->isTrackingDeselectedTableRecords ?? false),
            'route' => request()?->route()?->getName(),
            'timestamp' => now()->toIso8601String(),
            'form_scope' => $formData['scope'] ?? null,
        ]);
    }
}
