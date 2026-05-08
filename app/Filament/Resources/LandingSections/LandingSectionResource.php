<?php

namespace App\Filament\Resources\LandingSections;

use App\Filament\Resources\LandingSections\Pages\CreateLandingSection;
use App\Filament\Resources\LandingSections\Pages\EditLandingSection;
use App\Filament\Resources\LandingSections\Pages\ListLandingSections;
use App\Filament\Resources\LandingSections\Schemas\LandingSectionForm;
use App\Filament\Resources\LandingSections\Tables\LandingSectionsTable;
use App\Models\LandingSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Traits\HasResourcePermissions;

class LandingSectionResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'landing_section.view',
        'create'  => 'landing_section.create',
        'edit'    => 'landing_section.edit',
        'delete'  => 'landing_section.delete',
    ];

    protected static ?string $model = LandingSection::class;
        public static function getNavigationGroup(): ?string { return 'Landing & Pages'; }


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return LandingSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingSectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingSections::route('/'),
            'create' => CreateLandingSection::route('/create'),
            'edit' => EditLandingSection::route('/{record}/edit'),
        ];
    }
}
