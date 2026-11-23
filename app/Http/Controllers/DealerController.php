<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeUnit;
use App\Models\Brand;
use App\Models\Dealer;
use App\Models\DealerService;
use App\Models\ProductCategory;
use App\Services\Dealer\DealerDirectoryService;
use App\Services\Dealer\DealerLocationQuery;
use App\Services\Dealer\DealerReviewService;
use App\Services\Seo\SeoManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DealerController extends Controller
{
    public function index(Request $request, DealerDirectoryService $directory)
    {
        $filters = $directory->filters($request->query());

        return $this->listing($request, $directory, $filters);
    }

    public function region(Request $request, DealerDirectoryService $directory, string $provinceSlug, ?string $districtSlug = null)
    {
        $scheme = $request->routeIs('dealers.legacy-region') ? 'legacy' : 'current';
        $province = AdministrativeUnit::where('scheme', $scheme)->where('kind', 'province')
            ->where('dataset_version', config('dealers.'.$scheme.'_version'))->where('slug', $provinceSlug)->firstOrFail();
        $filters = array_replace($request->query(), ['scheme' => $scheme, 'province' => $province->id]);
        $region = $province;
        if ($districtSlug !== null) {
            $region = $province->children()->where('kind', 'district')->where('slug', $districtSlug)->firstOrFail();
            $filters['district'] = $region->id;
        }

        return $this->listing($request, $directory, $directory->filters($filters), $region);
    }

    private function listing(Request $request, DealerDirectoryService $directory, array $filters, ?AdministrativeUnit $region = null)
    {
        $dealers = $directory->query($filters)->paginate(12)->withQueryString();
        $regions = $directory->regions($filters);
        $brands = Brand::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $services = DealerService::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $canonical = $region ? url($request->path()) : route('dealers.index');
        $filtered = array_diff(array_keys($filters), ['scheme', 'province', 'district']) !== [];
        $indexable = $region ? $region->is_indexable && filled($region->introduction) && $dealers->total() > 0 && ! $filtered
            : $request->query() === [];
        $seo = app(SeoManager::class)->forPage(
            $region?->seo_title ?: ($region ? 'Đại lý tại '.$region->full_name : 'Tìm đại lý'),
            $region?->seo_description ?: 'Tìm đại lý theo khu vực, thương hiệu, dịch vụ và khoảng cách.',
            $canonical, $indexable ? 'index,follow' : 'noindex,follow'
        );

        $cardOptions = app(\App\Services\Dealer\DealerHomepageService::class)->cardOptions();

        return view('dealers.index', compact('dealers', 'regions', 'brands', 'categories', 'services', 'filters', 'region', 'seo', 'cardOptions'));
    }

    public function show(string $dealerSlug, DealerDirectoryService $directory)
    {
        $dealer = $directory->query(['scheme' => 'current'])->where('slug', $dealerSlug)->firstOrFail();
        $reviews = $dealer->publicReviews()->latest('id')->paginate(10)->withQueryString();
        $ratingDistribution = $dealer->reviews_visible
            ? $dealer->publicReviews()->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating')->all() : [];
        $seo = app(SeoManager::class)->fromRecord($dealer, [
            'canonical' => route('dealers.show', $dealer->slug),
            'robots' => $dealer->is_indexable ? 'index,follow' : 'noindex,follow',
        ]);

        $dealerDisplay = app(\App\Services\Dealer\DealerHomepageService::class)->cardOptions();

        return view('dealers.show', compact('dealer', 'reviews', 'seo', 'dealerDisplay', 'ratingDistribution'));
    }

    public function locations(Request $request)
    {
        $data = Validator::make($request->query(), [
            'scheme' => ['required', Rule::in(['current', 'legacy'])],
            'kind' => ['required', Rule::in(['province', 'district', 'ward'])],
            'parent' => 'nullable|integer', 'q' => 'nullable|string|max:100',
        ])->validate();
        $query = AdministrativeUnit::where('scheme', $data['scheme'])
            ->where('dataset_version', config('dealers.'.$data['scheme'].'_version'))->where('kind', $data['kind']);
        if ($data['kind'] !== 'province') {
            $parent = AdministrativeUnit::whereKey($data['parent'] ?? 0)->where('scheme', $data['scheme'])
                ->where('dataset_version', config('dealers.'.$data['scheme'].'_version'))->firstOrFail();
            abort_unless($parent->kind === ($data['kind'] === 'district' || $data['scheme'] === 'current' ? 'province' : 'district'), 422);
            $query->where('parent_id', $parent->id);
        }
        if (! empty($data['q'])) {
            $query->where('full_name', 'like', '%'.$data['q'].'%');
        }

        return response()->json($query->orderBy('name')->limit(500)->get(['id', 'full_name']));
    }

    public function nearby(Request $request, DealerDirectoryService $directory, DealerLocationQuery $location)
    {
        try {
            $data = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
                'radius' => ['nullable', Rule::in(['5', '10', '20', '50', '100', 'all'])],
                'limit' => 'nullable|integer|between:1,12', 'card_profile' => ['nullable', Rule::in(['default', 'homepage'])],
            ])->validate();
            $filters = $directory->filters($request->except(['latitude', 'longitude', 'radius', 'sort', 'page', 'limit', 'card_profile']));
            $radius = ($data['radius'] ?? '20') === 'all' ? null : (int) ($data['radius'] ?? 20);
            $limit = (int) ($data['limit'] ?? 10);
            $dealers = $location->apply($directory->query($filters), (float) $data['latitude'], (float) $data['longitude'], $radius)->limit($limit)->get();
            $cardOptions = ($data['card_profile'] ?? 'default') === 'homepage' ? app(\App\Services\Dealer\DealerHomepageService::class)->cardOptions() : [];

            return response()->json([
                'html' => view('dealers._cards', ['dealers' => $dealers, 'cardOptions' => $cardOptions])->render(),
                'markers' => $dealers->map(fn ($dealer) => $this->marker($dealer))->values(),
                'count' => $dealers->count(), 'radius' => $radius,
            ])->header('Cache-Control', 'no-store, private');
        } catch (ValidationException $exception) {
            // JSON even for non-AJAX clients: do not flash exact coordinates to session.
            return response()->json(['message' => 'Bộ lọc vị trí không hợp lệ.', 'errors' => $exception->errors()], 422)
                ->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            // Query exceptions can contain bindings with the visitor's exact position.
            Log::warning('dealer_nearby_failed', ['exception_type' => get_class($exception)]);

            return response()->json(['message' => 'Chưa thể tìm theo vị trí. Bạn có thể chọn khu vực.'], 503)->header('Cache-Control', 'no-store, private');
        }
    }

    public function submitReview(Request $request, string $dealerSlug, DealerReviewService $service)
    {
        $dealer = Dealer::publiclyListed()->where('slug', $dealerSlug)->firstOrFail();
        $service->submit($dealer, $request->all(), $request->user(), $request->ip());

        if ($request->expectsJson()) {
            return response()->json(['status'=>'pending', 'message'=>'Đánh giá đã được gửi và đang chờ duyệt.'], 201);
        }

        return redirect()->route('dealers.show', $dealer->slug)->with('dealer_review_success', 'Đánh giá đã được gửi và đang chờ duyệt.')->withFragment('reviews');
    }

    public function sitemap()
    {
        $urls = Dealer::publiclyListed()->where('is_indexable', true)->get(['slug', 'updated_at'])
            ->map(fn ($d) => ['loc' => route('dealers.show', $d->slug), 'lastmod' => $d->updated_at?->toAtomString()]);
        $regions = AdministrativeUnit::where('is_indexable', true)->whereNotNull('introduction')
            ->whereIn('kind', ['province', 'district'])->get();
        foreach ($regions as $region) {
            $column = $region->scheme === 'current' ? 'province_id' : 'legacy_'.$region->kind.'_id';
            if (! Dealer::publiclyListed()->where($column, $region->id)->exists()) {
                continue;
            }
            $url = $region->scheme === 'current' ? route('dealers.region', $region->slug)
                : route('dealers.legacy-region', [$region->kind === 'district' ? $region->parent->slug : $region->slug,
                    $region->kind === 'district' ? $region->slug : null]);
            $urls->push(['loc' => $url, 'lastmod' => $region->updated_at?->toAtomString()]);
        }

        return response()->view('seo.sitemap-urlset', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function marker(Dealer $dealer): array
    {
        return ['id' => $dealer->id, 'name' => $dealer->name, 'latitude' => $dealer->latitude, 'longitude' => $dealer->longitude,
            'address' => $dealer->full_address, 'url' => route('dealers.show',$dealer->slug),
            'rating' => $dealer->average_rating, 'distance_km' => $dealer->distance_km];
    }
}
