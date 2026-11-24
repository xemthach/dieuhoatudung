<?php

namespace App\Services\DataTransfer;

use App\Models\DataImportJob;
use App\Models\DataImportReceipt;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Support\EncodingGuard;
use App\Support\Spreadsheet\SpreadsheetLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataImportService
{
    /**
     * Upload and parse a file, creating a preview.
     */
    public function uploadAndPreview(
        string $module,
        string $filePath,
        string $originalName,
        string $fileType,
        string $mode = 'create',
        string $matchingKey = 'id',
        ?int $userId = null,
        string $intent = 'auto',
        array $columnMapping = [],
    ): DataImportJob {
        ModuleRegistry::modelClass($module);
        if (! in_array($mode, ['create', 'update', 'upsert'], true)
            || ! array_key_exists($matchingKey, ModuleRegistry::matchingKeys($module))) {
            throw new \InvalidArgumentException('Invalid import mode or matching key.');
        }
        $fileType = strtolower(trim($fileType));
        app(SpreadsheetFileGuard::class)->assertFile($filePath, $fileType, $originalName);
        // Store the uploaded file privately
        $storagePath = 'data-imports/' . $module;
        $fileName = \Illuminate\Support\Str::uuid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        Storage::disk('local')->makeDirectory($storagePath);
        
        $destination = $storagePath . '/' . $fileName;
        $disk = Storage::disk('local');
        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Không thể mở file import.');
        }
        try {
            if (! $disk->put($destination, $stream)) {
                throw new \RuntimeException('Không thể lưu file import.');
            }
        } finally {
            if (is_resource($stream)) fclose($stream);
        }

        try {
            $workbook = $fileType === 'xlsx' && $module === 'product' ? $this->readWorkbook($disk->path($destination), true) : null;
            $formatContext = $workbook['manifest'] ?? null;
            if (! in_array($intent, ['auto', 'business', 'product_transfer', 'system_restore'], true)) {
                throw new \InvalidArgumentException('Mục đích import không hợp lệ.');
            }
            if ($intent === 'business' && ($module !== 'product' || ! in_array($fileType, ['xlsx', 'csv'], true) || $formatContext !== null)) {
                throw new \InvalidArgumentException('Luồng nghiệp vụ chỉ nhận Product CSV/XLSX không có manifest Transfer/Restore.');
            }
            if (in_array($intent, ['product_transfer', 'system_restore'], true)
                && ($formatContext === null || ($intent === 'product_transfer') !== (($formatContext['contract'] ?? '') === 'PRODUCT_TRANSFER'))) {
                throw new \InvalidArgumentException('File thiếu manifest đúng loại. Hãy xuất lại đúng Product Transfer hoặc System Backup.');
            }
            if ($formatContext !== null) {
                $mode = $formatContext['contract'] === 'PRODUCT_TRANSFER' ? 'product_transfer' : 'system_restore';
                $matchingKey = $mode === 'system_restore' ? 'id' : 'sku';
                $formatContext['governance_snapshot'] = app(ImportGovernanceService::class)->snapshot();
            }
        } catch (\Throwable $error) {
            $disk->delete($destination);
            throw $error;
        }

        $formatContext ??= [];
        $formatContext['file_sha256'] = hash_file('sha256', Storage::disk('local')->path($destination));
        $formatContext['intent'] = $intent;
        $formatContext['column_mapping'] = $columnMapping;
        $formatContext['previewed_by'] = $userId ?? auth()->id();
        $formatContext['previewed_at'] = now()->toIso8601String();
        if ($intent === 'business') {
            $formatContext['contract'] = 'PRODUCT_DATA_EDIT';
            $formatContext['format_version'] = ProductDataImportContract::VERSION;
        }

        $job = DataImportJob::create([
            'module'       => $module,
            'file_name'    => $originalName,
            'file_path'    => $destination,
            'file_type'    => $fileType,
            'mode'         => $mode,
            'matching_key' => $matchingKey,
            'format_context_json' => $formatContext,
            'status'       => 'validating',
            'created_by'   => $userId ?? auth()->id(),
            'started_at'   => now(),
        ]);

        try {
            // Parse the file
            $rows = $workbook['rows'] ?? $this->parseFile($destination, $fileType);
            $job->update(['total_rows'=>$rows->count()]);
            if ($intent === 'business') $rows = app(BusinessProductImport::class)->map($rows, $columnMapping, $matchingKey);

            if ($rows->isEmpty()) {
                $job->update([
                    'status'            => 'empty',
                    'error_report_json' => [['row' => 0, 'errors' => ['File rỗng hoặc không đọc được dữ liệu.']]],
                    'finished_at'       => now(),
                ]);
                return $job;
            }

            // Validate each row
            $handler = $this->getModuleHandler($module, $mode, $intent);
            $validationResult = $this->validateRows($rows, $module, $mode, $matchingKey, $handler);

            if (isset($formatContext['contract'])) {
                $formatContext['preview_summary'] = [
                    'integrity' => 'VALID',
                    'rows' => $rows->count(),
                    'valid' => $validationResult['valid_count'],
                    'blocked' => $validationResult['error_count'],
                    'warnings' => 0,
                    'create' => $validationResult['create_count'],
                    'update' => $validationResult['update_count'],
                ];
                if (($formatContext['contract'] ?? null) === 'PRODUCT_TRANSFER') {
                    $formatContext['preview_summary'] += $this->productTransferMappingSummary($rows);
                }
            }

            if ($validationResult['error_count'] === 0) {
                $formatContext['target_hashes'] = $this->targetHashes($rows, $handler, $matchingKey);
            }

            // Build preview data (first 20 rows)
            $previewRows = $rows->take(20)->map(fn ($row, $i) => [
                    'row_number' => $i + 2,
                'data'       => $row,
                'errors'     => $validationResult['row_errors'][$i] ?? [],
                'action'     => $validationResult['row_actions'][$i] ?? 'create',
            ])->values()->toArray();

            $job->update([
                'status'             => 'previewing',
                'total_rows'         => $rows->count(),
                'success_rows'       => $validationResult['valid_count'],
                'failed_rows'        => $validationResult['error_count'],
                'created_rows'       => $validationResult['create_count'],
                'updated_rows'       => $validationResult['update_count'],
                'preview_data_json'  => $previewRows,
                'error_report_json'  => $validationResult['all_errors'],
                'column_mapping_json'=> array_keys($rows->first()),
                'format_context_json'=> $formatContext,
            ]);
        } catch (\Throwable $e) {
            $report = $e instanceof \Illuminate\Validation\ValidationException
                ? collect($e->errors())->map(fn ($errors, $line) => ['row'=>(int) $line, 'errors'=>$errors])->values()->all()
                : [['row'=>0, 'errors'=>[$e->getMessage()]]];
            $job->update([
                'status'=>'failed', 'failed_rows'=>count($report), 'error_report_json'=>$report, 'finished_at'=>now(),
            ]);
        }

        return $job;
    }

    /**
     * Confirm and execute the import after preview.
     */
    public function confirmImport(DataImportJob $job, bool $queuedExecution = false): DataImportJob
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('data-import:'.$job->id, 960);
        if (! $lock->get()) throw new \Illuminate\Contracts\Cache\LockTimeoutException('Import đang được xử lý.');
        try {
            $job->refresh();
            $allowed = $queuedExecution ? ['queued', 'importing'] : ['previewing'];
            if (! in_array($job->status, $allowed, true)) throw new \RuntimeException('Import job is not in preview state.');
            if (! $queuedExecution && $job->failed_rows > 0) {
                $job->update([
                    'status' => data_get($job->format_context_json, 'intent') === 'auto' && ! in_array($job->mode, ['system_restore', 'product_transfer'], true) ? 'failed' : 'blocked',
                    'success_rows' => 0, 'created_rows' => 0, 'updated_rows' => 0, 'finished_at' => now(),
                ]);
                return $job;
            }
            if (! $queuedExecution && $job->total_rows > (int) config('import-export.queue_threshold', 500) && config('queue.default') !== 'sync') {
                $job->update(['status'=>'queued', 'success_rows' => 0, 'created_rows' => 0, 'updated_rows' => 0, 'started_at'=>now()]);
                try {
                    \App\Jobs\ExecuteDataImport::dispatch($job->id);
                } catch (\Throwable $error) {
                    report($error);
                    $job->update(['status' => 'failed', 'success_rows' => 0, 'finished_at' => now(),
                        'error_report_json' => [['row' => 0, 'errors' => ['Không thể đưa import vào hàng đợi.']]]]);
                }
                return $job->refresh();
            }
            if ($job->module !== 'product') return $this->executeImport($job);

            $productLock = \Illuminate\Support\Facades\Cache::lock('product-import:execute', 960);
            if (! $productLock->get()) {
                throw new \Illuminate\Contracts\Cache\LockTimeoutException('Một file Product khác đang được nhập.');
            }
            try {
                return $this->executeImport($job);
            } finally {
                $productLock->release();
            }
        } finally { $lock->release(); }
    }

    private function executeImport(DataImportJob $job): DataImportJob
    {
        $job->update(['status'=>'importing', 'success_rows' => 0, 'created_rows' => 0, 'updated_rows' => 0, 'started_at'=>now()]);
        try {
            $actorId = data_get($job->format_context_json, 'previewed_by') ?? $job->created_by;
            if ($actorId !== null) {
                $actor = \App\Models\User::find($actorId);
                if (! $actor?->is_active || ! $actor->can($job->module.'.import')
                    || ($job->mode === 'system_restore' && ! $actor->can('system_restore.run'))
                    || ($job->mode === 'product_transfer' && ! $actor->can('product_transfer.run'))) {
                    throw new \RuntimeException('IMPORT_PERMISSION_REVOKED');
                }
            }
            $expected = data_get($job->format_context_json, 'file_sha256');
            $path = Storage::disk('local')->path($job->file_path);
            if (! is_string($expected) || ! is_file($path) || ! hash_equals($expected, hash_file('sha256', $path) ?: '')) {
                throw new \RuntimeException('Không xác minh được file đã xem trước. Vui lòng tải lên lại.');
            }
            $workbook = $job->file_type === 'xlsx' && $job->module === 'product'
                ? $this->readWorkbook($path, true)
                : null;
            $rows = $workbook['rows'] ?? $this->parseFile($job->file_path, $job->file_type);
            if (data_get($job->format_context_json, 'intent') === 'business') {
                $rows = app(BusinessProductImport::class)->map($rows, data_get($job->format_context_json, 'column_mapping', []), $job->matching_key);
            }
            $handler = $this->getModuleHandler($job->module, $job->mode, data_get($job->format_context_json, 'intent'));
            $receipts = DataImportReceipt::where('data_import_job_id', $job->id)->pluck('result', 'row_number')->all();
            $expectedTargets = data_get($job->format_context_json, 'target_hashes', []);
            if ($expectedTargets !== []) {
                $currentTargets = $this->targetHashes($rows, $handler, $job->matching_key);
                foreach ($expectedTargets as $index => $hash) {
                    if (! isset($receipts[$index + 1]) && $hash !== ($currentTargets[$index] ?? null)) throw new \RuntimeException('Dữ liệu đích đã thay đổi sau Preview. Vui lòng xem trước lại.');
                }
            }
            $remaining = $rows->reject(fn ($row, $index) => isset($receipts[$index + 1]));
            $validation = $this->validateRows($remaining, $job->module, $job->mode, $job->matching_key, $handler);
            if ($validation['error_count'] > 0) {
                $job->update(['status'=>data_get($job->format_context_json, 'intent') === 'auto' && ! in_array($job->mode, ['system_restore', 'product_transfer']) ? 'failed' : 'blocked', 'failed_rows'=>$validation['error_count'], 'error_report_json'=>$validation['all_errors'], 'finished_at'=>now()]);
                return $job;
            }

            // A system restore is an all-or-nothing, manifest-bound operation.
            // Re-check the workbook after preview so a replaced file cannot cause
            // a partial restore or silently change FK meaning.
            if ($job->mode === 'system_restore') {
                if (($workbook['manifest']['contract'] ?? null) !== 'SYSTEM_PRODUCT_RESTORE') {
                    throw new \RuntimeException('SYSTEM RESTORE metadata is missing.');
                }
            }
            if ($job->mode === 'product_transfer') {
                if (($workbook['manifest']['contract'] ?? null) !== 'PRODUCT_TRANSFER') throw new \RuntimeException('PRODUCT_TRANSFER metadata is missing.');
                $storedPolicies = data_get($job->format_context_json, 'governance_snapshot', []);
                if ($remaining->isNotEmpty() && (! app(ImportGovernanceService::class)->enabled('product_transfer.enabled') || ! $this->sameGovernanceSnapshot($storedPolicies, app(ImportGovernanceService::class)->snapshot()))) {
                    $job->update(['status'=>'blocked','error_report_json'=>[['row'=>0,'errors'=>['Governance policies changed after Preview. Upload and preview again.']]],'finished_at'=>now()]);
                    return $job;
                }
            }

            $stats = ['success'=>count($receipts), 'failed'=>0, 'created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>[]];
            foreach ($receipts as $result) {
                if ($result === 'created') $stats['created']++;
                if ($result === 'updated') $stats['updated']++;
                if ($result === 'skipped') $stats['skipped']++;
            }
            $chunkSize = max(1, min(500, (int) setting('import_export.import_chunk_size', 100)));
            // Product writes are one logical data-edit operation. A late row
            // failure must never leave a half-imported Product file behind.
            // Transfer and Restore use their own handlers but share this
            // atomic boundary; receipts are committed together with data.
            $atomic = $job->module === 'product' || ! app(ImportGovernanceService::class)->enabled('import.continue_on_row_error');
            $transactionLevel = DB::transactionLevel();
            if ($atomic) DB::beginTransaction();
            try {
                foreach ($rows->chunk($chunkSize) as $chunk) {
                    foreach ($chunk as $index => $row) {
                        if (isset($receipts[$index + 1])) continue;
                        try {
                            $write = function () use ($handler, $row, $job, $index): string {
                                $result = $handler->importRow($row, $job->mode, $job->matching_key);
                                DataImportReceipt::create([
                                    'data_import_job_id'=>$job->id, 'row_number'=>$index + 1,
                                    'payload_hash'=>hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)),
                                    'result'=>$result, 'created_at'=>now(),
                                ]);
                                return $result;
                            };
                            $result = $atomic ? $write() : DB::transaction($write);
                            $stats['success']++;
                            if ($result === 'created') $stats['created']++;
                            if ($result === 'updated') $stats['updated']++;
                            if ($result === 'skipped') $stats['skipped']++;
                        } catch (\Throwable $error) {
                            if ($atomic) throw new \RuntimeException('Dòng '.($index + 2).': '.$error->getMessage(), previous: $error);
                            $stats['failed']++;
                            $stats['errors'][] = ['row'=>$index + 2, 'errors'=>[$error->getMessage()]];
                        }
                    }
                    $job->update(['success_rows'=>$stats['success'], 'failed_rows'=>$stats['failed']]);
                }
                if ($atomic) DB::commit();
            } catch (\Throwable $error) {
                if ($atomic && DB::transactionLevel() > $transactionLevel) DB::rollBack($transactionLevel);
                throw $error;
            }

            $job->update([
                'status'            => DataImportJob::terminalStatusFor($rows->count(), $stats['success'], $stats['failed']),
                'success_rows'      => $stats['success'],
                'failed_rows'       => $stats['failed'],
                'created_rows'      => $stats['created'],
                'updated_rows'      => $stats['updated'],
                'skipped_rows'      => $stats['skipped'],
                'error_report_json' => $stats['errors'] ?: null,
                'finished_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            $completed = DataImportReceipt::where('data_import_job_id', $job->id)->get(['result']);
            $job->update([
                'status'=>$completed->isEmpty() ? 'failed' : 'completed_with_errors',
                'success_rows'=>$completed->count(), 'failed_rows'=>max(0, $job->total_rows - $completed->count()),
                'created_rows'=>$completed->where('result', 'created')->count(),
                'updated_rows'=>$completed->where('result', 'updated')->count(),
                'skipped_rows'=>$completed->where('result', 'skipped')->count(),
                'error_report_json'=>[['row'=>0, 'errors'=>[$e->getMessage()]]], 'finished_at'=>now(),
            ]);
        }

        return $job;
    }

    private function targetHashes(Collection $rows, Contracts\ImportHandlerInterface $handler, string $matchingKey): array
    {
        return $rows->map(function ($row) use ($handler, $matchingKey) {
            $record = $handler->findExisting($row, $matchingKey);
            $references = match (true) {
                $handler instanceof ProductDataImportContract => array_intersect_key($row, array_flip(['brand_id', 'product_category_id'])),
                $handler instanceof Modules\ProductTransferImportHandler => [
                    'brand_id' => Brand::where('slug', $row['brand_slug'] ?? '')->value('id'),
                    'product_category_id' => ProductCategory::where('slug', $row['category_slug'] ?? '')->value('id'),
                    'catalog' => app(ProductTransferCatalogReferences::class)->snapshot($row),
                ],
                default => [],
            };
            return hash('sha256', json_encode([$record?->getRawOriginal(), $references], JSON_THROW_ON_ERROR));
        })->all();
    }

    /**
     * Parse a file into a collection of associative arrays.
     */
    public function parseFile(string $storagePath, string $fileType): Collection
    {
        $fullPath = Storage::disk('local')->path($storagePath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$storagePath}");
        }

        $guard = app(SpreadsheetFileGuard::class);
        $guard->assertFile($fullPath, $fileType);
        $rows = match ($fileType) {
            'xlsx'  => $this->readGenericWorkbook($fullPath),
            'csv'   => $this->parseCsv($fullPath),
            'json'  => $this->parseJson($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
        $guard->assertRows($rows);
        return $rows;
    }

    private function readGenericWorkbook(string $path): Collection
    {
        $workbook = $this->readWorkbook($path);
        if ($workbook['manifest'] !== null) {
            throw new \InvalidArgumentException('File Transfer/Restore chỉ dùng cho Product.');
        }

        return $workbook['rows'];
    }

    /**
     * Parse XLSX file.
     */
    private function readWorkbook(string $path, bool $checkUnmarkedRestore = false): array
    {
        $guard = app(SpreadsheetFileGuard::class);
        $guard->assertFile($path, 'xlsx');
        $spreadsheet = SpreadsheetLoader::load($path);
        try {
            $guard->assertSpreadsheet($spreadsheet);
            $restore = $spreadsheet->getSheetByName(ProductSystemRestoreContract::METADATA_SHEET);
            $transfer = $spreadsheet->getSheetByName(ProductTransferContract::METADATA_SHEET);
            $restorePayload = $spreadsheet->getSheetByName(ProductSystemRestoreContract::PAYLOAD_SHEET);
            $transferPayload = $spreadsheet->getSheetByName(ProductTransferContract::PAYLOAD_SHEET);
            if (($restore && $transfer) || ($restorePayload && ! $restore) || ($transferPayload && ! $transfer)) {
                throw new \InvalidArgumentException('Workbook có manifest xung đột hoặc thiếu sheet metadata tương ứng.');
            }
            $manifest = $restore ?? $transfer;
            $sheet = $spreadsheet->getSheetByName(ProductSystemRestoreContract::DATA_SHEET);
            if ($manifest && ! $sheet) {
                throw new \InvalidArgumentException('Workbook có manifest nhưng thiếu sheet Data.');
            }
            $rows = ($sheet ?? $spreadsheet->getActiveSheet())->toArray(null, false, false, false);
            $payloads = $this->readSystemPayloads($restorePayload ?? $transferPayload);
            $headers = array_map('trim', array_map('strval', $rows[0] ?? []));
            $this->assertHeaders($headers);
            if ($checkUnmarkedRestore && ! $manifest && (in_array($headers, [ProductSystemRestoreContract::fields(), ProductSystemRestoreContract::fields(1), ProductTransferContract::fields(), ProductTransferContract::fields(1)], true))) {
                throw new \InvalidArgumentException('Thiếu manifest _SYSTEM_EXPORT hoặc _PRODUCT_TRANSFER. Hãy xuất lại file đúng loại.');
            }
            $data = $this->workbookRows($rows, $headers, $payloads);
            $guard->assertRows($data);

            $metadata = $manifest ? $this->verifyManifest($manifest, $headers, $data, (bool) $restore) : null;
            if ($restore && ($metadata['format_version'] ?? null) === '1') {
                throw new \InvalidArgumentException('Restore không khả dụng với tệp v1 vì thiếu bằng chứng danh tính FK. Hãy xuất System Backup v2 từ môi trường nguồn.');
            }
            if (($metadata['format_version'] ?? null) === '1') {
                $data = $data->map(fn (array $row) => array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row));
            }
            if ($restore && $metadata !== null && (int) $metadata['format_version'] >= 2) {
                $data = $data->map(fn (array $row): array => array_map(static function ($cell) {
                    $value = json_decode((string) $cell, flags: JSON_THROW_ON_ERROR);
                    if ($value !== null && ! is_scalar($value)) {
                        throw new \InvalidArgumentException('Invalid value in Product System Restore snapshot.');
                    }
                    return $value;
                }, $row));
                if (! hash_equals(ProductSystemRestoreContract::referenceChecksum($data), $metadata['references_sha256'] ?? '')) {
                    throw new \InvalidArgumentException('SYSTEM RESTORE foreign key identities differ from the exported environment.');
                }
            }

            return ['rows' => $data, 'manifest' => $metadata];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function workbookRows(array $rows, array $headers, array $payloads): Collection
    {
        $data = collect();

        for ($i = 1; $i < count($rows); $i++) {
            $row = [];
            $hasValue = false;
            foreach ($headers as $colIndex => $header) {
                if (empty($header)) continue;
                $value = $rows[$i][$colIndex] ?? '';
                $row[$header] = $value;
                if ($value !== '' && $value !== null) $hasValue = true;
            }
            foreach ($row as $field => $value) {
                if (! is_string($value) || ! str_starts_with($value, ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX)) {
                    continue;
                }
                $payloadField = substr($value, strlen(ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX));
                $payload = $payloads[(string) ($row['id'] ?? '')][$payloadField] ?? null;
                if ($payload === null || $field !== $payloadField) {
                    throw new \InvalidArgumentException('Payload XLSX thiếu hoặc sai trường tại dòng '.($i + 1).'.');
                }
                $row[$field] = $payload;
            }
            if ($hasValue) {
                $data->push($row);
            }
        }

        return $data;
    }

    /** @return array<string, array<string, string>> */
    private function readSystemPayloads(?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        if ($sheet === null) {
            return [];
        }

        $payloads = [];
        foreach (array_slice($sheet->toArray(null, false, false, false), 1) as $row) {
            $productId = (string) ($row[0] ?? '');
            $field = (string) ($row[1] ?? '');
            $chunkIndex = filter_var($row[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($productId === '' || $field === '' || $chunkIndex === false || isset($payloads[$productId][$field][$chunkIndex])) {
                throw new \InvalidArgumentException('Dòng payload XLSX thiếu, sai hoặc trùng.');
            }
            $payloads[$productId][$field][$chunkIndex] = (string) ($row[3] ?? '');
        }

        foreach ($payloads as $productId => $fields) {
            foreach ($fields as $field => $chunks) {
                ksort($chunks);
                if (! array_is_list($chunks)) throw new \InvalidArgumentException('Payload XLSX thiếu đoạn dữ liệu.');
                $payloads[$productId][$field] = implode('', $chunks);
            }
        }

        return $payloads;
    }

    /**
     * Parse CSV file with encoding detection.
     */
    protected function parseCsv(string $path): Collection
    {
        $content = file_get_contents($path);

        // Detect and convert encoding to UTF-8
        $content = $this->ensureUtf8($content);

        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Do not split CSV on newlines. A quoted value may legitimately contain
        // line breaks (long descriptions, installation notes, etc.) and
        // explode() silently shifts every following column when that happens.
        // fgetcsv() implements RFC 4180 quoting and keeps those values intact.
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Không thể khởi tạo bộ đọc CSV.');
        }
        fwrite($stream, $content);
        rewind($stream);

        $firstLine = fgets($stream);
        if ($firstLine === false) {
            fclose($stream);
            return collect();
        }
        // Vietnamese spreadsheet exports commonly use either comma or
        // semicolon. Detect the delimiter from the header, then let fgetcsv
        // handle all subsequent quoted/newline-containing records.
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter, escape: '');
        if ($headers === false) {
            fclose($stream);
            return collect();
        }
        $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
        if ($headers === ['']) {
            fclose($stream);
            return collect();
        }
        $this->assertHeaders($headers);

        $data = collect();
        while (($values = fgetcsv($stream, 0, $delimiter, escape: '')) !== false) {
            // Ignore completely blank records while retaining whitespace and
            // line breaks inside actual fields.
            $hasValue = false;
            foreach ($values as $value) {
                if ($value !== '' && $value !== null) {
                    $hasValue = true;
                    break;
                }
            }
            if (! $hasValue) {
                continue;
            }

            if (count($values) !== count($headers)) {
                throw new \InvalidArgumentException('Số cột CSV không khớp tiêu đề tại bản ghi '.($data->count() + 2).'.');
            }
            $row = [];
            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $value = $values[$colIndex] ?? '';
                // Do not trim payloads: trimming would destroy intentional
                // indentation/newlines in long text fields. Headers are still
                // normalised above.
                $row[$header] = is_string($value) ? $value : (string) $value;
            }
            if ($row !== []) {
                $data->push($row);
            }
        }

        fclose($stream);
        return $data;
    }

    private function assertHeaders(array $headers): void
    {
        if ($headers === ['']) return;
        if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            throw new \InvalidArgumentException('Tên cột không được để trống hoặc trùng lặp.');
        }
    }

    /**
     * Parse JSON file.
     */
    protected function parseJson(string $path): Collection
    {
        $content = file_get_contents($path);
        $content = $this->ensureUtf8($content);

        $decoded = json_decode($content, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || collect($decoded)->contains(fn ($row) => ! $row instanceof \stdClass)) {
            throw new \InvalidArgumentException('File JSON phải chứa một danh sách đối tượng.');
        }

        return collect($decoded)->map(fn (\stdClass $row) => array_map(
            fn ($value) => is_array($value) || is_object($value) ? EncodingGuard::jsonEncode($value) : $value,
            (array) $row,
        ));
    }

    /**
     * Ensure content is UTF-8 encoded.
     */
    protected function ensureUtf8(string $content): string
    {
        return EncodingGuard::ensureUtf8(
            $content,
            autoFixMojibake: true,
            rejectBroken: true,
            context: 'import file'
        );
    }

    /**
     * Validate all rows and return validation results.
     */
    protected function validateRows(Collection $rows, string $module, string $mode, string $matchingKey, Contracts\ImportHandlerInterface $handler): array
    {
        $requiredFields = ModuleRegistry::requiredFields($module);

        $validCount  = 0;
        $errorCount  = 0;
        $createCount = 0;
        $updateCount = 0;
        $rowErrors   = [];
        $rowActions  = [];
        $allErrors   = [];

        $seen = ['id' => [], 'sku' => [], 'slug' => []];
        foreach ($rows as $index => $row) {
            $errors = [];

            // Values imported from a spreadsheet must never be interpreted as
            // formulas by a later export/open in Excel. Reject formula-like
            // cells at validation time; negative numeric values remain valid.
            foreach ($row as $field => $value) {
                if (! in_array($mode, ['system_restore', 'product_transfer'], true)
                    && ! ($field === 'phone' && preg_match('/^\+84\d{9,10}$/D', (string) $value))
                    && SpreadsheetFileGuard::looksLikeFormula($value)) {
                    $errors[] = "{$field} chứa công thức spreadsheet không được phép.";
                }
            }

            // Check required fields
            foreach ($requiredFields as $field) {
                if (empty($row[$field] ?? null)) {
                    $errors[] = "Thiếu trường bắt buộc: {$field}";
                }
            }

            // Module-specific validation
            $moduleErrors = $handler->validateRow($row, $mode, $matchingKey);
            $errors = array_merge($errors, $moduleErrors);

            if (in_array($mode, ['system_restore', 'product_transfer'], true)) {
                foreach (array_keys($seen) as $unique) {
                    $value = mb_strtolower((string) ($row[$unique] ?? ''));
                    if ($value === '') continue;
                    if (isset($seen[$unique][$value])) {
                        $errors[] = "{$mode} contains duplicate {$unique}: {$value}";
                    }
                    $seen[$unique][$value] = true;
                }
            }

            // Determine action (create or update)
            $action = 'create';
            if ($mode === 'system_restore') {
                $exists = $handler->findExisting($row, 'id');
                $action = $exists ? 'update' : 'create';
                $exists ? $updateCount++ : $createCount++;
            } elseif ($mode === 'product_transfer') {
                $exists = $errors === [] ? $handler->findExisting($row, 'sku') : null;
                if ($exists) {
                    $action = 'update';
                    $updateCount++;
                } else {
                    $createCount++;
                }
            } elseif ($mode === 'update' || $mode === 'upsert') {
                $exists = $handler->findExisting($row, $matchingKey);
                if ($exists) {
                    $action = 'update';
                    $updateCount++;
                } elseif ($mode === 'update') {
                    if (!empty($row[$matchingKey] ?? null)) {
                        $errors[] = "Không tìm thấy bản ghi để update (key: {$matchingKey} = " . ($row[$matchingKey] ?? '') . ")";
                    }
                    $action = 'skip';
                } else {
                    $createCount++;
                }
            } else {
                $createCount++;
            }

            if (!empty($errors)) {
                $errorCount++;
                $rowErrors[$index] = $errors;
                $allErrors[] = [
                    'row'    => $index + 2,
                    'errors' => $errors,
                ];
            } else {
                $validCount++;
            }

            $rowActions[$index] = $action;
        }

        if ($mode === 'product_transfer'
            && $createCount > 0
            && $updateCount > 0
            && ! app(ImportGovernanceService::class)->enabled('product_transfer.allow_upsert')) {
            $message = 'PRODUCT_TRANSFER mixed create/update package is disabled by governance.';
            foreach ($rows as $index => $_row) {
                if (! in_array($message, $rowErrors[$index] ?? [], true)) {
                    $rowErrors[$index][] = $message;
                }
            }
            $allErrors = collect($rowErrors)->map(fn (array $errors, int $index) => [
                'row' => $index + 2,
                'errors' => $errors,
            ])->values()->all();
            $errorCount = count($rowErrors);
            $validCount = $rows->count() - $errorCount;
        }

        return [
            'valid_count'  => $validCount,
            'error_count'  => $errorCount,
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'row_errors'   => $rowErrors,
            'row_actions'  => $rowActions,
            'all_errors'   => $allErrors,
        ];
    }

    /**
     * Get the module-specific import handler.
     */
    protected function getModuleHandler(string $module, ?string $mode = null, ?string $intent = null): Contracts\ImportHandlerInterface
    {
        if ($module === 'product' && $mode === 'system_restore') {
            return app(Modules\ProductRestoreImportHandler::class);
        }
        if ($module === 'product' && $mode === 'product_transfer') {
            return app(Modules\ProductTransferImportHandler::class);
        }
        if ($module === 'product' && $intent === 'business') {
            return app(ProductDataImportContract::class);
        }

        return match ($module) {
            'product'         => app(Modules\ProductImportHandler::class),
            'lead'            => app(Modules\LeadImportHandler::class),
            'quote_request'   => app(Modules\QuoteRequestImportHandler::class),
            'btu_calculation' => app(Modules\BtuCalculationImportHandler::class),
            default           => throw new \InvalidArgumentException("No import handler for module: {$module}"),
        };
    }

    /** @return array<string, mixed> */
    private function productTransferMappingSummary(Collection $rows): array
    {
        $summary = [
            'brand_mapping' => ['exact' => 0, 'remapped' => 0, 'missing' => 0, 'ambiguous' => 0],
            'category_mapping' => ['exact' => 0, 'remapped' => 0, 'missing' => 0, 'ambiguous' => 0],
            'catalog_lineage' => ['preserve' => 0, 'remapped' => 0, 'blocked' => 0],
        ];

        foreach ($rows as $row) {
            foreach ([
                ['brand_mapping', Brand::class, 'brand_slug', 'source_brand_id'],
                ['category_mapping', ProductCategory::class, 'category_slug', 'source_category_id'],
            ] as [$key, $model, $slugField, $sourceIdField]) {
                if (blank($row[$slugField] ?? null) && blank($row[$sourceIdField] ?? null)) {
                    $summary[$key]['exact']++;
                    continue;
                }
                $matches = $model::query()->where('slug', (string) ($row[$slugField] ?? ''))->get(['id']);
                if ($matches->isEmpty()) $summary[$key]['missing']++;
                elseif ($matches->count() > 1) $summary[$key]['ambiguous']++;
                elseif ((int) $matches->first()->id === (int) ($row[$sourceIdField] ?? 0)) $summary[$key]['exact']++;
                else $summary[$key]['remapped']++;
            }

            try {
                $catalog = app(ProductTransferCatalogReferences::class)->resolve($row);
                $mapped = (string) ($catalog['source']?->id ?? '') !== (string) ($row['catalog_source_id'] ?? '')
                    || (string) ($catalog['model']?->id ?? '') !== (string) ($row['catalog_model_id'] ?? '');
                $summary['catalog_lineage'][$mapped ? 'remapped' : 'preserve']++;
            } catch (\InvalidArgumentException) {
                $summary['catalog_lineage']['blocked']++;
            }
        }

        return $summary;
    }

    private function sameGovernanceSnapshot(mixed $stored, mixed $current): bool
    {
        if (! is_array($stored) || ! is_array($current)) {
            return false;
        }

        return $this->canonicalizeArray($stored) === $this->canonicalizeArray($current);
    }

    private function canonicalizeArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalizeArray($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function verifyManifest(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $fields,
        Collection $rows,
        bool $isRestore,
    ): array {
        $contract = $isRestore ? ProductSystemRestoreContract::class : ProductTransferContract::class;
        $format = $contract::FORMAT;
        $metadata = [];
        foreach (array_slice($sheet->toArray(null, false, false, false), 1) as $row) {
            $key = trim((string) ($row[0] ?? ''));
            if ($key === '' || isset($metadata[$key])) {
                throw new \InvalidArgumentException("Invalid {$format} metadata key.");
            }
            $metadata[$key] = trim((string) ($row[1] ?? ''));
        }
        $version = $metadata['format_version'] ?? '';
        if (($metadata['format'] ?? null) !== $format || ! in_array($version, ['1', (string) $contract::VERSION], true)) {
            throw new \InvalidArgumentException("Invalid {$format} metadata.");
        }
        if (! $isRestore && (int) $version >= 2
            && ($metadata['catalog_lineage_policy'] ?? '') !== 'PRESERVE_BY_SHA256_AND_SCOPED_NORMALIZED_IDENTITY') {
            throw new \InvalidArgumentException('Invalid PRODUCT_TRANSFER catalog lineage policy.');
        }
        if ($fields !== $contract::fields((int) $version)
            || ($metadata['product_count'] ?? null) !== (string) $rows->count()
            || ! hash_equals(ProductSystemRestoreContract::columnsChecksum($fields), $metadata['columns_sha256'] ?? '')
            || ! hash_equals(ProductSystemRestoreContract::contentChecksum($fields, $rows, (int) $version), $metadata['content_sha256'] ?? '')) {
            throw new \InvalidArgumentException("Invalid or modified {$format} manifest.");
        }

        $metadata['contract'] = $isRestore ? 'SYSTEM_PRODUCT_RESTORE' : 'PRODUCT_TRANSFER';

        return $metadata;
    }

    /**
     * Detect file type from extension.
     */
    public static function detectFileType(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx'        => 'xlsx',
            'csv'         => 'csv',
            'json'        => 'json',
            default       => throw new \InvalidArgumentException("Unsupported file extension: {$ext}"),
        };
    }

    /**
     * Get allowed MIME types for upload validation.
     */
    public static function allowedMimeTypes(): array
    {
        return [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
            'text/csv',
            'text/plain',
            'application/csv',
            'application/json',
        ];
    }

    /**
     * Get max file size in KB.
     */
    public static function maxFileSizeKb(): int
    {
        $mb = (int) setting('import_export.max_file_size_mb', config('import-export.limits.max_file_size_mb', 10));
        return max(1, min($mb, (int) config('import-export.limits.max_file_size_mb', 10))) * 1024;
    }
}
