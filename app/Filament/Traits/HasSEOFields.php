<?php

namespace App\Filament\Traits;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

trait HasSEOFields
{
    public static function getSEOFields(): Section
    {
        return Section::make('Tối ưu SEO')
            ->description('Cấu hình các thẻ meta cho SEO')
            ->schema([
                TextInput::make('seo_title')
                    ->label('Tiêu đề SEO (Title)')
                    ->maxLength(255)
                    ->columnSpanFull(),
                    
                Textarea::make('seo_description')
                    ->label('Mô tả SEO (Meta Description)')
                    ->rows(3)
                    ->maxLength(255)
                    ->columnSpanFull(),
                    
                TextInput::make('canonical_url')
                    ->label('Canonical URL')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                    
                Select::make('robots')
                    ->label('Robots Meta')
                    ->options([
                        'index,follow' => 'Index, Follow',
                        'noindex,follow' => 'NoIndex, Follow',
                        'index,nofollow' => 'Index, NoFollow',
                        'noindex,nofollow' => 'NoIndex, NoFollow',
                    ])
                    ->default('index,follow'),
            ])
            ->collapsed()
            ->columns(2);
    }
}
