<?php

namespace App\Filament\Resources\SiteCampaigns\Schemas;

use App\Models\SiteCampaign;
use App\Services\Media\MediaDiskService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Group::make()->schema([
                        Section::make('General')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('title')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'active' => 'Active',
                                        'paused' => 'Paused',
                                        'archived' => 'Archived',
                                    ])
                                    ->default('draft')
                                    ->required(),
                                Select::make('type')
                                    ->label('Type')
                                    ->options(SiteCampaign::typeOptions())
                                    ->default('modal')
                                    ->required(),
                                Select::make('placement')
                                    ->label('Placement')
                                    ->options(SiteCampaign::placementOptions())
                                    ->default('all')
                                    ->required(),
                                Select::make('device')
                                    ->label('Device')
                                    ->options(SiteCampaign::deviceOptions())
                                    ->default('both')
                                    ->required(),
                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Priority cao hơn sẽ thắng khi nhiều campaign cùng match.'),
                            ]),
                        ]),

                        Section::make('Content')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('content_json.title')
                                    ->label('Headline')
                                    ->maxLength(160),
                                TextInput::make('content_json.subtitle')
                                    ->label('Subtitle')
                                    ->maxLength(240),
                            ]),
                            Textarea::make('content_json.content')
                                ->label('Content')
                                ->rows(4)
                                ->columnSpanFull(),
                            FileUpload::make('content_json.image')
                                ->label('Image')
                                ->disk(fn () => app(MediaDiskService::class)->getUploadDisk())
                                ->directory('campaigns')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                ->maxSize(2048),
                            TextInput::make('content_json.video_url')
                                ->label('Video URL')
                                ->maxLength(500),
                        ]),

                        Section::make('CTA')->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('content_json.button_primary_text')
                                    ->label('Primary button text')
                                    ->maxLength(80),
                                TextInput::make('content_json.button_primary_url')
                                    ->label('Primary button URL')
                                    ->maxLength(500),
                                TextInput::make('content_json.button_secondary_text')
                                    ->label('Secondary button text')
                                    ->maxLength(80),
                                TextInput::make('content_json.button_secondary_url')
                                    ->label('Secondary button URL')
                                    ->maxLength(500),
                                TextInput::make('content_json.phone')
                                    ->label('Phone')
                                    ->maxLength(40),
                                TextInput::make('content_json.zalo_url')
                                    ->label('Zalo URL')
                                    ->maxLength(500),
                            ]),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 2]),

                    Group::make()->schema([
                        Section::make('Schedule')->schema([
                            DateTimePicker::make('start_at')
                                ->label('Start at'),
                            DateTimePicker::make('end_at')
                                ->label('End at'),
                        ]),

                        Section::make('Display rules')->schema([
                            TextInput::make('frequency_json.delay_seconds')
                                ->label('Delay seconds')
                                ->numeric()
                                ->default(5),
                            TextInput::make('frequency_json.scroll_percent')
                                ->label('Scroll percent')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->helperText('Để trống nếu không dùng trigger scroll.'),
                            Select::make('frequency_json.frequency')
                                ->label('Frequency')
                                ->options([
                                    'session' => 'Show once per session',
                                    'day' => 'Show once per day',
                                    'visit' => 'Show every visit',
                                ])
                                ->default('session'),
                            Toggle::make('frequency_json.exit_intent')
                                ->label('Exit intent desktop')
                                ->default(false),
                        ]),

                        Section::make('URL targeting')->schema([
                            Textarea::make('targeting_json.exact_urls')
                                ->label('Exact URLs')
                                ->rows(3)
                                ->helperText('Một URL/path mỗi dòng. VD: /san-pham'),
                            Textarea::make('targeting_json.contains')
                                ->label('URL contains')
                                ->rows(3),
                            Textarea::make('targeting_json.starts_with')
                                ->label('URL starts with')
                                ->rows(3),
                            Textarea::make('targeting_json.regex')
                                ->label('Regex')
                                ->rows(3)
                                ->helperText('Chỉ dùng khi thật cần. VD: #/san-pham/.+#'),
                        ]),

                        Section::make('Design')->schema([
                            TextInput::make('design_json.background_color')
                                ->label('Background color')
                                ->default('#ffffff')
                                ->maxLength(30),
                            TextInput::make('design_json.text_color')
                                ->label('Text color')
                                ->default('#0f172a')
                                ->maxLength(30),
                            Select::make('design_json.position')
                                ->label('Position')
                                ->options([
                                    'center' => 'Center',
                                    'bottom_right' => 'Bottom right',
                                    'bottom_left' => 'Bottom left',
                                ])
                                ->default('center'),
                        ]),
                    ])->columnSpan(['default' => 1, 'md' => 1]),
                ]),
            ]);
    }
}
