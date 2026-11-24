<?php

namespace App\Services\DataTransfer;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ZipArchive;

/** Shared file safety only; business, transfer, restore and catalog keep separate contracts. */
final class SpreadsheetFileGuard
{
    public static function looksLikeFormula(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $text = ltrim($value, " \t\r\n\v\f");

        return preg_match('/^[=+@-]/', $text) === 1 && ! is_numeric($text);
    }

    public function assertFile(string $path, string $type, ?string $originalName = null): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Import file does not exist or cannot be read.');
        }
        if (! in_array($type, config('import-export.allowed_import_types', ['csv', 'xlsx', 'json']), true)) {
            throw new InvalidArgumentException('Only XLSX, CSV, and JSON imports are supported.');
        }
        if ($originalName !== null && strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== $type) {
            throw new InvalidArgumentException('File extension does not match the declared import type.');
        }
        if (filesize($path) > DataImportService::maxFileSizeKb() * 1024) {
            throw new InvalidArgumentException('Import file exceeds the configured size limit.');
        }

        if ($type === 'xlsx') {
            $this->assertXlsx($path);

            return;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $allowed = $type === 'json'
            ? ['application/json', 'text/plain', 'application/x-empty']
            : ['text/plain', 'text/csv', 'application/csv', 'application/x-empty'];
        if (! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('Import content does not match a text CSV/JSON file.');
        }
    }

    private function assertXlsx(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Only OOXML XLSX files are accepted; legacy XLS files are rejected.');
        }
        try {
            if ($zip->numFiles > $this->limit('max_zip_entries')) {
                throw new InvalidArgumentException('XLSX package contains too many archive entries.');
            }
            $bytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false) {
                    throw new InvalidArgumentException('Invalid XLSX archive entry.');
                }
                $bytes += $entry['size'];
                if ($bytes > $this->limit('max_uncompressed_bytes')) {
                    throw new InvalidArgumentException('XLSX decompressed content exceeds the configured size limit.');
                }
                if (str_ends_with(strtolower($entry['name']), 'vbaproject.bin')) {
                    throw new InvalidArgumentException('Macro-enabled workbooks are not accepted.');
                }
            }
        } finally {
            $zip->close();
        }

        // A ZIP signature is insufficient: verify the actual OOXML workbook.
        $reader = new Xlsx;
        if (! $reader->canRead($path)) {
            throw new InvalidArgumentException('Invalid XLSX workbook; renamed archives are rejected.');
        }
        // Check dimensions before building the in-memory cell graph.
        $cells = 0;
        foreach ($reader->listWorksheetInfo($path) as $sheet) {
            if ((int) $sheet['totalRows'] > $this->limit('max_records') + 1 || (int) $sheet['totalColumns'] > $this->limit('max_columns')) {
                throw new InvalidArgumentException('Worksheet dimensions exceed configured import limits.');
            }
            $cells += (int) $sheet['totalRows'] * (int) $sheet['totalColumns'];
            if ($cells > $this->limit('max_cells')) {
                throw new InvalidArgumentException('Workbook exceeds the configured cell limit.');
            }
        }
    }

    /** Run before toArray() or any calculation API evaluates formula cells. */
    public function assertSpreadsheet(Spreadsheet $spreadsheet): void
    {
        $cells = 0;
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $columns = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            if ($sheet->getHighestRow() > $this->limit('max_records') + 1 || $columns > $this->limit('max_columns')) {
                throw new InvalidArgumentException('Worksheet dimensions exceed configured import limits.');
            }
            $cells += $sheet->getHighestRow() * $columns;
            if ($cells > $this->limit('max_cells')) {
                throw new InvalidArgumentException('Workbook exceeds the configured cell limit.');
            }
            foreach ($sheet->getCoordinates(false) as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw new InvalidArgumentException("Formula cells are not allowed (sheet {$sheet->getTitle()}, cell {$coordinate}).");
                }
            }
        }
    }

    public function assertRows(Collection $rows): void
    {
        if ($rows->count() > $this->limit('max_records')) {
            throw new InvalidArgumentException('Import exceeds the configured record limit.');
        }
        $cells = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) > $this->limit('max_columns')) {
                throw new InvalidArgumentException('Import contains an invalid record or too many columns.');
            }
            $cells += count($row);
            if ($cells > $this->limit('max_cells')) {
                throw new InvalidArgumentException('Import exceeds the configured cell limit.');
            }
        }
    }

    private function limit(string $key): int
    {
        return max(1, (int) config('import-export.limits.'.$key));
    }
}
