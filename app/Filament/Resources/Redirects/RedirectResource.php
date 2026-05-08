<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;
use App\Filament\Traits\HasResourcePermissions;

class RedirectResource extends Resource
{

    use HasResourcePermissions;
    protected static array $permissionMap = [
        'viewAny' => 'redirect.view',
        'create'  => 'redirect.create',
        'edit'    => 'redirect.edit',
        'delete'  => 'redirect.delete',
    ];

    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Redirect 301/302';

    public static function getNavigationGroup(): ?string { return 'SEO'; }

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'source_url';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source_url')
                ->label('URL nguồn (cũ)')
                ->required()
                ->placeholder('/duong-dan-cu hoac https://domain.com/...')
                ->helperText('Nhập đường dẫn hoặc URL đầy đủ. Ví dụ: /san-pham-cu')
                ->unique(ignoreRecord: true)
                ->rules([
                    fn ($get, $record) => function (string $attribute, $value, $fail) use ($get, $record) {
                        $target = $get('target_url');
                        if (! $target) return;

                        $sourcePath = '/' . ltrim(parse_url($value, PHP_URL_PATH) ?? $value, '/');
                        $targetPath = '/' . ltrim(parse_url($target, PHP_URL_PATH) ?? $target, '/');

                        if ($sourcePath === $targetPath) {
                            $fail('URL nguồn và đích không được giống nhau (loop).');
                            return;
                        }

                        // Check reverse loop A→B and B→A
                        $query = Redirect::where('source_url', $targetPath)
                            ->where('target_url', $sourcePath);
                        if ($record) {
                            $query->where('id', '!=', $record->id);
                        }
                        if ($query->exists()) {
                            $fail('Redirect này sẽ tạo vòng lặp (A→B đã tồn tại B→A).');
                        }
                    }
                ]),

            TextInput::make('target_url')
                ->label('URL đích (mới)')
                ->required()
                ->placeholder('/dieu-hoa-tu-dung hoặc https://site.com/trang-moi')
                ->helperText('Có thể là đường dẫn nội bộ hoặc URL tuyệt đối.'),

            Select::make('status_code')
                ->label('Mã redirect')
                ->options([
                    301 => '301 - Permanent (SEO friendly)',
                    302 => '302 - Temporary',
                ])
                ->default(301)
                ->required(),

            Toggle::make('is_active')
                ->label('Kích hoạt')
                ->default(true),

            Textarea::make('note')
                ->label('Ghi chú')
                ->placeholder('Lý do redirect, ngày tháng, liên quan...')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_url')
                    ->label('URL nguồn')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(50),

                TextColumn::make('target_url')
                    ->label('URL đích')
                    ->searchable()
                    ->copyable()
                    ->limit(50),

                TextColumn::make('status_code')
                    ->label('Code')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        301 => 'success',
                        302 => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('hit_count')
                    ->label('Lượt hit')
                    ->sortable()
                    ->numeric(),

                TextColumn::make('last_hit_at')
                    ->label('Hit cuối')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('hit_count', 'desc')
            ->filters([
                Filter::make('active')
                    ->label('Đang active')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),

                Filter::make('inactive')
                    ->label('Đang tắt')
                    ->query(fn (Builder $query) => $query->where('is_active', false)),

                SelectFilter::make('status_code')
                    ->label('Mã HTTP')
                    ->options([
                        301 => '301 Permanent',
                        302 => '302 Temporary',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Tắt các mục đã chọn')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['is_active' => false]));
                            Notification::make()
                                ->title('Đã tắt ' . $records->count() . ' redirect.')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit'   => EditRedirect::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
