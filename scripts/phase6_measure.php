<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$product = \App\Models\Product::query()->where('is_active', true)->orderBy('id')->first();
$category = \App\Models\ProductCategory::query()->where('is_active', true)->orderBy('id')->first();
$brand = \App\Models\Brand::query()->where('is_active', true)->orderBy('id')->first();

$routes = [
    'product_listing' => '/san-pham',
    'search' => '/tim-kiem?q='.rawurlencode((string) ($product?->model_code ?: 'dieu hoa')),
    'sitemap_products' => '/sitemap-products.xml',
    'sitemap_static' => '/sitemap-static.xml',
    'merchant_feed' => '/feeds/google-merchant.xml',
];
if ($category) $routes['category'] = '/danh-muc/'.rawurlencode($category->slug);
if ($brand) $routes['brand'] = '/thuong-hieu/'.rawurlencode($brand->slug);
if ($product) $routes['product_detail'] = '/san-pham/'.rawurlencode($product->slug);

$results = [];
$queries = [];
DB::listen(function ($event) use (&$queries): void {
    $fingerprint = preg_replace('/\s+/', ' ', trim((string) $event->sql));
    $queries[] = [
        'fingerprint' => $fingerprint,
        'time_ms' => (float) $event->time,
    ];
});
foreach ($routes as $name => $path) {
    $runs = [];
    for ($run = 1; $run <= 2; $run++) {
        $queries = [];
        $started = hrtime(true);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        $kernel->terminate($request, $response);

        $counts = array_count_values(array_column($queries, 'fingerprint'));
        arsort($counts);
        $runs[] = [
            'run' => $run,
            'status' => $response->getStatusCode(),
            'bytes' => strlen((string) $response->getContent()),
            'elapsed_ms' => round($elapsed, 2),
            'query_count' => count($queries),
            'db_time_ms' => round(array_sum(array_column($queries, 'time_ms')), 2),
            'duplicate_queries' => array_filter($counts, fn (int $count): bool => $count > 1),
        ];
    }
    $results[$name] = ['path' => $path, 'runs' => $runs];
}

$output = [
    'generated_at' => now()->toIso8601String(),
    'environment' => app()->environment(),
    'routes' => $results,
    'note' => 'Local diagnostic timings; not production latency certification.',
];

$target = storage_path('app/private/reports/phase6a_request_metrics.json');
if (! is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
file_put_contents($target, json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
