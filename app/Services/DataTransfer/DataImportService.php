<?php

namespace App\Services\DataTransfer;

use App\Models\DataImportJob;
use App\Models\Brand;
use App\Models\ProductCategory;
use App\Support\EncodingGuard;
use App\Support\Spreadsheet\SpreadsheetLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
    ): DataImportJob {
        // Store the uploaded file privately
        $storagePath = 'data-imports/' . $module;
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        Storage::disk('local')->makeDirectory($storagePath);
        
        $destination = $storagePath . '/' . $fileName;
        Storage::disk('local')->put($destination, file_get_contents($filePath));

        $formatContext = $module === 'product' && $fileType === 'xlsx'
            ? ($this->detectProductSystemRestore($destination) ?? $this->detectProductTransfer($destination))
            : null;
        if ($formatContext !== null) {
            $mode = ($formatContext['contract'] ?? null) === 'PRODUCT_TRANSFER' ? 'product_transfer' : 'system_restore';
            $matchingKey = $mode === 'system_restore' ? 'id' : 'sku';
            $formatContext['governance_snapshot'] = app(ImportGovernanceService::class)->snapshot();
            $formatContext['previewed_at'] = now()->toIso8601String();
            $formatContext['previewed_by'] = $userId ?? auth()->id();
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
            $rows = $this->parseFile($destination, $fileType);

            if ($rows->isEmpty()) {
                $job->update([
                    'status'            => 'empty',
                    'error_report_json' => [['row' => 0, 'errors' => ['File rỗng hoặc không đọc được dữ liệu.']]],
                    'finished_at'       => now(),
                ]);
                return $job;
            }

            // Validate each row
            $validationResult = $this->validateRows($rows, $module, $mode, $matchingKey);

            if ($formatContext !== null) {
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

            // Build preview data (first 20 rows)
            $previewRows = $rows->take(20)->map(fn ($row, $i) => [
                'row_number' => $i + 1,
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
            $job->update([
                'status'            => 'failed',
                'error_report_json' => [['row' => 0, 'errors' => [$e->getMessage()]]],
                'finished_at'       => now(),
            ]);
        }

        return $job;
    }

    /**
     * Confirm and execute the import after preview.
     */
    public function confirmImport(DataImportJob $job): DataImportJob
    {
        if ($job->status !== 'previewing') {
            throw new \RuntimeException('Import job is not in preview state.');
        }

        $job->update(['status' => 'importing', 'started_at' => now()]);

        try {
            $rows = $this->parseFile($job->file_path, $job->file_type);
            $handler = $this->getModuleHandler($job->module);

            // A system restore is an all-or-nothing, manifest-bound operation.
            // Re-check the workbook after preview so a replaced file cannot cause
            // a partial restore or silently change FK meaning.
            if ($job->mode === 'system_restore') {
                $formatContext = $this->detectProductSystemRestore($job->file_path);
                if ($formatContext === null) {
                    throw new \RuntimeException('SYSTEM RESTORE metadata is missing.');
                }
                $validation = $this->validateRows($rows, $job->module, $job->mode, $job->matching_key);
                if ($validation['error_count'] > 0) {
                    $job->update([
                        'status' => 'failed',
                        'failed_rows' => $validation['error_count'],
                        'error_report_json' => $validation['all_errors'],
                        'finished_at' => now(),
                    ]);

                    return $job;
                }
            }
            if ($job->mode === 'product_transfer') {
                $formatContext = $this->detectProductTransfer($job->file_path);
                if ($formatContext === null) throw new \RuntimeException('PRODUCT_TRANSFER metadata is missing.');
                $storedPolicies = data_get($job->format_context_json, 'governance_snapshot', []);
                if (! $this->sameGovernanceSnapshot($storedPolicies, app(ImportGovernanceService::class)->snapshot())) {
                    $job->update(['status'=>'blocked','error_report_json'=>[['row'=>0,'errors'=>['Governance policies changed after Preview. Upload and preview again.']]],'finished_at'=>now()]);
                    return $job;
                }
                $validation = $this->validateRows($rows, $job->module, $job->mode, $job->matching_key);
                if ($validation['error_count'] > 0) {
                    $job->update(['status'=>'blocked','failed_rows'=>$validation['error_count'],'error_report_json'=>$validation['all_errors'],'finished_at'=>now()]);
                    return $job;
                }
            }

            $stats = [
                'success' => 0,
                'failed'  => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => [],
            ];

            $chunkSize = (int) setting('import_export.import_chunk_size', 100);

            foreach ($rows as $index => $row) {
                try {
                    DB::beginTransaction();
                    $result = $handler->importRow($row, $job->mode, $job->matching_key);
                    DB::commit();

                    $stats['success']++;
                    if ($result === 'created') $stats['created']++;
                    if ($result === 'updated') $stats['updated']++;
                    if ($result === 'skipped') $stats['skipped']++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'row'    => $index + 1,
                        'errors' => [$e->getMessage()],
                        'data'   => array_slice($row, 0, 5),
                    ];
                }
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
            $job->update([
                'status'            => 'failed',
                'error_report_json' => [['row' => 0, 'errors' => [$e->getMessage()]]],
                'finished_at'       => now(),
            ]);
        }

        return $job;
    }

    /**
     * Parse a file into a collection of associative arrays.
     */
    public function parseFile(string $storagePath, string $fileType): Collection
    {
        $fullPath = storage_path('app/private/' . $storagePath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("File not found: {$storagePath}");
        }

        return match ($fileType) {
            'xlsx'  => $this->parseXlsx($fullPath),
            'csv'   => $this->parseCsv($fullPath),
            'xml'   => $this->parseXml($fullPath),
            'json'  => $this->parseJson($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Parse XLSX file.
     */
    protected function parseXlsx(string $path): Collection
    {
        $spreadsheet = SpreadsheetLoader::load($path);
        $sheet = $spreadsheet->getSheetByName(ProductSystemRestoreContract::DATA_SHEET) ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $payloads = $this->readSystemPayloads($spreadsheet);
        $spreadsheet->disconnectWorksheets();

        if (empty($rows)) return collect();

        $headers = array_map('trim', array_map('strval', $rows[0]));
        $data = collect();

        for ($i = 1; $i < count($rows); $i++) {
            $row = [];
            $hasValue = false;
            foreach ($headers as $colIndex => $header) {
                if (empty($header)) continue;
                $value = $rows[$i][$colIndex] ?? '';
                $row[$header] = is_string($value) ? trim($value) : $value;
                if ($value !== '' && $value !== null) $hasValue = true;
            }
            foreach ($row as $field => $value) {
                if (! is_string($value) || ! str_starts_with($value, ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX)) {
                    continue;
                }
                $payloadField = substr($value, strlen(ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX));
                $payload = $payloads[(string) ($row['id'] ?? '')][$payloadField] ?? null;
                if ($payload !== null) {
                    $row[$field] = $payload;
                }
            }
            if ($hasValue) {
                $data->push($row);
            }
        }

        return $data;
    }

    /** @return array<string, array<string, string>> */
    private function readSystemPayloads($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName(ProductSystemRestoreContract::PAYLOAD_SHEET)
            ?? $spreadsheet->getSheetByName(ProductTransferContract::PAYLOAD_SHEET);
        if ($sheet === null) {
            return [];
        }

        $payloads = [];
        foreach (array_slice($sheet->toArray(null, true, true, false), 1) as $row) {
            $productId = (string) ($row[0] ?? '');
            $field = (string) ($row[1] ?? '');
            $chunkIndex = (int) ($row[2] ?? 0);
            if ($productId === '' || $field === '') {
                continue;
            }
            $payloads[$productId][$field][$chunkIndex] = (string) ($row[3] ?? '');
        }

        foreach ($payloads as $productId => $fields) {
            foreach ($fields as $field => $chunks) {
                ksort($chunks);
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

        $lines = explode("\n", str_replace("\r\n", "\n", $content));
        $lines = array_filter($lines, fn ($line) => trim($line) !== '');

        if (empty($lines)) return collect();

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);

        $data = collect();
        foreach ($lines as $line) {
            $values = str_getcsv($line);
            $row = [];
            $hasValue = false;
            foreach ($headers as $colIndex => $header) {
                if (empty($header)) continue;
                $value = $values[$colIndex] ?? '';
                $row[$header] = trim($value);
                if ($value !== '') $hasValue = true;
            }
            if ($hasValue) {
                $data->push($row);
            }
        }

        return $data;
    }

    /**
     * Parse XML file.
     */
    protected function parseXml(string $path): Collection
    {
        $content = file_get_contents($path);
        $content = $this->ensureUtf8($content);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \RuntimeException('Không thể đọc file XML.');
        }

        $data = collect();
        foreach ($xml->record as $record) {
            $row = [];
            foreach ($record->children() as $child) {
                $row[$child->getName()] = trim((string) $child);
            }
            if (!empty($row)) {
                $data->push($row);
            }
        }

        return $data;
    }

    /**
     * Parse JSON file.
     */
    protected function parseJson(string $path): Collection
    {
        $content = file_get_contents($path);
        $content = $this->ensureUtf8($content);

        $decoded = json_decode($content, true);
        if ($decoded === null) {
            throw new \RuntimeException('Không thể đọc file JSON: ' . json_last_error_msg());
        }

        // If it's a flat array of objects, use as-is
        if (isset($decoded[0]) && is_array($decoded[0])) {
            return collect($decoded)->map(fn ($row) =>
                array_map(fn ($v) => is_array($v) ? EncodingGuard::jsonEncode($v) : $v, $row)
            );
        }

        return collect($decoded);
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
    protected function validateRows(Collection $rows, string $module, string $mode, string $matchingKey): array
    {
        $handler = $this->getModuleHandler($module);
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

            // Check required fields
            foreach ($requiredFields as $field) {
                if (empty($row[$field] ?? null)) {
                    $errors[] = "Thiếu trường bắt buộc: {$field}";
                }
            }

            // Module-specific validation
            $moduleErrors = $handler->validateRow($row, $mode, $matchingKey);
            $errors = array_merge($errors, $moduleErrors);

            if ($mode === 'system_restore') {
                foreach (array_keys($seen) as $unique) {
                    $value = (string) ($row[$unique] ?? '');
                    if ($value === '') continue;
                    if (isset($seen[$unique][$value])) {
                        $errors[] = "System restore contains duplicate {$unique}: {$value}";
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
                $exists = $handler->findExisting($row, 'sku')
                    ?? $handler->findExisting($row, 'slug');
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
                    'row'    => $index + 1,
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
                'row' => $index + 1,
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
    protected function getModuleHandler(string $module): Contracts\ImportHandlerInterface
    {
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
            'catalog_lineage' => ['preserve' => 0, 'detach_required' => 0, 'blocked' => 0],
        ];

        foreach ($rows as $row) {
            foreach ([
                ['brand_mapping', Brand::class, 'brand_slug', 'source_brand_id'],
                ['category_mapping', ProductCategory::class, 'category_slug', 'source_category_id'],
            ] as [$key, $model, $slugField, $sourceIdField]) {
                $matches = $model::withTrashed()->where('slug', (string) ($row[$slugField] ?? ''))->get(['id']);
                if ($matches->isEmpty()) $summary[$key]['missing']++;
                elseif ($matches->count() > 1) $summary[$key]['ambiguous']++;
                elseif ((int) $matches->first()->id === (int) ($row[$sourceIdField] ?? 0)) $summary[$key]['exact']++;
                else $summary[$key]['remapped']++;
            }

            $hasLineage = filled($row['catalog_source_id'] ?? null) || filled($row['catalog_model_id'] ?? null);
            if (! $hasLineage) {
                $summary['catalog_lineage']['preserve']++;
            } elseif (app(ImportGovernanceService::class)->catalogDetachEnabled()) {
                $summary['catalog_lineage']['detach_required']++;
            } else {
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

    private function detectProductSystemRestore(string $storagePath): ?array
    {
        $fullPath = storage_path('app/private/'.$storagePath);
        $spreadsheet = SpreadsheetLoader::load($fullPath);
        $metadataSheet = $spreadsheet->getSheetByName(ProductSystemRestoreContract::METADATA_SHEET);
        if ($metadataSheet === null) {
            $spreadsheet->disconnectWorksheets();
            return null;
        }

        $metadata = [];
        foreach ($metadataSheet->toArray(null, true, true, false) as $index => $row) {
            if ($index === 0) continue;
            $key = trim((string) ($row[0] ?? ''));
            if ($key !== '') $metadata[$key] = trim((string) ($row[1] ?? ''));
        }
        $spreadsheet->disconnectWorksheets();

        if (($metadata['format'] ?? null) !== ProductSystemRestoreContract::FORMAT
            || (int) ($metadata['format_version'] ?? 0) !== ProductSystemRestoreContract::VERSION) {
            throw new \InvalidArgumentException('Invalid PRODUCT_SYSTEM_RESTORE metadata.');
        }

        $rows = $this->parseFile($storagePath, 'xlsx');
        $fields = array_keys($rows->first() ?? []);
        if ($fields !== ProductSystemRestoreContract::fields()
            || ($metadata['columns_sha256'] ?? '') !== ProductSystemRestoreContract::columnsChecksum($fields)
            || (int) ($metadata['product_count'] ?? -1) !== $rows->count()
            || ! hash_equals((string) ($metadata['content_sha256'] ?? ''), ProductSystemRestoreContract::contentChecksum($fields, $rows))) {
            throw new \InvalidArgumentException('Invalid or modified PRODUCT_SYSTEM_RESTORE manifest.');
        }

        return $metadata + ['contract' => 'SYSTEM_PRODUCT_RESTORE'];
    }

    private function detectProductTransfer(string $storagePath): ?array
    {
        $fullPath = storage_path('app/private/'.$storagePath);
        $spreadsheet = SpreadsheetLoader::load($fullPath);
        $metadataSheet = $spreadsheet->getSheetByName(ProductTransferContract::METADATA_SHEET);
        if ($metadataSheet === null) { $spreadsheet->disconnectWorksheets(); return null; }
        $metadata = [];
        foreach ($metadataSheet->toArray(null, true, true, false) as $index => $row) {
            if ($index === 0) continue;
            $key = trim((string) ($row[0] ?? ''));
            if ($key !== '') $metadata[$key] = trim((string) ($row[1] ?? ''));
        }
        $spreadsheet->disconnectWorksheets();
        if (($metadata['format'] ?? null) !== ProductTransferContract::FORMAT || (int) ($metadata['format_version'] ?? 0) !== ProductTransferContract::VERSION) throw new \InvalidArgumentException('Invalid PRODUCT_TRANSFER metadata.');
        $rows = $this->parseFile($storagePath, 'xlsx'); $fields = array_keys($rows->first() ?? []);
        if ($fields !== ProductTransferContract::fields()
            || ($metadata['columns_sha256'] ?? '') !== ProductSystemRestoreContract::columnsChecksum($fields)
            || (int) ($metadata['product_count'] ?? -1) !== $rows->count()
            || ! hash_equals((string) ($metadata['content_sha256'] ?? ''), ProductSystemRestoreContract::contentChecksum($fields, $rows))) {
            throw new \InvalidArgumentException('Invalid or modified PRODUCT_TRANSFER manifest.');
        }
        return $metadata + ['contract' => 'PRODUCT_TRANSFER'];
    }

    /**
     * Detect file type from extension.
     */
    public static function detectFileType(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx', 'xls' => 'xlsx',
            'csv'         => 'csv',
            'xml'         => 'xml',
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
            'application/vnd.ms-excel', // xls
            'text/csv',
            'text/plain',
            'application/csv',
            'text/xml',
            'application/xml',
            'application/json',
        ];
    }

    /**
     * Get max file size in KB.
     */
    public static function maxFileSizeKb(): int
    {
        $mb = (int) setting('import_export.max_file_size_mb', 10);
        return $mb * 1024;
    }
}
