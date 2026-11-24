<?php

// Real database-queue smoke test; refuses all business databases.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || basename(config('database.connections.sqlite.database')) !== 'comprehensive-worker.sqlite') {
    throw new RuntimeException('Dedicated worker-smoke database required.');
}
touch(config('database.connections.sqlite.database'));
$rowCount = filter_var($argv[1] ?? '501', FILTER_VALIDATE_INT, ['options' => ['min_range' => 501, 'max_range' => 10000]]);
if ($rowCount === false) throw new RuntimeException('Use 501–10000 generated rows.');
$started = microtime(true);
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force'=>true]);
$actor = App\Models\User::factory()->create(['is_active'=>true]);
$actor->assignRole(Spatie\Permission\Models\Role::firstOrCreate(['name'=>'super_admin', 'guard_name'=>'web']));
$category = App\Models\ProductCategory::factory()->create(['is_active'=>true, 'technical_schema_status'=>'active', 'technical_schema_json'=>['fields'=>[['key'=>'btu', 'label'=>'BTU', 'type'=>'number']]]]);
$path = storage_path('framework/testing/worker-business.csv');
$stream = fopen($path, 'wb');
fputcsv($stream, ['sku', 'name', 'category_slug', 'regular_price']);
foreach (range(1, $rowCount) as $index) fputcsv($stream, ['WORKER-'.$index, 'Worker product '.$index, $category->slug, 100000]);
fclose($stream);
$service = app(App\Services\DataTransfer\DataImportService::class);
$job = $service->uploadAndPreview('product', $path, 'worker.csv', 'csv', 'create', 'sku', $actor->id, 'business');
if ($job->failed_rows !== 0) throw new RuntimeException('Preview failed.');
$service->confirmImport($job);
if ($job->fresh()->status !== 'queued') throw new RuntimeException('Not queued.');
Illuminate\Support\Facades\Artisan::call('queue:work', ['--queue'=>'default', '--once'=>true, '--tries'=>1, '--timeout'=>900]);
$job->refresh();
$result = ['queue'=>'default', 'status'=>$job->status, 'products'=>App\Models\Product::count(), 'receipts'=>Illuminate\Support\Facades\DB::table('data_import_receipts')->count(), 'failed_jobs'=>Illuminate\Support\Facades\DB::table('failed_jobs')->count(), 'seconds' => round(microtime(true) - $started, 2), 'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1)];
if ($result['status'] !== 'completed' || $result['products'] !== $rowCount || $result['receipts'] !== $rowCount || $result['failed_jobs'] !== 0) throw new RuntimeException('Worker smoke failed: '.json_encode($result));
Illuminate\Support\Facades\Storage::disk('local')->delete($job->file_path);
unlink($path);
echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
