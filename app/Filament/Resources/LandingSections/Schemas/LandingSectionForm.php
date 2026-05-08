<?php

namespace App\Filament\Resources\LandingSections\Schemas;

use App\Enums\LandingSectionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class LandingSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Thông tin nội dung')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('page_key')
                                    ->label('Page Key')
                                    ->required(),
                                Select::make('section_type')
                                    ->label('Loại Section')
                                    ->options(LandingSectionType::class)
                                    ->required(),
                            ]),
                            TextInput::make('title')->label('Tiêu đề chính'),
                            TextInput::make('subtitle')->label('Tiêu đề phụ'),
                            Textarea::make('content')
                                ->label('Nội dung')
                                ->rows(5)
                                ->columnSpanFull(),
                            TextInput::make('settings_json')->label('Cấu hình JSON'),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Cấu hình hiển thị')->schema([
                            Toggle::make('is_active')
                                ->label('Kích hoạt hiển thị')
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



