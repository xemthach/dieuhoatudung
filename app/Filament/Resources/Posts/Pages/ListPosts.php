<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\PostCategories\PostCategoryResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                Action::make('postCategories')
                    ->label('Danh mục bài viết')
                    ->icon('heroicon-o-folder')
                    ->url(fn (): string => PostCategoryResource::getUrl())
                    ->visible(fn (): bool => PostCategoryResource::canViewAny()),
                Action::make('authors')
                    ->label('Tác giả')
                    ->icon('heroicon-o-user')
                    ->url(fn (): string => AuthorResource::getUrl())
                    ->visible(fn (): bool => AuthorResource::canViewAny()),
                Action::make('tags')
                    ->label('Thẻ nội dung')
                    ->icon('heroicon-o-tag')
                    ->url(fn (): string => TagResource::getUrl())
                    ->visible(fn (): bool => TagResource::canViewAny()),
            ])
                ->label('Cấu hình bài viết')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => PostCategoryResource::canViewAny()
                    || AuthorResource::canViewAny()
                    || TagResource::canViewAny()),
        ];
    }
}
