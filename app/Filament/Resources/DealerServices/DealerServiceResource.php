<?php

namespace App\Filament\Resources\DealerServices;

use App\Filament\Traits\HasResourcePermissions;
use App\Models\DealerService;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealerServiceResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = DealerService::class;

    protected static ?string $navigationLabel = 'Dịch vụ đại lý';

    protected static array $permissionMap = ['viewAny' => 'dealer_service.view', 'create' => 'dealer_service.create', 'edit' => 'dealer_service.edit', 'delete' => 'dealer_service.delete'];

    public static function getNavigationGroup(): ?string
    {
        return 'Đại lý';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Tên')->required()->maxLength(255),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
            Toggle::make('is_active')->label('Đang dùng')->default(true), ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Tên')->searchable(), TextColumn::make('slug')->searchable()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEntries::route('/'), 'edit' => Pages\EditEntry::route('/{record}/edit'), 'create' => Pages\CreateEntry::route('/create')];
    }
}
