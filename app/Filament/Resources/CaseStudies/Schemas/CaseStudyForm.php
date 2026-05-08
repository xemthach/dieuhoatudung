<?php

namespace App\Filament\Resources\CaseStudies\Schemas;

use App\Enums\CaseStudyStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CaseStudyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                    \Filament\Schemas\Components\Group::make()->schema([
                        \Filament\Schemas\Components\Tabs::make('Tabs')
                            ->tabs([
                                \Filament\Schemas\Components\Tabs\Tab::make('Basic')
                                    ->schema([
                                        TextInput::make('title')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($operation, $state, $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
                                        TextInput::make('slug')
                                            ->required()
                                            ->unique(ignoreRecord: true),
                                    ])->columns(2),
                                
                                \Filament\Schemas\Components\Tabs\Tab::make('Project Details')
                                    ->schema([
                                        TextInput::make('area_m2')->label('Area (m2)'),
                                        TextInput::make('ceiling_height')->label('Ceiling Height'),
                                        TextInput::make('total_units')
                                            ->numeric()
                                            ->label('Total Units'),
                                        TextInput::make('installation_time')->label('Installation Time'),
                                        \Filament\Forms\Components\DatePicker::make('completion_date')->label('Completion Date'),
                                        Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->label('Primary Product')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('product_ids')
                                            ->options(\App\Models\Product::pluck('name', 'id'))
                                            ->multiple()
                                            ->label('Additional Products')
                                            ->searchable()
                                            ->preload(),
                                    ])->columns(2),

                                \Filament\Schemas\Components\Tabs\Tab::make('Content')
                                    ->schema([
                                        \Filament\Forms\Components\RichEditor::make('problem')->columnSpanFull(),
                                        \Filament\Forms\Components\RichEditor::make('solution')->columnSpanFull(),
                                        \Filament\Forms\Components\RichEditor::make('result')->columnSpanFull(),
                                        Textarea::make('testimonial')->columnSpanFull(),
                                    ]),

                                \Filament\Schemas\Components\Tabs\Tab::make('Media')
                                    ->schema([
                                        FileUpload::make('cover_image')
                                            ->image()
                                            ->directory('case-studies'),
                                            
                                        FileUpload::make('gallery_json')
                                            ->multiple()
                                            ->image()
                                            ->directory('case-studies/gallery')
                                            ->panelLayout('grid'),
                                            
                                    ])->columns(1),

                                \Filament\Schemas\Components\Tabs\Tab::make('SEO')
                                    ->schema([
                                        TextInput::make('seo_title'),
                                        Textarea::make('seo_description'),
                                        TextInput::make('canonical_url')->url(),
                                        TextInput::make('robots')->default('index,follow'),
                                        FileUpload::make('og_image')
                                            ->image()
                                            ->directory('seo'),
                                            
                                    ])->columns(1),
                            ])
                            ->columnSpanFull()
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    \Filament\Schemas\Components\Group::make()->schema([
                        \Filament\Schemas\Components\Section::make('Trạng thái')->schema([
                            Select::make('status')
                                ->options(CaseStudyStatus::class)
                                ->default('draft')
                                ->required(),
                            DateTimePicker::make('published_at'),
                        ]),
                        \Filament\Schemas\Components\Section::make('Khách hàng')->schema([
                            TextInput::make('project_type')->label('Project Type'),
                            TextInput::make('client_name')->label('Client Name'),
                            TextInput::make('location')->label('Location'),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}

