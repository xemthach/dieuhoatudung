<?php

namespace App\Filament\Resources\AdministrativeUnits;

use App\Filament\Traits\HasResourcePermissions;
use App\Models\AdministrativeUnit;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdministrativeUnitResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = AdministrativeUnit::class;

    protected static ?string $navigationLabel = 'Địa giới';

    protected static array $permissionMap = ['viewAny' => 'location.view', 'create' => 'location.create', 'edit' => 'location.edit', 'delete' => 'location.delete'];

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('full_name')->label('Tên đơn vị')->disabled()->dehydrated(false),
            Textarea::make('introduction')->label('Nội dung giới thiệu'),
            TextInput::make('seo_title')->maxLength(255),
            Textarea::make('seo_description'),
            Toggle::make('is_indexable')->label('Cho phép index khi có đại lý và nội dung'), ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Tên')->searchable(), TextColumn::make('slug')->searchable(), TextColumn::make('scheme')->label('Hệ')])
            ->filters([
                SelectFilter::make('scheme')->label('Hệ')->options(['current' => 'Hiện hành', 'legacy' => 'Ba cấp cũ']),
                SelectFilter::make('kind')->label('Cấp')->options(['province' => 'Tỉnh', 'district' => 'Huyện', 'ward' => 'Xã']),
            ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEntries::route('/'), 'edit' => Pages\EditEntry::route('/{record}/edit')];
    }
}
