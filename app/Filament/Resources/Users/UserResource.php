<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('user.view') || auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('user.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('user.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        // Cannot delete self or super_admin
        if ($record->id === auth()->id()) return false;
        if ($record->hasRole('super_admin') && !auth()->user()?->isSuperAdmin()) return false;
        return auth()->user()?->can('user.delete') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 12])
            ->components([
                \Filament\Schemas\Components\Group::make([

                    Section::make('Thông tin tài khoản')
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextInput::make('name')
                                ->label('Họ tên')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            TextInput::make('password')
                                ->label('Mật khẩu')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                                ->dehydrated(fn ($state) => !empty($state))
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->minLength(8)
                                ->helperText('Để trống nếu không muốn đổi mật khẩu.')
                                ->columnSpan(['default' => 'full', 'md' => 1]),

                            TextInput::make('password_confirmation')
                                ->label('Xác nhận mật khẩu')
                                ->password()
                                ->revealable()
                                ->same('password')
                                ->dehydrated(false)
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->columnSpan(['default' => 'full', 'md' => 1]),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),

                    Section::make('Ảnh đại diện')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('avatar_url')
                                ->label('Avatar')
                                ->image()
                                ->imageEditor()
                                ->circleCropper()
                                ->disk(fn () => app(\App\Services\Media\MediaDiskService::class)->getUploadDisk())
                                ->directory('avatars')
                                ->maxSize(fn () => app(\App\Services\Settings\UploadSettingService::class)->avatarMaxSizeKb())
                                ->helperText(fn () => 'Tối đa ' . app(\App\Services\Settings\UploadSettingService::class)->formatMb(app(\App\Services\Settings\UploadSettingService::class)->avatarMaxSizeKb()))
                                ->columnSpanFull(),
                        ])
                        ->collapsed(),

                ])->columnSpan(['default' => 'full', 'lg' => 8]),

                \Filament\Schemas\Components\Group::make([

                    Section::make('Phân quyền')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Select::make('roles')
                                ->label('Vai trò (Roles)')
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->columnSpanFull(),

                            Toggle::make('is_active')
                                ->label('Kích hoạt')
                                ->helperText('Tắt để khóa tài khoản khỏi admin panel.')
                                ->default(true)
                                ->columnSpanFull(),
                        ]),

                    Section::make('Thông tin hệ thống')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Placeholder::make('created_at')
                                ->label('Tạo lúc')
                                ->content(fn (?User $record): string => $record?->created_at?->format('d/m/Y H:i') ?? '—'),

                            Placeholder::make('last_login_at')
                                ->label('Đăng nhập lần cuối')
                                ->content(fn (?User $record): string => $record?->last_login_at?->diffForHumans() ?? 'Chưa đăng nhập'),

                            Placeholder::make('roles_list')
                                ->label('Vai trò hiện tại')
                                ->content(fn (?User $record): string => $record?->getRoleNames()->join(', ') ?? '—'),
                        ])
                        ->collapsed(),

                ])->columnSpan(['default' => 'full', 'lg' => 4]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (User $r) => 'https://ui-avatars.com/api/?name=' . urlencode($r->name) . '&color=fff&background=1a56db&size=64')
                    ->size(36)
                    ->grow(false),

                TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $r): string => $r->email),

                TextColumn::make('roles.name')
                    ->label('Vai trò')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin'       => 'warning',
                        'editor'      => 'info',
                        'staff'       => 'success',
                        'viewer'      => 'gray',
                        default       => 'gray',
                    })
                    ->separator(',')
                    ->grow(false),

                ToggleColumn::make('is_active')
                    ->label('Hoạt động')
                    ->grow(false),

                TextColumn::make('last_login_at')
                    ->label('Đăng nhập')
                    ->since()
                    ->placeholder('Chưa đăng nhập')
                    ->sortable()
                    ->grow(false),

                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->date('d/m/Y')
                    ->sortable()
                    ->grow(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Vai trò')
                    ->relationship('roles', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Trạng thái')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã khóa'),
            ])
            ->recordActions([
                EditAction::make()->label('Sửa'),

                Action::make('reset_password')
                    ->label('Reset mật khẩu')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset mật khẩu?')
                    ->modalDescription('Mật khẩu mới sẽ được gửi qua email.')
                    ->closeModalByClickingAway(false)
                    ->form([
                        TextInput::make('new_password')
                            ->label('Mật khẩu mới')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => Hash::make($data['new_password'])]);
                        Notification::make()
                            ->title('Đã reset mật khẩu')
                            ->body("Mật khẩu của {$record->name} đã được cập nhật.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (User $r) =>
                        $r->id !== auth()->id()
                        && !$r->hasRole('super_admin')
                        && auth()->user()?->can('user.reset_password')
                    ),

                DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (User $r) => $r->id !== auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Xóa đã chọn'),

                    BulkAction::make('assign_role')
                        ->label('Gán vai trò')
                        ->icon('heroicon-o-shield-check')
                        ->form([
                            Select::make('role')
                                ->label('Vai trò')
                                ->options(Role::pluck('name', 'name'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $records->each(fn (User $u) => $u->assignRole($data['role']));
                            Notification::make()
                                ->title('Đã gán vai trò ' . $data['role'])
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('Chưa có user nào');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
        ];
    }
}
