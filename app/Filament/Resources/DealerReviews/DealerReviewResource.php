<?php

namespace App\Filament\Resources\DealerReviews;

use App\Filament\Traits\HasResourcePermissions;
use App\Models\DealerReview;
use App\Services\Dealer\DealerReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DealerReviewResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = DealerReview::class;

    protected static ?string $navigationLabel = 'Đánh giá đại lý';

    protected static array $permissionMap = ['viewAny' => 'dealer_review.view', 'edit' => 'dealer_review.moderate'];

    public static function getNavigationGroup(): ?string
    {
        return 'Đại lý';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $actions = [];
        foreach (['approve' => 'Duyệt', 'reject' => 'Từ chối', 'hide' => 'Ẩn', 'show' => 'Hiện'] as $action => $label) {
            $actions[] = Action::make($action)->label($label)->requiresConfirmation()->modalSubmitActionLabel('Confirm')
                ->visible(fn () => auth()->user()?->can('dealer_review.moderate'))
                ->schema([Textarea::make('admin_note')->label('Ghi chú nội bộ')->maxLength(2000)])
                ->action(fn (DealerReview $record, array $data) => app(DealerReviewService::class)->moderate($record, $action, auth()->user(), $data['admin_note'] ?? null));
        }

        return $table->columns([
            TextColumn::make('dealer.name')->label('Đại lý')->searchable(),
            TextColumn::make('customer_name')->label('Khách')->searchable(),
            TextColumn::make('rating')->label('Sao'), TextColumn::make('title')->label('Tiêu đề'),
            TextColumn::make('content')->label('Nội dung')->wrap(),
            TextColumn::make('status')->label('Trạng thái')->badge(),
            IconColumn::make('is_visible')->label('Hiển thị')->boolean(),
            TextColumn::make('created_at')->dateTime()->label('Ngày gửi'),
        ])->filters([SelectFilter::make('status')->options(['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'])])
            ->recordActions($actions)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDealerReviews::route('/')];
    }
}
