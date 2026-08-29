<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class SpreadsheetLoaderTest extends TestCase
{
    public function test_xlsx_load_is_safe_when_open_basedir_is_enabled(): void
    {
        $path = storage_path('framework/testing/open-basedir-spreadsheet.xlsx');
        (new Xlsx(new Spreadsheet()))->save($path);

        try {
            $process = new Process([
                PHP_BINARY,
                '-d',
                'open_basedir='.base_path().PATH_SEPARATOR.sys_get_temp_dir(),
                '-r',
                'require '.var_export(base_path('vendor/autoload.php'), true).'; '
                    .'$s=\\App\\Support\\Spreadsheet\\SpreadsheetLoader::load('.var_export($path, true).'); '
                    .'echo $s->getActiveSheet()->getTitle();',
            ], base_path());
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('Worksheet', $process->getOutput());
        } finally {
            @unlink($path);
        }
    }
}
