<?php

namespace App\Filament\Resources\SiteCampaigns;

use App\Filament\Traits\HasResourcePermissions;
use App\Filament\Resources\SiteCampaigns\Pages\CreateSiteCampaign;
use App\Filament\Resources\SiteCampaigns\Pages\EditSiteCampaign;
use App\Filament\Resources\SiteCampaigns\Pages\ListSiteCampaigns;
use App\Filament\Resources\SiteCampaigns\Schemas\SiteCampaignForm;
use App\Filament\Resources\SiteCampaigns\Tables\SiteCampaignsTable;
use App\Models\SiteCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiteCampaignResource extends Resource
{
    use HasResourcePermissions;

    protected static array $permissionMap = [
        'viewAny' => 'site_campaign.view',
        'create' => 'site_campaign.create',
        'edit' => 'site_campaign.edit',
        'delete' => 'site_campaign.delete',
    ];

    protected static ?string $model = SiteCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Site Campaigns';

    protected static ?string $modelLabel = 'Site Campaign';

    protected static ?string $pluralModelLabel = 'Site Campaigns';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return 'Landing & Pages';
    }

    public static function form(Schema $schema): Schema
    {
        return SiteCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteCampaigns::route('/'),
            'create' => CreateSiteCampaign::route('/create'),
            'edit' => EditSiteCampaign::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
