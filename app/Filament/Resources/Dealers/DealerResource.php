<?php

namespace App\Filament\Resources\Dealers;

use App\Filament\Traits\HasResourcePermissions;
use App\Models\AdministrativeUnit;
use App\Models\Dealer;
use App\Services\Dealer\MapCoordinateParser;
use App\Services\Dealer\SafeMapLinkResolver;
use App\Services\Media\MediaDiskService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DealerResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Dealer::class;

    protected static ?string $navigationLabel = 'Đại lý';

    protected static ?string $modelLabel = 'Đại lý';

    protected static ?string $pluralModelLabel = 'Đại lý';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static array $permissionMap = ['viewAny' => 'dealer.view', 'create' => 'dealer.create', 'edit' => 'dealer.edit', 'delete' => 'dealer.delete'];

    public static function getNavigationGroup(): ?string
    {
        return 'Đại lý';
    }

    private static function location(string $field, string $label, string $scheme, string $kind, ?string $parent = null): Select
    {
        return Select::make($field)->label($label)->searchable()->live()->native(false)
            ->options(function (Get $get) use ($scheme, $kind, $parent) {
                $query = AdministrativeUnit::where('scheme', $scheme)->where('kind', $kind)
                    ->where('dataset_version', config('dealers.'.$scheme.'_version'));
                if ($parent) {
                    $query->where('parent_id', $get($parent) ?: 0);
                }

                return $query->orderBy('name')->limit($kind === 'province' ? 100 : 50)->pluck('full_name', 'id');
            })
            ->getSearchResultsUsing(function (string $search, Get $get) use ($scheme, $kind, $parent) {
                $query = AdministrativeUnit::where('scheme', $scheme)->where('kind', $kind)
                    ->where('dataset_version', config('dealers.'.$scheme.'_version'))->where('full_name', 'like', '%'.$search.'%');
                if ($parent) {
                    $query->where('parent_id', $get($parent) ?: 0);
                }

                return $query->limit(50)->pluck('full_name', 'id');
            })
            ->getOptionLabelUsing(fn ($value) => AdministrativeUnit::find($value)?->full_name);
    }

    public static function form(Schema $schema): Schema
    {
        $mapAction = function (string $field, string $label) {
            return Action::make('parse_'.$field)->label($label)->action(function (Get $get, Set $set) use ($field) {
                abort_unless(auth()->user()?->can('dealer.edit') || auth()->user()?->can('dealer.create'), 403);
                try {
                    $url = (string) $get($field);
                    $parser = app(MapCoordinateParser::class);
                    $point = $parser->parse($url) ?? app(SafeMapLinkResolver::class)->resolve($url);
                    if (! $point) {
                        Notification::make()->warning()->title('Không tìm thấy tọa độ. Hãy nhập latitude/longitude.')->send();

                        return;
                    }
                    $set('latitude', $point['latitude']);
                    $set('longitude', $point['longitude']);
                    Notification::make()->success()->title('Kiểm tra vị trí trên bản đồ trước khi lưu.')->send();
                } catch (\Throwable $exception) {
                    Notification::make()->warning()->title('Không phân giải được liên kết an toàn. Hãy nhập tọa độ thủ công.')->send();
                }
            });
        };

        return $schema->columns(1)->components([
            Tabs::make('dealer_tabs')->tabs([
                Tabs\Tab::make('Thông tin')->schema([
                    TextInput::make('name')->label('Tên đại lý')->required()->maxLength(255),
                    TextInput::make('slug')->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('code')->label('Mã đại lý')->maxLength(255)->unique(ignoreRecord: true),
                    Textarea::make('short_description')->label('Giới thiệu ngắn')->maxLength(1000),
                    Textarea::make('description')->label('Giới thiệu')->rows(6),
                    TextInput::make('phone')->label('Điện thoại')->maxLength(40),
                    TextInput::make('hotline')->label('Hotline')->maxLength(40),
                    TextInput::make('email')->email(),
                    TextInput::make('website')->url(),
                    FileUpload::make('logo')->image()->disk(fn () => app(MediaDiskService::class)->getUploadDisk())->directory('dealers')->maxSize(4096),
                    FileUpload::make('cover_image')->label('Ảnh bìa')->image()->disk(fn () => app(MediaDiskService::class)->getUploadDisk())->directory('dealers')->maxSize(4096),
                    Toggle::make('is_active')->label('Công khai')->default(false),
                    Toggle::make('is_featured')->label('Nổi bật'),
                    Toggle::make('is_verified')->label('Đã xác minh'),
                    TextInput::make('sort_order')->label('Thứ tự')->integer()->default(0),
                ]),
                Tabs\Tab::make('Địa chỉ')->schema([
                    TextInput::make('address_line')->label('Số nhà, đường')->required()->maxLength(255),
                    self::location('province_id', 'Tỉnh/Thành hiện hành', 'current', 'province')->required()
                        ->afterStateUpdated(fn (Set $set) => $set('ward_id', null)),
                    self::location('ward_id', 'Phường/Xã hiện hành', 'current', 'ward', 'province_id')->required(),
                    TextInput::make('legacy_address_line')->label('Số nhà, đường cũ')->maxLength(255),
                    self::location('legacy_province_id', 'Tỉnh/Thành cũ', 'legacy', 'province')
                        ->afterStateUpdated(function (Set $set) {
                            $set('legacy_district_id', null);
                            $set('legacy_ward_id', null);
                        }),
                    self::location('legacy_district_id', 'Quận/Huyện cũ', 'legacy', 'district', 'legacy_province_id')
                        ->afterStateUpdated(fn (Set $set) => $set('legacy_ward_id', null)),
                    self::location('legacy_ward_id', 'Phường/Xã cũ', 'legacy', 'ward', 'legacy_district_id'),
                ]),
                Tabs\Tab::make('Bản đồ')->schema([
                    TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)->live(onBlur: true),
                    TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)->live(onBlur: true),
                    TextInput::make('google_maps_url')->label('Google Maps URL')->url()->maxLength(1000),
                    TextInput::make('openstreetmap_url')->label('OpenStreetMap URL')->url()->maxLength(1000),
                    Actions::make([$mapAction('google_maps_url', 'Lấy tọa độ Google'), $mapAction('openstreetmap_url', 'Lấy tọa độ OSM')]),
                    Placeholder::make('map_preview')->hiddenLabel()->content(fn (Get $get) => new HtmlString(view('components.dealer-map', [
                        'markers' => is_numeric($get('latitude')) && is_numeric($get('longitude'))
                            ? [['name' => 'Vị trí đã chọn', 'latitude' => $get('latitude'), 'longitude' => $get('longitude')]] : [],
                    ])->render())),
                ]),
                Tabs\Tab::make('Thương hiệu / Dòng sản phẩm')->schema([
                    Select::make('brands')->label('Thương hiệu')->relationship('brands', 'name')->multiple()->searchable()->preload(),
                    Select::make('categories')->label('Dòng sản phẩm')->relationship('categories', 'name')->multiple()->searchable()->preload(),
                ]),
                Tabs\Tab::make('Dịch vụ')->schema([
                    Select::make('services')->label('Dịch vụ')->relationship('services', 'name')->multiple()->searchable()->preload(),
                ]),
                Tabs\Tab::make('Reviews')->schema([
                    Toggle::make('review_enabled')->label('Nhận đánh giá mới')->default(true),
                    Toggle::make('reviews_visible')->label('Hiển thị đánh giá và rating')->default(true),
                    Placeholder::make('review_help')->hiddenLabel()->content('Duyệt, ẩn hoặc hiện đánh giá tại mục Đánh giá đại lý.'),
                ]),
                Tabs\Tab::make('SEO')->schema([
                    TextInput::make('seo_title')->label('SEO title')->maxLength(255),
                    Textarea::make('seo_description')->label('SEO description')->maxLength(1000),
                    Toggle::make('is_indexable')->label('Cho phép index trang đại lý')->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Đại lý')->searchable()->sortable(),
            TextColumn::make('province.full_name')->label('Tỉnh/Thành'),
            IconColumn::make('is_active')->label('Công khai')->boolean(),
            IconColumn::make('is_verified')->label('Xác minh')->boolean(),
            TextColumn::make('phone')->label('Điện thoại'),
        ])->filters([SelectFilter::make('province_id')->label('Tỉnh/Thành')->options(fn () => AdministrativeUnit::where('scheme', 'current')->where('kind', 'province')->pluck('full_name', 'id'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDealers::route('/'), 'create' => Pages\CreateDealer::route('/create'), 'edit' => Pages\EditDealer::route('/{record}/edit')];
    }
}
