<?php

namespace App\Filament\Resources\PolicyPages\Schemas;

use App\Enums\PolicyType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PolicyPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 4,
                ])->schema([

                    // ─── Main Content (3/4) ────────────────────
                    Group::make()->schema([
                        Section::make('Nội dung chính')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextInput::make('title')
                                    ->label('Tiêu đề')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                TextInput::make('slug')
                                    ->label('Slug (URL)')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                RichEditor::make('content')
                                    ->label('Nội dung')
                                    ->required()
                                    ->columnSpanFull()
                                    ->extraAttributes(['style' => 'min-height:450px'])
                                    ->toolbarButtons([
                                        'bold', 'italic', 'underline', 'strike',
                                        'h2', 'h3',
                                        'bulletList', 'orderedList',
                                        'blockquote', 'link',
                                        'undo', 'redo',
                                    ]),
                            ]),

                        Section::make('SEO')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextInput::make('seo_title')
                                    ->label('SEO Title')
                                    ->maxLength(255)
                                    ->helperText('Để trống sẽ dùng tiêu đề trang')
                                    ->columnSpanFull(),
                                TextInput::make('seo_description')
                                    ->label('SEO Description')
                                    ->maxLength(255)
                                    ->helperText('Để trống sẽ lấy từ nội dung')
                                    ->columnSpanFull(),
                                TextInput::make('robots')
                                    ->label('Robots')
                                    ->default('index,follow')
                                    ->required()
                                    ->maxLength(50)
                                    ->columnSpan(1),
                            ])
                            ->collapsible(),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),

                    // ─── Sidebar (1/4) ─────────────────────────
                    Group::make()->schema([
                        Section::make('Phân loại & Hiển thị')
                            ->schema([
                                Select::make('type')
                                    ->label('Loại chính sách')
                                    ->options(PolicyType::class)
                                    ->required(),
                                CheckboxList::make('display_locations')
                                    ->label('Vị trí hiển thị')
                                    ->options([
                                        'footer'         => 'Footer',
                                        'header_top'     => 'Header phụ',
                                        'lead_form'      => 'Form liên hệ/Báo giá',
                                        'product_detail' => 'Chi tiết sản phẩm',
                                    ])
                                    ->default(['footer']),
                                TextInput::make('sort_order')
                                    ->label('Thứ tự')
                                    ->numeric()
                                    ->default(0),
                                Toggle::make('is_active')
                                    ->label('Kích hoạt')
                                    ->default(true),
                            ]),

                        Section::make('Liên kết')
                            ->schema([
                                Placeholder::make('public_url_display')
                                    ->label('URL public')
                                    ->content(function ($record) {
                                        if (!$record || !$record->slug) {
                                            return 'Lưu trang để xem URL';
                                        }
                                        $url = route('policy-pages.show', $record->slug);
                                        return new \Illuminate\Support\HtmlString(
                                            '<a href="' . $url . '" target="_blank" style="color:#2563eb;text-decoration:underline;word-break:break-all">' . $url . '</a>'
                                        );
                                    }),
                            ])
                            ->hiddenOn('create'),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),

                ]),
            ]);
    }
}
