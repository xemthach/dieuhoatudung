<?php

namespace App\Filament\Resources\AiContentJobs;

use App\Filament\Resources\AiContentJobs\Pages\CreateAiContentJob;
use App\Filament\Resources\AiContentJobs\Pages\EditAiContentJob;
use App\Filament\Resources\AiContentJobs\Pages\ListAiContentJobs;
use App\Filament\Resources\AiContentJobs\Schemas\AiContentJobForm;
use App\Filament\Resources\AiContentJobs\Tables\AiContentJobsTable;
use App\Models\AiContentJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Traits\HasResourcePermissions;

class AiContentJobResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'ai_content_job.view',
        'create'  => 'ai_content_job.create',
        'edit'    => 'ai_content_job.view',
        'delete'  => 'ai_content_job.delete',
    ];

    protected static ?string $model = AiContentJob::class;
        public static function getNavigationGroup(): ?string { return 'SEO & AI'; }


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'topic';

    public static function form(Schema $schema): Schema
    {
        return AiContentJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiContentJobsTable::configure($table);
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
            'index' => ListAiContentJobs::route('/'),
            'create' => CreateAiContentJob::route('/create'),
            'edit' => EditAiContentJob::route('/{record}/edit'),
        ];
    }
}
