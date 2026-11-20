<?php

namespace App\Filament\Resources\Products\Tables;

use App\Services\Product\ProductBulkTechnicalService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

final class ProductBulkTechnicalAction
{
    public static function make(): BulkAction
    {
        $fields = [];
        foreach (ProductBulkTechnicalService::FIELDS as $key => [$label, $rule]) {
            $operation = Select::make("fields.{$key}.operation")->label('Thao tác')
                ->options($key === 'inverter'
                    ? ['keep' => 'Không thay đổi', 'set' => 'Áp dụng']
                    : ['keep' => 'Không thay đổi', 'set' => 'Áp dụng', 'clear' => 'Xóa giá trị'])
                ->default('keep')->live();
            $value = match ($key) {
                'inverter' => Select::make("fields.{$key}.value")->options(['1' => 'Có', '0' => 'Không']),
                'cooling_type' => Select::make("fields.{$key}.value")->options(['1_chieu' => '1 chiều', '2_chieu' => '2 chiều']),
                default => TextInput::make("fields.{$key}.value"),
            };
            $value->label('Giá trị')->visible(fn (Get $get) => $get("fields.{$key}.operation") === 'set');
            $fields[$key] = Section::make($label)->schema([$operation, $value])->columns(2)->compact();
        }

        $tabs = [];
        foreach ([
            'Công suất' => ['technical_capacity_btu','capacity_kw','hp'],
            'Đặc tính' => ['inverter','cooling_type','refrigerant_gas'],
            'Điện' => ['voltage','phase','frequency'],
            'Vận hành' => ['power_consumption','airflow','noise_level','recommended_area'],
            'Kích thước' => ['indoor_dimensions','outdoor_dimensions','weight'],
        ] as $label => $keys) {
            $tabs[] = Tabs\Tab::make($label)->schema(array_values(array_intersect_key($fields, array_flip($keys))));
        }

        return BulkAction::make('bulk_tech_attributes')
            ->label('Cập nhật thông số kỹ thuật')->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn () => auth()->user()?->can('product.edit'))
            ->modalWidth('5xl')->modalSubmitActionLabel('Xác nhận cập nhật')
            ->steps([
                Step::make('Chọn thay đổi')->schema([
                    Placeholder::make('instructions')->hiddenLabel()->content('Bật Áp dụng hoặc Xóa giá trị cho từng trường cần cập nhật.'),
                    Tabs::make('technical_groups')->tabs($tabs),
                    Repeater::make('extras')->label('Thông số mở rộng')
                        ->schema([
                            Select::make('operation')->label('Thao tác')->required()->default('ADD_OR_UPDATE')
                                ->options(['ADD_OR_UPDATE' => 'Thêm / cập nhật', 'REMOVE' => 'Xóa theo tên'])->live(),
                            TextInput::make('key')->label('Tên thông số')->required()->maxLength(120),
                            TextInput::make('value')->label('Giá trị')->maxLength(1000)
                                ->visible(fn (Get $get) => $get('operation') !== 'REMOVE'),
                        ])->columns(3)->defaultItems(0)->addActionLabel('Thêm thông số'),
                    Textarea::make('reason')->label('Lý do thay đổi')->required()->maxLength(1000),
                    Hidden::make('preview_token'),
                ])->afterValidation(function (Get $get, Set $set, $livewire) {
                    try {
                        $plan = app(ProductBulkTechnicalService::class)->preview($livewire->getSelectedTableRecords()->modelKeys(), [
                            'fields' => $get('fields') ?? [], 'extras' => $get('extras') ?? [], 'reason' => $get('reason'),
                        ], auth()->user());
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                    $set('preview_token', $plan['id']);
                    $set('confirmed', false);
                }),
                Step::make('Xem trước và xác nhận')->schema([
                    Placeholder::make('preview')->hiddenLabel()->content(function (Get $get) {
                        $plan = session()->get('technical_bulk.'.$get('preview_token'));
                        return new HtmlString(view('filament.products.bulk-technical-preview', ['plan' => $plan])->render());
                    }),
                    Checkbox::make('confirmed')->label('Tôi xác nhận cập nhật các sản phẩm trong bản xem trước')->accepted()->required(),
                ]),
            ])
            ->action(function (Collection $records, array $data, BulkAction $action) {
                try {
                    $count = app(ProductBulkTechnicalService::class)->apply($data['preview_token'] ?? '',
                        $records->modelKeys(), auth()->user(), (bool) ($data['confirmed'] ?? false));
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }
                Notification::make()->success()->title("Cập nhật thành công {$count}/{$count} sản phẩm")->send();
            })->deselectRecordsAfterCompletion();
    }
}
