<?php

namespace App\Filament\Resources\PolicyPages;

use App\Filament\Resources\PolicyPages\Pages\CreatePolicyPage;
use App\Filament\Resources\PolicyPages\Pages\EditPolicyPage;
use App\Filament\Resources\PolicyPages\Pages\ListPolicyPages;
use App\Filament\Resources\PolicyPages\Schemas\PolicyPageForm;
use App\Filament\Resources\PolicyPages\Tables\PolicyPagesTable;
use App\Models\PolicyPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Traits\HasResourcePermissions;

class PolicyPageResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'policy_page.view',
        'create'  => 'policy_page.create',
        'edit'    => 'policy_page.edit',
        'delete'  => 'policy_page.delete',
    ];

    protected static ?string $model = PolicyPage::class;
        public static function getNavigationGroup(): ?string { return 'Landing & Pages'; }


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PolicyPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PolicyPagesTable::configure($table);
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
            'index' => ListPolicyPages::route('/'),
            'create' => CreatePolicyPage::route('/create'),
            'edit' => EditPolicyPage::route('/{record}/edit'),
        ];
    }
}
