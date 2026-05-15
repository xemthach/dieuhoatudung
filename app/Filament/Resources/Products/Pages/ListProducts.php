<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Traits\HasDataTransferActions;
use App\Jobs\AiProductContentBatchJob;
use App\Models\AiProductJob;
use App\Support\SchemaColumns;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use HasDataTransferActions;

    protected static string $resource = ProductResource::class;

    protected string $transferModule = 'product';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ai_generate_filtered')
                ->label('AI Generate theo filter')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->visible(fn () => auth()->user()?->can('product.ai_generate') ?? false)
                ->form(ProductsTable::aiConfigForm([
                    'content', 'seo', 'merchant', 'tags', 'faq', 'internal_links', 'og',
                ], 'missing_only'))
                ->action(function (array $data) {
                    abort_unless(auth()->user()?->can('product.ai_generate'), 403);

                    $productIds = $this->getFilteredTableQuery()->pluck('products.id')->all();

                    if ($productIds === []) {
                        Notification::make()
                            ->title('Filter hiện tại không có sản phẩm')
                            ->warning()
                            ->send();

                        return;
                    }

                    $data['scope'] = 'all_filtered';
                    $config = ProductsTable::normalizeAiActionData($data, 'generate_ai_content');
                    $job = AiProductJob::create(array_merge([
                        'type' => 'generate_ai_content',
                        'scope' => 'all_filtered',
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
                        ->title('Đã tạo AI Product Job theo filter')
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
}
