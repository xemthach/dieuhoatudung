<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Enums\ProductCategoryType;
use App\Filament\Resources\Shared\Forms\AdminContentAutomation;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use App\Services\Media\MediaDiskService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        AdminContentAutomation::slugTracker('name'),
                        Section::make('Thông tin chung')->schema([
                            Select::make('parent_id')
                                ->label('Danh mục cha')
                                ->relationship('parent', 'name'),
                            Select::make('type')
                                ->label('Loại danh mục')
                                ->options(ProductCategoryType::class)
                                ->default('main')
                                ->required(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                AdminContentAutomation::titleField('name', 'Tên danh mục'),
                                AdminContentAutomation::slugField('product_categories'),
                            ]),
                            AdminContentAutomation::aiAction(
                                module: 'product_category',
                                inputFields: [
                                    'title' => 'name',
                                    'category_type' => 'type',
                                ],
                                fieldMap: [
                                    'short_description' => 'intro',
                                    'detailed_content' => 'content',
                                    'meta_description' => 'seo_description',
                                ],
                                fieldOptions: [
                                    'short_description' => 'Short description',
                                    'detailed_content' => 'Detailed content',
                                    'seo_title' => 'SEO title',
                                    'meta_description' => 'Meta description',
                                    'og_title' => 'OG title',
                                    'og_description' => 'OG description',
                                ],
                            ),
                            Textarea::make('intro')
                                ->label('Mô tả ngắn')
                                ->rows(3)
                                ->columnSpanFull(),
                            Textarea::make('content')
                                ->label('Nội dung chi tiết')
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),

                        Section::make('Cấu hình SEO')->schema([
                            TextInput::make('seo_title')->label('Tiêu đề SEO'),
                            TextInput::make('seo_description')->label('Meta description'),
                            TextInput::make('og_title')->label('OG title'),
                            TextInput::make('og_description')->label('OG description'),
                            TextInput::make('canonical_url')->label('Canonical URL')->url(),
                            TextInput::make('robots')
                                ->label('Robots')
                                ->required()
                                ->default('index,follow'),
                        ])->collapsed(),

                        Section::make('Technical schema')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('technical_schema_status')
                                    ->label('Schema status')
                                    ->options([
                                        'missing' => 'missing',
                                        'draft' => 'draft',
                                        'active' => 'active',
                                        'deprecated' => 'deprecated',
                                    ])
                                    ->default('missing')
                                    ->required(),
                                TextInput::make('technical_schema_version')
                                    ->label('Schema version')
                                    ->placeholder('v1'),
                            ]),
                            Textarea::make('technical_schema_notes')
                                ->label('Schema notes')
                                ->rows(3)
                                ->columnSpanFull(),
                            Actions::make([
                                Action::make('generate_schema_preset')
                                    ->label('Tạo schema gợi ý theo loại danh mục')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('warning')
                                    ->form([
                                        Select::make('preset')
                                            ->label('Preset')
                                            ->options(CategoryTechnicalSchemaService::PRESETS)
                                            ->default(fn (Get $get): string => app(CategoryTechnicalSchemaService::class)->inferPreset($get('name'))),
                                    ])
                                    ->action(function (array $data, Set $set, Get $get): void {
                                        $preset = app(CategoryTechnicalSchemaService::class)->presetFor($get('name'), $data['preset'] ?? null);
                                        $set('technical_schema_status', 'draft');
                                        $set('technical_schema_version', $preset['version']);
                                        $set('technical_schema_fields', $preset['fields'] ?? []);

                                        Notification::make()
                                            ->title('Đã tạo schema gợi ý')
                                            ->body('Preset chỉ tạo bộ khung field, không chứa giá trị thông số model.')
                                            ->success()
                                            ->send();
                                    }),
                                Action::make('validate_schema')
                                    ->label('Validate schema')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->action(function (Get $get): void {
                                        $schema = app(CategoryTechnicalSchemaService::class)->normalize(
                                            ['fields' => (array) ($get('technical_schema_fields') ?? [])],
                                            (string) ($get('technical_schema_version') ?: 'v1'),
                                            (string) ($get('technical_schema_status') ?: 'draft'),
                                        );
                                        $errors = app(CategoryTechnicalSchemaService::class)->validate($schema);

                                        Notification::make()
                                            ->title($errors === [] ? 'Schema hợp lệ' : 'Schema chưa hợp lệ')
                                            ->body($errors === [] ? 'Có thể lưu và dùng cho validate/import/render.' : implode("\n", $errors))
                                            ->status($errors === [] ? 'success' : 'danger')
                                            ->send();
                                    }),
                            ]),
                            Repeater::make('technical_schema_fields')
                                ->label('Schema field builder')
                                ->helperText('Đây là khung field kỹ thuật cho category. Giá trị model cụ thể phải lấy từ catalog/product, không nhập tại đây.')
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 3])->schema([
                                        TextInput::make('key')
                                            ->label('Field key')
                                            ->datalist(CategoryTechnicalSchemaService::FIELD_KEYS)
                                            ->required(),
                                        TextInput::make('label')
                                            ->label('Label tiếng Việt')
                                            ->required(),
                                        TextInput::make('sort_order')
                                            ->label('Sort order')
                                            ->numeric()
                                            ->default(10),
                                    ]),
                                    Grid::make(['default' => 1, 'md' => 3])->schema([
                                        Select::make('type')
                                            ->label('Field type')
                                            ->options(array_combine(CategoryTechnicalSchemaService::FIELD_TYPES, CategoryTechnicalSchemaService::FIELD_TYPES))
                                            ->default('text')
                                            ->required(),
                                        Select::make('unit')
                                            ->label('Unit')
                                            ->options(array_combine(CategoryTechnicalSchemaService::UNITS, CategoryTechnicalSchemaService::UNITS))
                                            ->default('none')
                                            ->required(),
                                        TextInput::make('validation_pattern')
                                            ->label('Validation pattern'),
                                    ]),
                                    Grid::make(['default' => 1, 'md' => 4])->schema([
                                        Toggle::make('required')->label('Required')->default(false),
                                        Toggle::make('visible_frontend')->label('Visible frontend')->default(true),
                                        Toggle::make('visible_compare')->label('Visible compare')->default(true),
                                        Toggle::make('use_for_ai')->label('Use for SEO/AI')->default(false),
                                    ]),
                                    TagsInput::make('aliases')
                                        ->label('Aliases catalog mapping')
                                        ->placeholder('ESP, áp suất tĩnh, external static pressure')
                                        ->columnSpanFull(),
                                    Textarea::make('notes')
                                        ->label('Notes')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->itemNumbers()
                                ->collapsed(false)
                                ->default([])
                                ->columnSpanFull(),
                            Placeholder::make('technical_schema_preview')
                                ->label('Advanced JSON preview')
                                ->content(function (Get $get): string {
                                    $schema = app(CategoryTechnicalSchemaService::class)->normalize(
                                        ['fields' => (array) ($get('technical_schema_fields') ?? [])],
                                        (string) ($get('technical_schema_version') ?: 'v1'),
                                        (string) ($get('technical_schema_status') ?: 'draft'),
                                    );

                                    return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
                                })
                                ->columnSpanFull(),
                        ])->collapsed(),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Truyền thông')->schema([
                            FileUpload::make('image')
                                ->label('Ảnh đại diện')
                                ->image()
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('categories'),
                        ]),

                        Section::make('Trạng thái')->schema([
                            Toggle::make('is_active')
                                ->label('Kích hoạt')
                                ->default(true)
                                ->required(),
                            Toggle::make('is_indexable')
                                ->label('Cho phép Index')
                                ->default(true)
                                ->required(),
                            TextInput::make('sort_order')
                                ->label('Thứ tự sắp xếp')
                                ->required()
                                ->numeric()
                                ->default(0),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}
