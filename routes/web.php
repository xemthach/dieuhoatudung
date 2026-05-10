<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\MiscController;
use App\Http\Controllers\BtuCalculatorController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PriceListController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PageController::class, 'home'])->name('home');

// CSRF Token Refresh (used by frontend csrfFetch() for 419 recovery)
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.token');

// Landing Page (SEO main keyword page)
Route::get('/dieu-hoa-tu-dung', [LandingController::class, 'index'])->name('landing');
Route::post('/dieu-hoa-tu-dung/bao-gia', [LandingController::class, 'storeLead'])->name('landing.lead');

// Products
Route::get('/san-pham', [ProductController::class, 'index'])->name('products.index');
Route::get('/danh-muc/{categorySlug}', [ProductController::class, 'category'])->name('category.show');
Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/san-pham/{slug}/danh-gia', [\App\Http\Controllers\ProductInteractionController::class, 'storeReview'])->name('product.review.store');
Route::post('/san-pham/{slug}/hoi-dap', [\App\Http\Controllers\ProductInteractionController::class, 'storeQuestion'])->name('product.question.store');

// Brands
Route::get('/thuong-hieu', [\App\Http\Controllers\BrandController::class, 'index'])->name('brands.index');
Route::get('/thuong-hieu/{slug}', [\App\Http\Controllers\BrandController::class, 'show'])->name('brands.show');

// Blog
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Policy Pages
Route::get('/chinh-sach', [\App\Http\Controllers\PolicyPageController::class, 'index'])->name('policy-pages.index');
Route::get('/chinh-sach/{slug}', [\App\Http\Controllers\PolicyPageController::class, 'show'])->name('policy-pages.show');

// Static pages
Route::get('/lien-he', [PageController::class, 'contact'])->name('contact');
Route::get('/bao-gia', [QuoteController::class, 'index'])->name('quote.index');
Route::post('/bao-gia', [QuoteController::class, 'store'])->name('quote.store');
Route::post('/bao-gia/nhanh', [QuoteController::class, 'storeQuick'])->name('quote.quick');

// Satellite SEO pages
Route::get('/kien-thuc/{slug}', [MiscController::class, 'knowledge'])->name('knowledge.show');
Route::get('/so-sanh/{slug}', [MiscController::class, 'comparison'])->name('comparison.show');
Route::get('/huong-dan-chon-mua/{slug}', [MiscController::class, 'buyingGuide'])->name('buying-guide.show');
Route::get('/lap-dat-bao-tri/{slug}', [MiscController::class, 'maintenance'])->name('maintenance.show');
Route::get('/du-an', [\App\Http\Controllers\CaseStudyController::class, 'index'])->name('case-studies.index');
Route::get('/du-an/{slug}', [\App\Http\Controllers\CaseStudyController::class, 'show'])->name('case-studies.show');
Route::get('/bang-gia/dieu-hoa-tu-dung', [PriceListController::class, 'index'])->name('price-list');
Route::get('/faq/dieu-hoa-tu-dung', [MiscController::class, 'faq'])->name('faq.dieu-hoa');

/*
|--------------------------------------------------------------------------
| Tools / Calculator Routes
|--------------------------------------------------------------------------
*/

Route::get('/cong-cu/chon-cong-suat-dieu-hoa-tu-dung', [BtuCalculatorController::class, 'index'])
    ->name('btu-calculator.index');
Route::post('/cong-cu/chon-cong-suat-dieu-hoa-tu-dung', [BtuCalculatorController::class, 'calculate'])
    ->name('btu-calculator.calculate');

// Compare
Route::get('/so-sanh-san-pham', [\App\Http\Controllers\CompareController::class, 'index'])->name('compare.index');
Route::post('/so-sanh-san-pham/add', [\App\Http\Controllers\CompareController::class, 'add'])->name('compare.add');
Route::post('/so-sanh-san-pham/remove', [\App\Http\Controllers\CompareController::class, 'remove'])->name('compare.remove');
Route::post('/so-sanh-san-pham/clear', [\App\Http\Controllers\CompareController::class, 'clear'])->name('compare.clear');
Route::get('/so-sanh-san-pham/export/pdf', [\App\Http\Controllers\CompareController::class, 'exportPdf'])->name('compare.export.pdf');
Route::get('/so-sanh-san-pham/export/excel', [\App\Http\Controllers\CompareController::class, 'exportExcel'])->name('compare.export.excel');
Route::get('/so-sanh-san-pham/export/csv', [\App\Http\Controllers\CompareController::class, 'exportCsv'])->name('compare.export.csv');

// Search
Route::get('/api/search/suggest', [\App\Http\Controllers\SearchController::class, 'suggest'])
    ->middleware('throttle:30,1')
    ->name('search.suggest');
Route::get('/tim-kiem', [\App\Http\Controllers\SearchController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('search.index');

/*
|--------------------------------------------------------------------------
| SEO Routes (Phase 9)
|--------------------------------------------------------------------------
*/

// Sitemap Index
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');

// Sub-sitemaps
Route::get('/sitemap-products.xml', [SitemapController::class, 'products'])->name('sitemap.products');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');
Route::get('/sitemap-brands.xml', [SitemapController::class, 'brands'])->name('sitemap.brands');
Route::get('/sitemap-static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');

// Robots.txt
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');

// Google Merchant Feed
Route::get('/feeds/google-merchant.xml', function () {
    $feed = app(\App\Services\Merchant\MerchantFeedService::class)->generateXml();
    return response($feed, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('merchant.feed');

/*
|--------------------------------------------------------------------------
| Admin Data Export Download (auth-protected)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/export/{dataExportJob}/download', [App\Http\Controllers\Admin\DataExportController::class, 'download'])
        ->name('admin.export.download');
});
