<?php

namespace App\Filament\Resources\Products\Pages;

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
use Filament\Actions\CreateAction;
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
            Action::make('ai_generate_filtered')
                ->label('AI Generate Content theo filter')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->modalDescription('Header action xử lý current page hoặc all filtered. Nếu muốn chạy đúng các checkbox đã chọn, dùng Tác vụ hàng loạt > AI Product System vì Filament chỉ truyền selected records cho bulk action.')
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
                ->form(ProductsTable::aiConfigForm([
                    'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                ], 'missing_only', 'current_page'))
                ->action(function (array $data) {
                    abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                    $scope = $data['scope'] ?? 'selected';
                    $productIds = $this->resolveAiProductIds($scope);
                    $this->logAiSelectionPayload($scope, $productIds, $data);

                    if ($productIds === []) {
                        Notification::make()
                            ->title($scope === 'selected' ? 'Chưa chọn sản phẩm' : 'Không có sản phẩm để xử lý')
                            ->body('Selection state không có ID hợp lệ. Hãy thử dùng bulk action trong bảng hoặc chọn lại sản phẩm.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $config = ProductsTable::normalizeAiActionData($data, 'generate_ai_content');
                    $job = AiProductJob::create(array_merge([
                        'type' => 'generate_ai_content',
                        'scope' => $scope,
                        'status' => 'queued',
                        'total' => count($productIds),
                        'config_json' => $config,
                        'created_by' => auth()->id(),
                    ], SchemaColumns::existing('ai_product_jobs', [
                        'module' => 'ai_product_bulk',
                        'queue_name' => 'ai',
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
                }),
            Action::make('ai_refresh_status')
                ->label('Refresh AI Status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->url('#')
                ->extraAttributes([
                    'data-ai-refresh-button' => '1',
                    'onclick' => 'event.preventDefault(); window.ProductAiStatusPoller?.refreshNow?.();',
                ])
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false),
            Action::make('ai_queue_health_badge')
                ->label('AI Queue: checking...')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->url('#')
                ->extraAttributes([
                    'data-ai-queue-widget' => '1',
                    'onclick' => 'event.preventDefault(); window.ProductAiStatusPoller?.refreshNow?.();',
                ])
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false),
            Action::make('ai_retry_all_failed')
                ->label('Retry all failed AI')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
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
                        'scope' => 'all_filtered',
                        'filtered_count' => count($productIds),
                        'item_count' => $count,
                        'product_ids_sample' => array_slice($productIds, 0, 25),
                    ]);

                    Notification::make()
                        ->title($count > 0 ? "Đã retry {$count} AI item" : 'Không có AI item lỗi để retry')
                        ->status($count > 0 ? 'success' : 'warning')
                        ->send();
                }),
            $this->getExportHeaderAction(),
            $this->getImportHeaderAction(),
            CreateAction::make(),
        ];
    }

    private function resolveAiProductIds(string $scope): array
    {
        if ($scope === 'all_filtered') {
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
            Log::warning('AI product selected scope fell back to current page records', [
                'source' => 'products_header_action',
                'selected_state_count' => count($this->selectedTableRecords ?? []),
                'deselected_state_count' => count($this->deselectedTableRecords ?? []),
                'is_tracking_deselected' => (bool) ($this->isTrackingDeselectedTableRecords ?? false),
            ]);

            return $this->resolveAiProductIds('current_page');
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
            'scope' => $scope,
            'selected_state_count' => count($this->selectedTableRecords ?? []),
            'selected_state_sample' => array_slice((array) ($this->selectedTableRecords ?? []), 0, 25, true),
            'deselected_state_count' => count($this->deselectedTableRecords ?? []),
            'is_tracking_deselected' => (bool) ($this->isTrackingDeselectedTableRecords ?? false),
            'resolved_count' => count($productIds),
            'resolved_ids_sample' => array_slice($productIds, 0, 25),
            'form_scope' => $formData['scope'] ?? null,
            'outputs' => $formData['outputs'] ?? [],
        ]);
    }
}
