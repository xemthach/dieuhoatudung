<?php

namespace App\Services\DataTransfer;

use App\Models\DataExportJob;
use App\Models\Product;
use App\Support\EncodingGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class DataExportService
{
    /**
     * Create an export job and process it.
     */
    public function export(
        string $module,
        string $fileType,
        array $fieldGroups = [],
        array $filters = [],
        array $selectedIds = [],
        string $scope = 'all',
        ?int $userId = null,
        string $intent = 'auto',
    ): DataExportJob {
        $scope = $this->normalizeScope($scope);
        $selectedIds = $this->normalizeIds($selectedIds);
        $governance = app(ImportGovernanceService::class);
        if ($intent === 'product_transfer' && ! $governance->enabled('product_transfer.enabled')) {
            throw new \RuntimeException('PRODUCT_TRANSFER is disabled by Import Governance.');
        }
        if ($intent === 'system_backup' && ! $this->isProductSystemRestoreExport($module, $fileType, $scope, $fieldGroups, $filters, $selectedIds)) {
            throw new \InvalidArgumentException('System Backup requires the complete unfiltered Product XLSX population.');
        }

        $job = DataExportJob::create([
            'module'             => $module,
            'file_type'          => $fileType,
            'field_groups_json'  => $fieldGroups ?: null,
            'filters_json'       => $filters ?: null,
            'selected_ids_json'  => $selectedIds ?: null,
            'status'             => 'pending',
            'created_by'        => $userId ?? auth()->id(),
        ]);

        try {
            $job->update(['status' => 'processing', 'started_at' => now()]);

            $systemRestoreExport = $intent === 'system_backup' || ($intent === 'auto' && $this->isProductSystemRestoreExport(
                $module,
                $fileType,
                $scope,
                $fieldGroups,
                $filters,
                $selectedIds,
            ));
            $productTransfer = $intent === 'product_transfer' && $module === 'product' && $fileType === 'xlsx';
            if ($intent === 'product_transfer' && ! $productTransfer) {
                throw new \InvalidArgumentException('PRODUCT_TRANSFER is available only for Product XLSX exports.');
            }

            $fields = $this->resolveFields($module, $fieldGroups);
            if ($systemRestoreExport) {
                $fields = ProductSystemRestoreContract::fields();
            }
            $query = $this->buildQuery($module, $filters, $selectedIds, $scope);
            if ($productTransfer) {
                $fields = ProductTransferContract::fields();
                $data = $this->fetchProductTransferData($query);
            } else {
                $data = $this->fetchData($query, $fields, $module);
            }

            $fileName = $this->generateFileName($module, $fileType, $scope, $data->count());
            $filePath = $this->writeFile($data, $fields, $fileType, $fileName, $module, $systemRestoreExport, $productTransfer, $scope);

            $job->update([
                'status'      => 'completed',
                'file_path'   => $filePath,
                'file_name'   => $fileName,
                'total_rows'  => $data->count(),
                'finished_at' => now(),
                'expires_at'  => now()->addDays((int) setting('import_export.keep_files_days', 30)),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status'      => 'failed',
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return $job;
    }

    private function fetchProductTransferData(Builder $query): Collection
    {
        return $query->with(['brand:id,slug', 'category:id,slug'])->select(ProductSystemRestoreContract::fields())->orderBy('id')->get()
            ->map(function ($product): array {
                $row = [];
                foreach (ProductSystemRestoreContract::fields() as $field) {
                    $value = $product->getAttribute($field);
                    if (is_array($value)) $value = EncodingGuard::jsonEncode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($value instanceof \BackedEnum) $value = $value->value;
                    $row[$field] = $value;
                }
                return array_merge($row, [
                'source_brand_id' => $product->brand_id, 'brand_slug' => $product->brand?->slug,
                'source_category_id' => $product->product_category_id, 'category_slug' => $product->category?->slug,
                'source_catalog_source_id' => $product->catalog_source_id, 'source_catalog_model_id' => $product->catalog_model_id,
                ]);
            });
    }

    /**
     * Resolve which fields to export based on selected groups.
     */
    protected function resolveFields(string $module, array $fieldGroups): array
    {
        if (empty($fieldGroups)) {
            return ModuleRegistry::allFields($module);
        }
        return ModuleRegistry::fieldsForGroups($module, $fieldGroups);
    }

    /**
     * A Product System Restore represents the complete, unfiltered Product
     * population. The export UI submits every group for "all fields", while
     * callers that omit grouping submit an empty list; both have the same
     * semantic intent.
     */
    private function isProductSystemRestoreExport(
        string $module,
        string $fileType,
        string $scope,
        array $fieldGroups,
        array $filters,
        array $selectedIds,
    ): bool {
        if ($module !== 'product' || $fileType !== 'xlsx' || $scope !== 'all' || $selectedIds !== []) {
            return false;
        }

        // Keep this aligned with buildQuery(): null and blank filter values do
        // not constrain the query. An empty array is intentionally effective
        // there, so it cannot be treated as a full-population restore export.
        foreach ($filters as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        if ($fieldGroups === []) {
            return true;
        }

        $submittedGroups = array_values(array_map('strval', $fieldGroups));
        $productGroups = array_keys(ModuleRegistry::fieldGroups('product'));
        sort($submittedGroups);
        sort($productGroups);

        return $submittedGroups === $productGroups;
    }

    /**
     * Build the Eloquent query with optional filters and selected IDs.
     */
    protected function buildQuery(string $module, array $filters, array $selectedIds, string $scope): Builder
    {
        $modelClass = ModuleRegistry::modelClass($module);
        $query = $modelClass::query();

        if (in_array($scope, ['selected', 'current_page', 'filter'], true)) {
            if ($scope === 'selected' && empty($selectedIds)) {
                throw new \InvalidArgumentException('Chưa chọn bản ghi để export.');
            }

            if (empty($selectedIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $selectedIds);
            }
        } elseif (!empty($selectedIds)) {
            $query->whereIn('id', $selectedIds);
        }

        // Apply basic column filters
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;

            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query;
    }

    protected function normalizeScope(string $scope): string
    {
        return match ($scope) {
            'filtered' => 'filter',
            'selected', 'current_page', 'filter', 'all' => $scope,
            default => throw new \InvalidArgumentException("Unsupported export scope: {$scope}"),
        };
    }

    protected function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Fetch data from DB and format for export.
     */
    protected function fetchData(Builder $query, array $fields, string $module): Collection
    {
        $chunkSize = (int) setting('import_export.export_chunk_size', 1000);

        $results = collect();
        $query->select($fields)->orderBy('id')
            ->chunk($chunkSize, function ($records) use (&$results, $fields, $module) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($fields as $field) {
                        $value = $record->$field;
                        // Serialize JSON/array fields
                        if (is_array($value)) {
                            $value = EncodingGuard::jsonEncode($value, JSON_PRETTY_PRINT);
                        }
                        // Format boolean fields
                        if (is_bool($value)) {
                            $value = $value ? '1' : '0';
                        }
                        // Handle enum values
                        if ($value instanceof \BackedEnum) {
                            $value = $value->value;
                        }
                        if (is_string($value)) {
                            $value = EncodingGuard::ensureUtf8($value, autoFixMojibake: true, rejectBroken: true, context: "export field {$field}");
                        }
                        $row[$field] = $value;
                    }
                    $results->push($row);
                }
            });

        return $results;
    }

    /**
     * Write data to a file in the specified format.
     */
    protected function writeFile(
        Collection $data,
        array $fields,
        string $fileType,
        string $fileName,
        string $module,
        bool $systemRestoreExport = false,
        bool $productTransfer = false,
        string $scope = 'all',
    ): string
    {
        if ($systemRestoreExport && (
            $module !== 'product'
            || $fileType !== 'xlsx'
            || $fields !== ProductSystemRestoreContract::fields()
        )) {
            throw new \LogicException('System Product Restore must use the canonical Product XLSX field contract.');
        }

        $dir = 'data-exports/' . $module;
        Storage::disk('local')->makeDirectory($dir);
        $fullPath = storage_path('app/private/' . $dir . '/' . $fileName);

        match ($fileType) {
            'xlsx' => $this->writeXlsx($data, $fields, $fullPath, $systemRestoreExport, $productTransfer, $scope),
            'csv'  => $this->writeCsv($data, $fields, $fullPath),
            'xml'  => $this->writeXml($data, $fields, $fullPath, $module),
            'json' => $this->writeJson($data, $fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$fileType}"),
        };

        return $dir . '/' . $fileName;
    }

    /**
     * Write XLSX file using PhpSpreadsheet.
     */
    protected function writeXlsx(Collection $data, array $fields, string $path, bool $systemRestore = false, bool $productTransfer = false, string $scope = 'all'): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        // Write headers
        foreach ($fields as $colIndex => $field) {
            $sheet->setCellValue([$colIndex + 1, 1], $field);
            $sheet->getStyle([$colIndex + 1, 1])->getFont()->setBold(true);
        }

        // Write data rows
        $rowIndex = 2;
        $payloadRows = [];
        foreach ($data as $row) {
            foreach ($fields as $colIndex => $field) {
                $value = $row[$field] ?? '';
                if ($systemRestore || $productTransfer) {
                    // A system restore is a data contract, not a presentation
                    // sheet. Keep IDs, decimal scale and JSON as literal strings;
                    // Excel's automatic numeric coercion alters the manifest.
                    $serialized = (string) $value;
                    if (mb_strlen($serialized) > ProductSystemRestoreContract::XLSX_CELL_SAFE_LENGTH) {
                        $token = ProductSystemRestoreContract::PAYLOAD_TOKEN_PREFIX.$field;
                        foreach (mb_str_split($serialized, ProductSystemRestoreContract::XLSX_CELL_SAFE_LENGTH) as $chunkIndex => $chunk) {
                            $payloadRows[] = [(string) ($row['id'] ?? ''), $field, $chunkIndex, $chunk];
                        }
                        $sheet->setCellValueExplicit([$colIndex + 1, $rowIndex], $token, DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValueExplicit([$colIndex + 1, $rowIndex], $serialized, DataType::TYPE_STRING);
                    }
                } else {
                    $sheet->setCellValue([$colIndex + 1, $rowIndex], (string) $value);
                }
            }
            $rowIndex++;
        }

        // Auto-size columns (up to 50 cols)
        foreach (range(1, min(count($fields), 50)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        if ($systemRestore || $productTransfer) {
            if ($payloadRows !== []) {
                $payload = $spreadsheet->createSheet();
                $payload->setTitle($productTransfer ? ProductTransferContract::PAYLOAD_SHEET : ProductSystemRestoreContract::PAYLOAD_SHEET);
                foreach (['product_id', 'field', 'chunk_index', 'value'] as $column => $header) {
                    $payload->setCellValue([$column + 1, 1], $header);
                }
                foreach ($payloadRows as $index => $payloadRow) {
                    foreach ($payloadRow as $column => $value) {
                        $payload->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
                    }
                }
                $payload->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
            }
            $metadata = $spreadsheet->createSheet();
            $metadata->setTitle($productTransfer ? ProductTransferContract::METADATA_SHEET : ProductSystemRestoreContract::METADATA_SHEET);
            $metadata->setCellValue('A1', 'key');
            $metadata->setCellValue('B1', 'value');
            $metadataRow = 2;
            $contractMetadata = $productTransfer ? ProductTransferContract::metadata($fields, $data, $scope) : ProductSystemRestoreContract::metadata($fields, $data);
            foreach ($contractMetadata as $key => $value) {
                $metadata->setCellValue([1, $metadataRow], $key);
                $metadata->setCellValue([2, $metadataRow], (string) $value);
                $metadataRow++;
            }
            $metadata->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
            $spreadsheet->setActiveSheetIndex(0);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * Write CSV file with UTF-8 BOM for proper Vietnamese display in Excel.
     */
    protected function writeCsv(Collection $data, array $fields, string $path): void
    {
        $addBom = (bool) setting('import_export.csv_utf8_bom', true);

        $fp = fopen($path, 'w');

        // Add UTF-8 BOM
        if ($addBom) {
            fwrite($fp, "\xEF\xBB\xBF");
        }

        // Write header
        fputcsv($fp, $fields);

        // Write data
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($fields as $field) {
                $csvRow[] = $row[$field] ?? '';
            }
            fputcsv($fp, $csvRow);
        }

        fclose($fp);
    }

    /**
     * Write XML file with proper UTF-8 encoding.
     */
    protected function writeXml(Collection $data, array $fields, string $path, string $module): void
    {
        $xml = new \XMLWriter();
        $xml->openUri($path);
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('data');
        $xml->writeAttribute('module', $module);
        $xml->writeAttribute('exported_at', now()->toIso8601String());
        $xml->writeAttribute('total', (string) $data->count());

        foreach ($data as $row) {
            $xml->startElement('record');
            foreach ($fields as $field) {
                $value = $row[$field] ?? '';
                $xml->startElement($field);
                $xml->text((string) $value);
                $xml->endElement();
            }
            $xml->endElement(); // record
        }

        $xml->endElement(); // data
        $xml->endDocument();
        $xml->flush();
    }

    /**
     * Write JSON file with UTF-8 support.
     */
    protected function writeJson(Collection $data, string $path): void
    {
        $json = EncodingGuard::jsonEncode($data->toArray(), JSON_PRETTY_PRINT);

        file_put_contents($path, $json);
    }

    /**
     * Generate a filename for the export.
     */
    protected function generateFileName(string $module, string $fileType, string $scope, int $rowCount): string
    {
        $timestamp = now()->format('Y-m-d_His');
        return "{$module}_export_{$scope}_{$rowCount}_{$timestamp}.{$fileType}";
    }

    /**
     * Get the download path for a completed export.
     */
    public function getDownloadPath(DataExportJob $job): ?string
    {
        if (!$job->isDownloadable()) {
            return null;
        }

        return storage_path('app/private/' . $job->file_path);
    }

    /**
     * Cleanup old export files.
     */
    public function cleanupExpired(): int
    {
        $expired = DataExportJob::where('status', 'completed')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $job) {
            if ($job->file_path && Storage::disk('local')->exists($job->file_path)) {
                Storage::disk('local')->delete($job->file_path);
            }
            $job->update(['status' => 'expired']);
            $count++;
        }

        return $count;
    }
}
