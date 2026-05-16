<?php

namespace App\Services\DataTransfer;

use App\Models\DataImportJob;
use App\Support\EncodingGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $job = DataImportJob::create([
            'module'       => $module,
            'file_name'    => $originalName,
            'file_path'    => $destination,
            'file_type'    => $fileType,
            'mode'         => $mode,
            'matching_key' => $matchingKey,
            'status'       => 'validating',
            'created_by'   => $userId ?? auth()->id(),
            'started_at'   => now(),
        ]);

        try {
            // Parse the file
            $rows = $this->parseFile($destination, $fileType);

            if ($rows->isEmpty()) {
                $job->update([
                    'status'            => 'failed',
                    'error_report_json' => [['row' => 0, 'errors' => ['File rỗng hoặc không đọc được dữ liệu.']]],
                    'finished_at'       => now(),
                ]);
                return $job;
            }

            // Validate each row
            $validationResult = $this->validateRows($rows, $module, $mode, $matchingKey);

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
                'status'            => 'completed',
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
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
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
            if ($hasValue) {
                $data->push($row);
            }
        }

        return $data;
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

            // Determine action (create or update)
            $action = 'create';
            if ($mode === 'update' || $mode === 'upsert') {
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
