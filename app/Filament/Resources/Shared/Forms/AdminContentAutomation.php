<?php

namespace App\Filament\Resources\Shared\Forms;

use App\Services\AI\AIContentFieldPipeline;
use App\Services\AI\AISeoContentGenerator;
use App\Services\SlugGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AdminContentAutomation
{
    public static function titleField(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required()
            ->live(debounce: 400)
            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                $slugger = app(SlugGeneratorService::class);
                $currentSlug = (string) $get('slug');
                $previousAutoSlug = (string) $get('_slug_auto_source');
                $nextSlug = $slugger->normalize($state);

                if (blank($currentSlug) || $currentSlug === $previousAutoSlug) {
                    $set('slug', $nextSlug);
                    $set('_slug_auto_source', $nextSlug);
                }
            });
    }

    public static function slugField(?string $table = null): TextInput
    {
        $field = TextInput::make('slug')
            ->label('Đường dẫn (Slug)')
            ->required()
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn (?string $state): string => app(SlugGeneratorService::class)->normalize($state));

        return $table ? $field->unique(table: $table, ignoreRecord: true) : $field->unique(ignoreRecord: true);
    }

    public static function slugTracker(string $sourceField): Hidden
    {
        return Hidden::make('_slug_auto_source')
            ->default(fn (Get $get): string => app(SlugGeneratorService::class)->normalize($get($sourceField)))
            ->dehydrated(false);
    }

    public static function aiAction(
        string $module,
        array $inputFields,
        array $fieldMap,
        array $fieldOptions,
    ): Actions {
        return Actions::make([
            Action::make('generate_ai_content')
                ->label('✨ Generate bằng AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->modalHeading('Generate nội dung bằng AI')
                ->modalDescription('Xem các lựa chọn, bấm Generate để đưa bản nháp vào form. Nội dung chưa được lưu cho đến khi admin lưu record.')
                ->modalSubmitActionLabel('Generate & preview trong form')
                ->form([
                    CheckboxList::make('fields')
                        ->label('Generate')
                        ->options($fieldOptions)
                        ->default(array_keys($fieldOptions))
                        ->columns(2)
                        ->required(),
                    Select::make('tone')
                        ->label('Tone')
                        ->options(AISeoContentGenerator::TONES)
                        ->default('hvac_expert')
                        ->required(),
                    Select::make('mode')
                        ->label('Cách áp dụng')
                        ->options([
                            'fill_empty' => 'Chỉ điền field đang trống',
                            'overwrite' => 'Overwrite field đã chọn',
                            'append' => 'Append vào cuối nội dung hiện có',
                        ])
                        ->default('fill_empty')
                        ->required(),
                ])
                ->action(function (array $data, Set $set, Get $get) use ($module, $inputFields, $fieldMap): void {
                    try {
                        $input = [];
                        foreach ($inputFields as $key => $field) {
                            $input[$key] = is_callable($field) ? $field($get) : $get($field);
                        }

                        $selectedFields = array_values((array) ($data['fields'] ?? []));
                        $generated = app(AISeoContentGenerator::class)->generate(
                            $module,
                            $input,
                            $selectedFields,
                            (string) ($data['tone'] ?? 'hvac_expert'),
                        );

                        $pipeline = app(AIContentFieldPipeline::class);
                        $updates = $pipeline->mapGeneratedFields($generated, $fieldMap, $selectedFields);

                        foreach ($updates as $targetField => $value) {
                            $set($targetField, $pipeline->mergeValue(
                                $get($targetField),
                                $value,
                                (string) ($data['mode'] ?? 'fill_empty'),
                            ));
                        }

                        Notification::make()
                            ->title('Đã generate AI vào form preview')
                            ->body('Kiểm tra các field vừa cập nhật, có thể chỉnh sửa hoặc bỏ lưu nếu không dùng.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('AI generate thất bại')
                            ->body($e->getMessage().' | Chi tiết đã ghi vào technical log.')
                            ->danger()
                            ->send();
                    }
                }),
        ]);
    }
}
