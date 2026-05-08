<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListRoles;
use App\Filament\Resources\Users\Pages\CreateRole;
use App\Filament\Resources\Users\Pages\EditRole;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('role.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('role.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('role.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        // Protect built-in roles
        if (in_array($record->name, ['super_admin', 'admin'])) return false;
        return auth()->user()?->can('role.delete') ?? false;
    }

    /**
     * Build permission groups from config/permissions.php.
     * Returns: ['module_prefix' => ['label' => '...', 'icon' => '...', 'options' => [id => label, ...]]]
     */
    protected static function getPermissionGroups(): array
    {
        $registry = config('permissions', []);
        $allPerms = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $groups = [];
        foreach ($registry as $module => $config) {
            $moduleOptions = [];
            foreach ($config['permissions'] as $action => $label) {
                $permName = "{$module}.{$action}";
                // Find the permission ID
                $id = array_search($permName, $allPerms);
                if ($id !== false) {
                    $moduleOptions[$id] = $label;
                }
            }
            if (!empty($moduleOptions)) {
                $groups[$module] = [
                    'label' => $config['label'] ?? $module,
                    'icon' => $config['icon'] ?? 'heroicon-o-key',
                    'options' => $moduleOptions,
                ];
            }
        }

        return $groups;
    }

    /**
     * Get total permission count across all modules.
     */
    protected static function getTotalPermissionCount(): int
    {
        $groups = static::getPermissionGroups();
        return collect($groups)->sum(fn ($g) => count($g['options']));
    }

    public static function form(Schema $schema): Schema
    {
        $groups = static::getPermissionGroups();
        $totalPerms = static::getTotalPermissionCount();

        // ── Build permission module sections (right column) ──
        $permissionSections = [];
        foreach ($groups as $module => $group) {
            $fieldName = "perm_{$module}";
            $count = count($group['options']);

            $permissionSections[] = Section::make($group['label'] . " ({$count})")
                ->icon($group['icon'])
                ->schema([
                    CheckboxList::make($fieldName)
                        ->hiddenLabel()
                        ->options($group['options'])
                        ->bulkToggleable()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                        ->searchable()
                        ->live()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->compact();
        }

        // ── Build permission summary items (left column) ──
        $dangerPerms = ['settings.edit', 'role.edit', 'user.edit', 'user.reset_password'];
        $moduleCount = count($groups);

        // ── Layout: 12-column grid ──
        return $schema
            ->columns(['default' => 1, 'lg' => 12])
            ->components([

                // ═══ LEFT SIDEBAR: 4/12 ═══
                Group::make([

                    // ── Role Info ──
                    Section::make('Thông tin role')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            TextInput::make('name')
                                ->label('Tên role')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->helperText('Dùng snake_case. Vd: super_admin, editor, staff')
                                ->columnSpanFull(),

                            TextInput::make('guard_name')
                                ->label('Guard')
                                ->default('web')
                                ->required()
                                ->columnSpanFull(),
                        ]),

                    // ── Permission Summary ──
                    Section::make('Tóm tắt quyền')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Placeholder::make('total_permissions_info')
                                ->label('Tổng quyền hệ thống')
                                ->content("{$totalPerms} quyền trong {$moduleCount} module"),

                            Placeholder::make('selected_permissions_info')
                                ->label('Đã chọn')
                                ->content(function ($get) use ($groups): string {
                                    $selected = 0;
                                    $modulesWithPerms = 0;
                                    foreach ($groups as $module => $group) {
                                        $fieldName = "perm_{$module}";
                                        $vals = $get($fieldName) ?? [];
                                        if (is_array($vals) && count($vals) > 0) {
                                            $selected += count($vals);
                                            $modulesWithPerms++;
                                        }
                                    }
                                    return "{$selected} quyền trong {$modulesWithPerms} module";
                                })
                                ->live(),

                            Placeholder::make('warning_no_dashboard')
                                ->label('')
                                ->content(function ($get) use ($groups): \Illuminate\Support\HtmlString {
                                    // Check if dashboard.view is selected
                                    $dashboardPerms = $get('perm_dashboard') ?? [];
                                    if (empty($dashboardPerms)) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-sm text-warning-600 dark:text-warning-400 flex items-center gap-1">'
                                            . '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>'
                                            . ' Role chưa có quyền Dashboard</div>'
                                        );
                                    }
                                    return new \Illuminate\Support\HtmlString('');
                                })
                                ->live(),

                            Placeholder::make('warning_sensitive')
                                ->label('')
                                ->content(function ($get): \Illuminate\Support\HtmlString {
                                    $allPerms = Permission::where('guard_name', 'web')
                                        ->pluck('name', 'id')->toArray();
                                    $sensitiveNames = ['settings.edit', 'role.edit', 'user.edit'];
                                    $checkModules = ['perm_settings', 'perm_role', 'perm_user'];
                                    $warnings = [];

                                    foreach ($checkModules as $field) {
                                        $selected = $get($field) ?? [];
                                        if (!is_array($selected)) continue;
                                        foreach ($selected as $pid) {
                                            $name = $allPerms[$pid] ?? '';
                                            if (in_array($name, $sensitiveNames) && !in_array($name, $warnings)) {
                                                $warnings[] = $name;
                                            }
                                        }
                                    }

                                    if (!empty($warnings)) {
                                        $list = implode(', ', $warnings);
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-sm text-danger-600 dark:text-danger-400 flex items-center gap-1">'
                                            . '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-4.5A.75.75 0 0010 5z" clip-rule="evenodd"/></svg>'
                                            . " Quyền nhạy cảm: {$list}</div>"
                                        );
                                    }
                                    return new \Illuminate\Support\HtmlString('');
                                })
                                ->live(),
                        ]),

                    // ── Help Guide ──
                    Section::make('Hướng dẫn quyền')
                        ->icon('heroicon-o-question-mark-circle')
                        ->schema([
                            Placeholder::make('help_guide')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<div class="text-sm space-y-2 text-gray-600 dark:text-gray-400">'
                                    . '<div><strong class="text-gray-800 dark:text-gray-200">view</strong> — Được xem menu / trang</div>'
                                    . '<div><strong class="text-gray-800 dark:text-gray-200">create</strong> — Được tạo mới</div>'
                                    . '<div><strong class="text-gray-800 dark:text-gray-200">edit</strong> — Được chỉnh sửa</div>'
                                    . '<div><strong class="text-gray-800 dark:text-gray-200">delete</strong> — Được xóa</div>'
                                    . '<div><strong class="text-gray-800 dark:text-gray-200">manage / run / test</strong> — Quyền nâng cao</div>'
                                    . '<hr class="my-2 border-gray-200 dark:border-gray-700">'
                                    . '<div class="text-xs text-gray-500"> Mỗi module có nút "Chọn tất cả" / "Bỏ chọn tất cả" riêng. Click tên module để mở/đóng.</div>'
                                    . '</div>'
                                )),
                        ])
                        ->collapsible()
                        ->collapsed(),

                ])->columnSpan(['default' => 'full', 'lg' => 4]),

                // ═══ RIGHT PANEL: 8/12 ═══
                Group::make([

                    Section::make('Phân quyền')
                        ->icon('heroicon-o-key')
                        ->description("Chọn quyền cho role — {$totalPerms} quyền trong {$moduleCount} module. Click tên module để mở/đóng.")
                        ->schema($permissionSections),

                ])->columnSpan(['default' => 'full', 'lg' => 8]),

            ]);
    }

    /**
     * Hydrate permission groups from the role's current permissions (for Edit).
     */
    public static function mutateFormDataBeforeFill(array $data, ?Role $record = null): array
    {
        // This is handled via afterStateHydrated on EditRole page
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Vai trò')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'editor' => 'info',
                        'staff' => 'success',
                        'viewer' => 'gray',
                        default => 'primary',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('permissions_count')
                    ->label('Số quyền')
                    ->counts('permissions')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('users_count')
                    ->label('Số users')
                    ->counts('users')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->label('Sửa'),
                DeleteAction::make()->label('Xóa'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Xóa đã chọn'),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('Chưa có role nào');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
