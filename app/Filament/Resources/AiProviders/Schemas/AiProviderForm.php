<?php

namespace App\Filament\Resources\AiProviders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiProviderForm
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

                    // ── Main Content (3/4) ──
                    Group::make([
                        Section::make('Cấu hình API')->schema([
                            Grid::make([
                                'default' => 1,
                                'md' => 2,
                            ])->schema([
                                Select::make('provider')
                                    ->label('Nhà cung cấp (Provider)')
                                    ->options([
                                        'gemini' => 'Google Gemini',
                                        'openai' => 'OpenAI',
                                        'claude' => 'Anthropic Claude',
                                        'groq' => 'Groq',
                                        'ollama' => 'Ollama (Local)',
                                        'custom' => 'Custom OpenAI-compatible',
                                    ])
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('name')
                                    ->label('Tên gọi nhớ')
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('model')
                                    ->label('Model (VD: gemini-2.5-flash)')
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('api_key')
                                    ->label('API Key')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Khóa API sẽ được mã hóa khi lưu.')
                                    ->columnSpanFull(),
                                TextInput::make('endpoint')
                                    ->label('Endpoint (Dành cho Ollama/Custom)')
                                    ->helperText('Chỉ dùng cho Ollama hoặc endpoint tương thích OpenAI.')
                                    ->url()
                                    ->columnSpanFull(),
                            ]),
                        ]),

                        Section::make('Giới hạn sử dụng')->schema([
                            Grid::make([
                                'default' => 1,
                                'md' => 2,
                            ])->schema([
                                TextInput::make('minute_limit')
                                    ->label('Giới hạn / Phút (RPM)')
                                    ->numeric()
                                    ->columnSpan(1),
                                TextInput::make('daily_limit')
                                    ->label('Giới hạn / Ngày (RPD)')
                                    ->numeric()
                                    ->columnSpan(1),
                            ]),
                        ])->collapsed(),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 3,
                    ]),

                    // ── Sidebar (1/4) ──
                    Group::make([
                        Section::make('Cài đặt luân phiên')->schema([
                            Select::make('priority')
                                ->label('Độ ưu tiên')
                                ->options([
                                    'primary' => 'Chính (Primary)',
                                    'fallback' => 'Dự phòng (Fallback)',
                                ])
                                ->default('primary')
                                ->required(),
                            TextInput::make('weight')
                                ->label('Trọng số (Weight)')
                                ->helperText('Chia tỷ lệ requests giữa các key cùng priority.')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->default(1),
                        ]),

                        Section::make('Trạng thái')->schema([
                            Select::make('status')
                                ->label('Trạng thái')
                                ->options([
                                    'active' => 'Hoạt động',
                                    'inactive' => 'Tắt',
                                    'rate_limited' => 'Rate Limited',
                                    'failed' => 'Lỗi (Failed)',
                                ])
                                ->default('active')
                                ->required(),
                            Toggle::make('is_default')
                                ->label('Là cấu hình mặc định'),
                        ]),

                        Section::make('Tính năng hỗ trợ')->schema([
                            Toggle::make('supports_json_mode')
                                ->label('Hỗ trợ JSON Mode')
                                ->default(false),
                            Toggle::make('supports_streaming')
                                ->label('Hỗ trợ Streaming')
                                ->default(false),
                        ]),
                    ])->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),

                ]),
            ]);
    }
}
