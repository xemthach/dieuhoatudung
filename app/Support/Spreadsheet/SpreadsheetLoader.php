<?php

declare(strict_types=1);

namespace App\Support\Spreadsheet;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class SpreadsheetLoader
{
    /**
     * PhpSpreadsheet probes OOXML members such as /xl/worksheets/sheet1.xml
     * as archive paths. With open_basedir enabled, PHP emits a benign warning
     * for that internal path before ZipArchive resolves it inside the workbook.
     * Laravel converts warnings to exceptions, so suppress only this exact
     * library probe while retaining open_basedir for real filesystem access.
     */
    public static function load(string $path): Spreadsheet
    {
        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING
                && str_contains($message, 'open_basedir restriction in effect')
                && preg_match('/File\(\/xl\/[^)]*\.xml\)/', $message) === 1;
        });

        try {
            return IOFactory::load($path);
        } finally {
            restore_error_handler();
        }
    }
}
