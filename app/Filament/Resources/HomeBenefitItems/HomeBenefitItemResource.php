<?php

namespace App\Filament\Resources\HomeBenefitItems;

use App\Filament\Resources\HomeBenefitItems\Pages\CreateHomeBenefitItem;
use App\Filament\Resources\HomeBenefitItems\Pages\EditHomeBenefitItem;
use App\Filament\Resources\HomeBenefitItems\Pages\ListHomeBenefitItems;
use App\Filament\Resources\HomeBenefitItems\Schemas\HomeBenefitItemForm;
use App\Filament\Resources\HomeBenefitItems\Tables\HomeBenefitItemsTable;
use App\Models\HomeBenefitItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomeBenefitItemResource extends Resource
{
    protected static ?string $model = HomeBenefitItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Home Benefits';

    protected static ?string $modelLabel = 'Benefit Item';

    protected static ?string $pluralModelLabel = 'Benefit Items';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Landing & Pages';
    }

    public static function form(Schema $schema): Schema
    {
        return HomeBenefitItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeBenefitItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListHomeBenefitItems::route('/'),
            'create' => CreateHomeBenefitItem::route('/create'),
            'edit'   => EditHomeBenefitItem::route('/{record}/edit'),
        ];
    }
}
