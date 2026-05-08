<?php

namespace App\Filament\Resources\ProductQuestions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ProductQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('Câu hỏi')->schema([
                            Select::make('product_id')
                                ->label('Sản phẩm')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('customer_name')
                                    ->label('Tên khách hàng')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('customer_phone')
                                    ->label('Số điện thoại')
                                    ->maxLength(20),
                                TextInput::make('customer_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                            ]),
                            Textarea::make('question')
                                ->label('Nội dung câu hỏi')
                                ->required()
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                        Section::make('Trả lời')->schema([
                            Textarea::make('answer')
                                ->label('Câu trả lời')
                                ->rows(5),
                        ]),
                    ])->columnSpan(2),

                    Group::make()->schema([
                        Section::make('Trạng thái')->schema([
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options([
                                    'pending' => 'Chờ duyệt',
                                    'answered' => 'Đã trả lời',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối',
                                ])
                                ->required()
                                ->default('pending'),
                            Toggle::make('is_public')
                                ->label('Hiển thị công khai')
                                ->default(true),
                        ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }
}
