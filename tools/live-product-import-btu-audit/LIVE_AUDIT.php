<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\DataImportJob;
use App\Models\DataExportJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ProductTransferContract;
use App\Services\DataTransfer\ImportGovernanceService;
use App\Services\Product\ProductFilterService;
use App\Services\Product\ProductMarketingCapacityAuditService;
use App\Support\Spreadsheet\SpreadsheetLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Product Import / BTU audit. This file deliberately has no command
 * handlers, model mutations, cache operations, queue dispatches, or migrations.
 */
function liveAuditBootstrap(string $root): void
{
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

function liveAuditIsWrite(string $sql): bool
{
    return (bool) preg_match('/\\b(insert|update|delete|replace|alter|drop|truncate|create|rename|grant|revoke)\\b/i', $sql);
}

function liveAuditGit(string $root, string $argument): ?string
{
    $value = @shell_exec('git -C '.escapeshellarg($root).' '.$argument);
    $value = is_string($value) ? trim($value) : '';

    return $value === '' ? null : $value;
}

function liveAuditFileSnapshot(string $root): array
{
    $paths = [$root.'/.env', $root.'/VERSION', $root.'/composer.json', $root.'/artisan'];
    foreach (['app', 'config'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
    }
    $snapshot = [];
    foreach ($paths as $path) {
        if (is_file($path)) $snapshot[$path] = [filesize($path), filemtime($path), hash_file('sha256', $path)];
    }
    ksort($snapshot);
    return $snapshot;
}

function liveAuditCodeHashes(string $root): array
{
    $files = [
        'ProductFilterService.php' => 'app/Services/Product/ProductFilterService.php',
        'ProductMarketingCapacityQueryAdapter.php' => 'app/Services/Product/ProductMarketingCapacityQueryAdapter.php',
        'DataImportService.php' => 'app/Services/DataTransfer/DataImportService.php',
        'ProductImportHandler.php' => 'app/Services/DataTransfer/Modules/ProductImportHandler.php',
        'ProductSystemRestoreContract.php' => 'app/Services/DataTransfer/ProductSystemRestoreContract.php',
        'ProductTransferContract.php' => 'app/Services/DataTransfer/ProductTransferContract.php',
        'ImportGovernanceService.php' => 'app/Services/DataTransfer/ImportGovernanceService.php',
    ];
    $result = [];
    foreach ($files as $name => $relative) $result[$name] = is_file($root.'/'.$relative) ? hash_file('sha256', $root.'/'.$relative) : null;
    return $result;
}

function liveAuditFilter(array $parameters): array
{
    $request = Request::create('/san-pham', 'GET', $parameters);
    $query = app(ProductFilterService::class)->apply(Product::query()->where('is_active', true), $request);
    $rows = $query->get(['id', 'sku', 'model_code', 'marketing_capacity_btu']);
    return ['input' => $parameters, 'count' => $rows->count(), 'ids' => $rows->pluck('id')->all(), 'model_codes' => $rows->pluck('model_code')->filter()->values()->all(), 'sql' => $query->toSql(), 'bindings' => $query->getBindings()];
}

function liveAuditWorkbook(?DataImportJob $job): ?array
{
    if (! $job || ! $job->file_path) return null;
    $path = storage_path('app/private/'.$job->file_path);
    $result = ['file_exists' => is_file($path), 'file_name' => $job->file_name, 'file_path' => $job->file_path, 'file_size' => is_file($path) ? filesize($path) : null, 'sha256' => is_file($path) ? hash_file('sha256', $path) : null];
    if (! is_file($path) || $job->file_type !== 'xlsx') return $result;
    $book = SpreadsheetLoader::load($path);
    $result['sheets'] = collect($book->getWorksheetIterator())->map(fn ($sheet) => ['name' => $sheet->getTitle(), 'hidden' => $sheet->getSheetState() !== 'visible'])->all();
    $metadata = $book->getSheetByName('_SYSTEM_EXPORT') ?? $book->getSheetByName(ProductTransferContract::METADATA_SHEET);
    $result['system_export_present'] = $book->getSheetByName('_SYSTEM_EXPORT') !== null;
    $result['product_transfer_present'] = $book->getSheetByName(ProductTransferContract::METADATA_SHEET) !== null;
    $result['system_payload_present'] = $book->getSheetByName('_SYSTEM_PAYLOAD') !== null;
    $result['product_transfer_payload_present'] = $book->getSheetByName(ProductTransferContract::PAYLOAD_SHEET) !== null;
    if ($metadata) {
        $values = [];
        foreach (array_slice($metadata->toArray(null, true, true, false), 1) as $row) if (($row[0] ?? '') !== '') $values[(string) $row[0]] = (string) ($row[1] ?? '');
        $result['metadata'] = array_intersect_key($values, array_flip(['format', 'format_version', 'product_count', 'id_restore_policy', 'columns_sha256', 'content_sha256']));
    }
    $book->disconnectWorksheets();
    try {
        $methodName = ($result['product_transfer_present'] ?? false) ? 'detectProductTransfer' : 'detectProductSystemRestore';
        $method = new ReflectionMethod(DataImportService::class, $methodName);
        $method->setAccessible(true);
        $result['manifest_status'] = $method->invoke(app(DataImportService::class), $job->file_path) !== null ? 'VALID' : 'INVALID';
    } catch (Throwable $e) { $result['manifest_status'] = 'INVALID'; $result['manifest_reason'] = $e->getMessage(); }
    return $result;
}

function liveAuditRenderMarkdown(array $audit): string
{
    $p = $audit['product_population']; $guard = $audit['read_only_proof'];
    return "# PRODUCT IMPORT / BTU LIVE AUDIT\n\n"
        ."- Generated: {$audit['generated_at']}\n- Environment: {$audit['application']['app_env']}\n- Version: {$audit['application']['version']}\n- Git SHA: ".($audit['application']['git_sha'] ?? 'unavailable')."\n- Database: {$audit['database']['name']}\n- Products: {$p['total']} (active {$p['active']}, soft deleted {$p['soft_deleted']})\n\n"
        ."## Read-only proof\n\n- Guard: {$guard['status']}\n- SELECT queries: {$guard['select_query_count']}\n- Write queries: {$guard['write_query_count']}\n- Unexpected file mutation: {$guard['unexpected_file_mutation']}\n\n"
        ."## Automatic diagnosis\n\n```json\n".json_encode($audit['diagnosis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n\n"
        ."The JSON report is the canonical machine-readable evidence.\n";
}

function liveAuditRenderHtml(array $audit, string $jsonFile, string $mdFile): string
{
    $safe = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $summary = $safe(json_encode($audit['diagnosis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return "<!doctype html><html lang=\"vi\"><meta charset=\"utf-8\"><title>Product Import / BTU Live Audit</title><style>body{font:16px system-ui;margin:2rem;max-width:1000px}pre{background:#f5f5f5;padding:1rem;overflow:auto}.ok{color:#087f23}</style><h1>PRODUCT IMPORT / BTU LIVE AUDIT</h1><p class=\"ok\">AUDIT COMPLETE — READ ONLY</p><ul><li>Environment: {$safe($audit['application']['app_env'])}</li><li>Version: {$safe($audit['application']['version'])}</li><li>Git SHA: {$safe($audit['application']['git_sha'] ?? 'unavailable')}</li><li>Database: {$safe($audit['database']['name'])}</li><li>Read-only guard: {$safe($audit['read_only_proof']['status'])}</li></ul><p>JSON: {$safe($jsonFile)}<br>Markdown: {$safe($mdFile)}</p><h2>Automatic diagnosis</h2><pre>{$summary}</pre><h2>Cleanup</h2><p>Download reports, then delete the temporary web audit file if this report was generated through the web fallback.</p></html>";
}

function liveAuditRun(string $root): array
{
    liveAuditBootstrap($root);
    $beforeFiles = liveAuditFileSnapshot($root); $selects = 0; $writes = 0; $guardFailure = null;
    $databaseBefore = null;
    $guard = static function (string $sql) use (&$selects, &$writes, &$guardFailure): void {
        if (liveAuditIsWrite($sql)) { $writes++; $guardFailure = 'Blocked write SQL: '.preg_replace('/\\s+/', ' ', $sql); throw new RuntimeException('READ_ONLY_GUARD_BLOCKED_WRITE'); }
        $selects++;
    };
    DB::beforeExecuting($guard); DB::listen(static function ($query) use (&$selects, &$writes): void { if (liveAuditIsWrite($query->sql)) $writes++; });
    try {
        $databaseBefore = [
            'products' => Product::withTrashed()->count(),
            'import_jobs' => DataImportJob::count(),
            'settings_hash' => hash('sha256', SiteSetting::query()->orderBy('id')->get()->toJson()),
            'queue_jobs' => DB::table('jobs')->count(),
        ];
        $connection = config('database.connections.'.config('database.default'));
        $capacity = Product::query()->whereNotNull('marketing_capacity_btu');
        $buckets = [
            'lt_9000' => (clone $capacity)->where('marketing_capacity_btu', '<', 9000)->count(), '9000' => (clone $capacity)->where('marketing_capacity_btu', 9000)->count(),
            '9000_12000_inclusive' => (clone $capacity)->whereBetween('marketing_capacity_btu', [9000, 12000])->count(), '12000' => (clone $capacity)->where('marketing_capacity_btu', 12000)->count(),
            'lt_12000' => (clone $capacity)->where('marketing_capacity_btu', '<', 12000)->count(), 'lte_12000' => (clone $capacity)->where('marketing_capacity_btu', '<=', 12000)->count(),
        ];
        foreach ([18000, 24000, 28000, 36000, 42000, 48000] as $value) $buckets[(string) $value] = (clone $capacity)->where('marketing_capacity_btu', $value)->count();
        $buckets['gte_50000'] = (clone $capacity)->where('marketing_capacity_btu', '>=', 50000)->count();
        $representatives = [];
        foreach (['9000-12000' => [9000,12000], '18000' => [18000,18000], '24000' => [24000,24000], '48000' => [48000,48000]] as $label => $range) {
            $representatives[$label] = Product::with(['brand:id,slug,name', 'category:id,slug,name'])->withTrashed()->whereBetween('marketing_capacity_btu', $range)->limit(20)->get()->map(fn (Product $p) => ['id'=>$p->id,'sku'=>$p->sku,'model_code'=>$p->model_code,'name'=>$p->name,'brand'=>$p->brand?->slug,'category'=>$p->category?->slug,'marketing_capacity_btu'=>$p->marketing_capacity_btu,'technical_capacity_btu'=>$p->technical_capacity_btu,'capacity_kw'=>$p->capacity_kw,'is_active'=>$p->is_active,'deleted_at'=>$p->deleted_at?->toIso8601String()])->all();
        }
        $job = DataImportJob::find(40); $workbook = liveAuditWorkbook($job);
        $errors = collect($job?->error_report_json ?? [])->map(fn ($error) => ['code' => is_array($error) ? ($error['code'] ?? $error['error_code'] ?? 'UNCLASSIFIED') : 'UNCLASSIFIED', 'message' => is_array($error) ? ($error['message'] ?? $error['error'] ?? '') : (string) $error, 'row' => is_array($error) ? ($error['row'] ?? null) : null])->groupBy('code')->map(fn ($group, $code) => ['error_code'=>$code, 'count'=>$group->count(), 'example_rows'=>$group->pluck('row')->filter()->take(5)->values()->all(), 'example_message'=>$group->first()['message']])->values()->all();
        $filters = ['9000-12000'=>liveAuditFilter(['btu'=>['9000-12000']]), '18000'=>liveAuditFilter(['btu'=>['18000']]), '24000'=>liveAuditFilter(['btu'=>['24000']]), '48000'=>liveAuditFilter(['btu'=>['48000']]), '18000+48000'=>liveAuditFilter(['btu'=>['18000','48000']])];
        $daikin = Brand::where('slug', 'daikin')->value('slug'); if ($daikin) $filters['daikin+18000'] = liveAuditFilter(['btu'=>['18000'],'brand'=>[$daikin]]);
        $category = ProductCategory::whereHas('products', fn ($q) => $q->where('marketing_capacity_btu', 18000))->value('slug'); if ($category) $filters['category+18000'] = liveAuditFilter(['btu'=>['18000'],'category'=>[$category]]);
        $filters['inverter+18000'] = liveAuditFilter(['btu'=>['18000'],'inverter'=>'1']);
        $jobExpected = $job ? DataImportJob::terminalStatusFor((int) $job->total_rows, (int) $job->success_rows, (int) $job->failed_rows) : null;
        $governance = app(ImportGovernanceService::class);
        $marketingPipeline = app(ProductMarketingCapacityAuditService::class)->audit()['stats'];
        $transferJobs = DataImportJob::query()->where('mode', 'product_transfer')->latest('id')->limit(20)->get()->map(fn (DataImportJob $item) => [
            'id'=>$item->id, 'status'=>$item->status, 'rows'=>$item->total_rows, 'created'=>$item->created_rows,
            'updated'=>$item->updated_rows, 'failed'=>$item->failed_rows,
            'contract'=>data_get($item->format_context_json, 'contract'),
            'format_version'=>data_get($item->format_context_json, 'format_version'),
            'catalog_lineage'=>data_get($item->format_context_json, 'preview_summary.catalog_lineage'),
            'policy_snapshot'=>data_get($item->format_context_json, 'governance_snapshot'),
        ])->all();
        $audit = ['generated_at'=>now()->toIso8601String(), 'application'=>['hostname'=>gethostname() ?: null,'app_env'=>app()->environment(),'laravel_env'=>app()->environment(),'version'=>trim((string) @file_get_contents($root.'/VERSION')),'git_sha'=>liveAuditGit($root,'rev-parse HEAD'),'git_tag'=>liveAuditGit($root,'describe --tags --always'),'git_branch'=>liveAuditGit($root,'branch --show-current'),'worktree_state'=>liveAuditGit($root,'status --porcelain') ?: 'clean_or_unavailable'], 'database'=>['driver'=>config('database.default'),'host_masked'=>preg_replace('/(?<=.)./', '*', (string) ($connection['host'] ?? '')),'name'=>(string) ($connection['database'] ?? '')], 'product_population'=>['total'=>Product::count(),'with_trashed'=>Product::withTrashed()->count(),'soft_deleted'=>Product::onlyTrashed()->count(),'active'=>Product::where('is_active',true)->count(),'visible'=>Product::where('is_active',true)->count()], 'brands'=>Brand::withCount('products')->orderBy('slug')->get(['id','slug','name'])->map(fn ($x)=>$x->only(['id','slug','name'])+['products_count'=>$x->products_count])->all(), 'categories'=>ProductCategory::withCount('products')->orderBy('slug')->get(['id','slug','name'])->map(fn ($x)=>$x->only(['id','slug','name'])+['products_count'=>$x->products_count])->all(), 'marketing_capacity'=>['null'=>Product::whereNull('marketing_capacity_btu')->count(),'non_null'=>(clone $capacity)->count(),'distinct_values'=>(clone $capacity)->distinct()->orderBy('marketing_capacity_btu')->pluck('marketing_capacity_btu')->map(fn($x)=>(int)$x)->all(),'buckets'=>$buckets,'range_label'=>'9.000 - 12.000 BTU','range_label_consistent'=>true,'pipeline_audit_stats'=>$marketingPipeline], 'technical_capacity'=>['technical_capacity_btu_present'=>Product::whereNotNull('technical_capacity_btu')->count(),'capacity_kw_present'=>Product::whereNotNull('capacity_kw')->count(),'legacy_btu_present'=>Product::whereNotNull('btu')->count()], 'representative_products'=>$representatives, 'filter_self_tests'=>$filters, 'job_40'=>$job ? ['id'=>$job->id,'module'=>$job->module,'status'=>$job->status,'mode'=>$job->mode,'matching_key'=>$job->matching_key,'total_rows'=>$job->total_rows,'processed_rows'=>$job->processed_rows,'created_rows'=>$job->created_rows,'updated_rows'=>$job->updated_rows,'failed_rows'=>$job->failed_rows,'file_name'=>$job->file_name,'format_context'=>$job->format_context_json,'created_at'=>$job->created_at?->toIso8601String(),'completed_at'=>$job->completed_at?->toIso8601String(),'errors'=>$errors,'expected_state'=>$jobExpected,'state_mismatch'=>$jobExpected !== $job->status,'workbook'=>$workbook] : null, 'transfer_jobs'=>$transferJobs, 'import_routing'=>['handler_class'=>'App\\Services\\DataTransfer\\Modules\\ProductImportHandler','validator_class'=>'ProductImportHandler validation','system_restore_detector'=>'DataImportService::detectProductSystemRestore','product_transfer_detector'=>'DataImportService::detectProductTransfer','provenance_guard_source'=>'ProductImportHandler::technicalInput / catalog provenance guard'], 'governance'=>['technical_appendix_provenance'=>['current_mode'=>$governance->mode('catalog.require_technical_appendix'),'current_source'=>'system_locked ImportGovernanceService','admin_manageable'=>false], 'catalog_detach_state'=>$governance->mode(ImportGovernanceService::DETACH_CATALOG_LINEAGE), 'registered_policies'=>$governance->snapshot()], 'cache'=>['driver'=>config('cache.default'),'filter_cache_enabled'=>false,'facet_cache_enabled'=>false], 'code_hashes'=>liveAuditCodeHashes($root)];
        $audit['diagnosis'] = ['import'=>$job ? (($workbook['system_export_present'] ?? false) ? (($workbook['manifest_status'] ?? null) === 'VALID' ? 'PROVENANCE_EXPECTED_OR_OTHER_HANDLER_GUARD' : 'SYSTEM_METADATA_MISSING_OR_INVALID') : 'EXPORT_FORMAT_BUG_OR_PRESENTATION_IMPORT') : 'UNKNOWN_NO_JOB_40', 'filter'=>['PRODUCT_POPULATION_DIFFERENCE','MARKETING_CAPACITY_DATA_GAP_OR_CODE_PARITY_REQUIRES_LIVE_COMPARISON']];
    } catch (Throwable $e) {
        $audit = ['generated_at'=>now()->toIso8601String(), 'fatal_error'=>$e->getMessage(), 'diagnosis'=>['import'=>'UNKNOWN','filter'=>['UNKNOWN']]];
    }
    $afterFiles = liveAuditFileSnapshot($root); $unexpected = $beforeFiles === $afterFiles ? 0 : 1;
    $databaseAfter = $databaseBefore;
    if ($databaseBefore !== null && $guardFailure === null) {
        $databaseAfter = [
            'products' => Product::withTrashed()->count(),
            'import_jobs' => DataImportJob::count(),
            'settings_hash' => hash('sha256', SiteSetting::query()->orderBy('id')->get()->toJson()),
            'queue_jobs' => DB::table('jobs')->count(),
        ];
    }
    $productMutations = $databaseBefore === null ? null : abs($databaseAfter['products'] - $databaseBefore['products']);
    $importJobMutations = $databaseBefore === null ? null : abs($databaseAfter['import_jobs'] - $databaseBefore['import_jobs']);
    $settingMutations = $databaseBefore === null ? null : (int) ($databaseAfter['settings_hash'] !== $databaseBefore['settings_hash']);
    $queueMutations = $databaseBefore === null ? null : abs($databaseAfter['queue_jobs'] - $databaseBefore['queue_jobs']);
    $audit['read_only_proof'] = [
        'status'=>($writes === 0 && $guardFailure === null && $unexpected === 0 && $productMutations === 0 && $importJobMutations === 0 && $settingMutations === 0 && $queueMutations === 0) ? 'PASS' : 'FAIL',
        'select_query_count'=>$selects,
        'write_query_count'=>$writes,
        'guard_failure'=>$guardFailure,
        'database_before'=>$databaseBefore,
        'database_after'=>$databaseAfter,
        'product_rows_mutated'=>$productMutations,
        'import_jobs_mutated'=>$importJobMutations,
        'settings_mutated'=>$settingMutations,
        'queue_mutated'=>$queueMutations,
        'unexpected_file_mutation'=>$unexpected,
        'production_mutation'=>'NONE',
    ];
    return $audit;
}

function liveAuditWriteReports(string $root, array $audit, string $prefix = 'LIVE_PRODUCT_IMPORT_BTU_AUDIT'): array
{
    $directory = $root.'/storage/logs/audits'; if (! is_dir($directory)) mkdir($directory, 0775, true);
    $stamp = date('Ymd_His'); $base = $directory.'/'.$prefix.'_'.$stamp;
    $json = $base.'.json'; $md = $base.'.md'; $html = $base.'.html';
    file_put_contents($json, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    file_put_contents($md, liveAuditRenderMarkdown($audit));
    file_put_contents($html, liveAuditRenderHtml($audit, basename($json), basename($md)));
    return ['json'=>$json,'markdown'=>$md,'html'=>$html,'sha256'=>['json'=>hash_file('sha256',$json),'markdown'=>hash_file('sha256',$md),'html'=>hash_file('sha256',$html)]];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__, 2);
    $prefix = in_array('--local', $argv ?? [], true)
        ? 'LOCAL_PRODUCT_IMPORT_BTU_AUDIT'
        : 'LIVE_PRODUCT_IMPORT_BTU_AUDIT';
    $audit = liveAuditRun($root);
    $paths = liveAuditWriteReports($root, $audit, $prefix);
    echo json_encode(['status'=>$audit['read_only_proof']['status'],'reports'=>$paths], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
    exit($audit['read_only_proof']['status'] === 'PASS' ? 0 : 1);
}
