<?php

namespace Tests\Feature;

use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\SpreadsheetFileGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class ImportFileSafetyTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_legacy_xls_and_xml_are_rejected_at_the_import_boundary(): void
    {
        $path = $this->file('legacy.xls', 'name,sku'."\n".'Example,EXAMPLE');
        foreach (['xls', 'xml', 'zip'] as $type) {
            try {
                app(DataImportService::class)->uploadAndPreview('product', $path, 'invalid.'.$type, $type);
                $this->fail('Unsupported import type was accepted: '.$type);
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('Only XLSX, CSV, and JSON', $error->getMessage());
            }
        }
    }

    public function test_csv_renamed_to_xlsx_is_rejected_before_job_creation(): void
    {
        $path = $this->file('renamed.xlsx', "name,sku\nExample,EXAMPLE");
        $this->expectException(InvalidArgumentException::class);
        app(DataImportService::class)->uploadAndPreview('product', $path, 'renamed.xlsx', 'xlsx');
    }

    public function test_zip_with_content_types_but_without_a_workbook_is_rejected(): void
    {
        $path = $this->file('archive.xlsx', '');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->close();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XLSX workbook');
        app(DataImportService::class)->uploadAndPreview('product', $path, 'archive.xlsx', 'xlsx');
    }

    public function test_formula_cells_are_rejected_before_their_calculated_values_are_read(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['name', 'sku'], ['Example', '=1+1']]);
        $path = $this->file('formula.xlsx', '');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        $job = app(DataImportService::class)->uploadAndPreview('lead', $path, 'formula.xlsx', 'xlsx');
        $this->files[] = storage_path('app/private/'.$job->file_path);
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('Formula cells are not allowed', $job->error_report_json[0]['errors'][0]);
    }

    public function test_zip_decompression_and_record_limits_are_enforced(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['name'], ['Example'], ['Second']]);
        $path = $this->file('bounded.xlsx', '');
        (new Xlsx($book))->save($path);
        config(['import-export.limits.max_uncompressed_bytes' => 10]);
        try {
            app(SpreadsheetFileGuard::class)->assertFile($path, 'xlsx');
            $this->fail('Oversized decompressed workbook was accepted.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('decompressed content', $error->getMessage());
        }
        config(['import-export.limits.max_uncompressed_bytes' => 128 * 1024 * 1024, 'import-export.limits.max_records' => 1]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dimensions exceed');
        app(SpreadsheetFileGuard::class)->assertSpreadsheet($book);
    }

    public function test_text_file_declared_as_json_must_contain_record_objects(): void
    {
        $path = $this->file('invalid.json', '"not a row list"');
        $job = app(DataImportService::class)->uploadAndPreview('lead', $path, 'invalid.json', 'json');
        $this->files[] = storage_path('app/private/'.$job->file_path);
        $this->assertSame('failed', $job->status);
    }

    private function file(string $name, string $content): string
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/'.bin2hex(random_bytes(6)).'-'.$name;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
