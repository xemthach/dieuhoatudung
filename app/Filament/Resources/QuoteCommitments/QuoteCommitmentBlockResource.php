<?php

namespace App\Filament\Resources\QuoteCommitments;

use App\Filament\Resources\QuoteCommitments\Pages\CreateQuoteCommitmentBlock;
use App\Filament\Resources\QuoteCommitments\Pages\EditQuoteCommitmentBlock;
use App\Filament\Resources\QuoteCommitments\Pages\ListQuoteCommitmentBlocks;
use App\Filament\Resources\QuoteCommitments\Schemas\QuoteCommitmentBlockForm;
use App\Filament\Resources\QuoteCommitments\Tables\QuoteCommitmentBlocksTable;
use App\Models\QuoteCommitmentBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QuoteCommitmentBlockResource extends Resource
{
    protected static ?string $model = QuoteCommitmentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Quote Commitments';

    protected static ?string $modelLabel = 'Commitment Block';

    protected static ?string $pluralModelLabel = 'Commitment Blocks';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Landing & Pages';
    }

    public static function form(Schema $schema): Schema
    {
        return QuoteCommitmentBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuoteCommitmentBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListQuoteCommitmentBlocks::route('/'),
            'create' => CreateQuoteCommitmentBlock::route('/create'),
            'edit'   => EditQuoteCommitmentBlock::route('/{record}/edit'),
        ];
    }
}
