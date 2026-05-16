<?php

namespace App\Services\DataTransfer;

use App\Models\DataExportJob;
use App\Support\EncodingGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
        ?int $userId = null,
    ): DataExportJob {
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

            $fields = $this->resolveFields($module, $fieldGroups);
            $query = $this->buildQuery($module, $filters, $selectedIds);
            $data = $this->fetchData($query, $fields, $module);

            $fileName = $this->generateFileName($module, $fileType);
            $filePath = $this->writeFile($data, $fields, $fileType, $fileName, $module);

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
     * Build the Eloquent query with optional filters and selected IDs.
     */
    protected function buildQuery(string $module, array $filters, array $selectedIds): Builder
    {
        $modelClass = ModuleRegistry::modelClass($module);
        $query = $modelClass::query();

        if (!empty($selectedIds)) {
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
    protected function writeFile(Collection $data, array $fields, string $fileType, string $fileName, string $module): string
    {
        $dir = 'data-exports/' . $module;
        Storage::disk('local')->makeDirectory($dir);
        $fullPath = storage_path('app/private/' . $dir . '/' . $fileName);

        match ($fileType) {
            'xlsx' => $this->writeXlsx($data, $fields, $fullPath),
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
    protected function writeXlsx(Collection $data, array $fields, string $path): void
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
        foreach ($data as $row) {
            foreach ($fields as $colIndex => $field) {
                $value = $row[$field] ?? '';
                $sheet->setCellValue([$colIndex + 1, $rowIndex], (string) $value);
            }
            $rowIndex++;
        }

        // Auto-size columns (up to 50 cols)
        foreach (range(1, min(count($fields), 50)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
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
    protected function generateFileName(string $module, string $fileType): string
    {
        $timestamp = now()->format('Y-m-d_His');
        return "{$module}_export_{$timestamp}.{$fileType}";
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
