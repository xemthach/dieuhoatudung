<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Traits\HasDataTransferActions;
use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductJob;
use App\Models\Product;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

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
                ->modalDescription('AI chỉ tạo Content Layer: Nội dung, SEO, Google Merchant, Tags, FAQ và Internal links. Không cập nhật Thông tin cơ bản, giá, model/SKU, brand/category hoặc Thông số kỹ thuật.')
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
                ->form(ProductsTable::aiConfigForm([
                    'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                ], 'missing_only'))
                ->action(function (array $data) {
                    abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                    $scope = $data['scope'] ?? 'selected';
                    $productIds = $this->resolveAiProductIds($scope);

                    if ($productIds === []) {
                        Notification::make()
                            ->title($scope === 'selected' ? 'Chưa chọn sản phẩm' : 'Không có sản phẩm để xử lý')
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

                    Notification::make()
                        ->title('Đã tạo AI Product Job')
                        ->body("Job #{$job->id} sẽ xử lý ".count($productIds).' sản phẩm.')
                        ->success()
                        ->persistent()
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

        $selectedIds = array_values(array_filter(array_map('intval', $this->selectedTableRecords ?? [])));

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

        return Product::query()
            ->whereKey($selectedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
