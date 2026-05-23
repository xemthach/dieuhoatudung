<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EncodingGovernanceAuditCommandTest extends TestCase
{
    public function test_governance_audit_generates_summary_files(): void
    {
        $relativeDir = 'private/reports/test-utf8-governance-audit';
        $absoluteDir = storage_path('app/'.$relativeDir);
        File::deleteDirectory($absoluteDir);

        $this->artisan('encoding:governance-audit', [
            '--report-dir' => $relativeDir,
            '--limit' => 10,
        ])->assertExitCode(0);

        $this->assertFileExists($absoluteDir.'/summary.json');
        $this->assertFileExists($absoluteDir.'/db-matrix.json');
        $this->assertFileExists($absoluteDir.'/db-mojibake-dry-run.csv');
    }

    public function test_repair_dry_run_generates_reports_without_writing_db(): void
    {
        $relativeDir = 'private/reports/test-utf8-repair-dry-run';
        $absoluteDir = storage_path('app/'.$relativeDir);
        File::deleteDirectory($absoluteDir);

        $this->artisan('encoding:repair', [
            '--dry-run' => true,
            '--table' => 'post_categories',
            '--limit' => 20,
            '--report-dir' => $relativeDir,
        ])->assertExitCode(0);

        $files = File::files($absoluteDir);
        $names = collect($files)->map(fn ($f) => $f->getFilename())->all();

        $this->assertTrue((bool) collect($names)->first(fn ($n) => str_starts_with($n, 'encoding-repair-') && str_ends_with($n, '.json')));
        $this->assertTrue((bool) collect($names)->first(fn ($n) => str_starts_with($n, 'encoding-repair-') && str_ends_with($n, '.csv')));
    }
}

