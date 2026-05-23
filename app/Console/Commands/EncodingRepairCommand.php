<?php

namespace App\Console\Commands;

use App\Services\Encoding\UTF8RepairService;
use App\Support\EncodingGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EncodingRepairCommand extends Command
{
    protected $signature = 'encoding:repair
                                {--dry-run : Preview what would be fixed without writing}
                                {--apply : Apply fixes and write backup logs}
                                {--table= : Limit to one table}
                                {--min-confidence=0.85 : Minimum confidence to apply}
                                {--report-dir= : Output report directory under storage/app/private/reports}
                                {--limit=0 : Limit number of rows per column scan (0 = no limit)}';

    protected $description = 'Repair UTF-8 mojibake in DB with confidence scoring (dry-run/apply)';

    protected array $targets = [
        'site_settings' => ['value'],
        'mail_templates' => ['name', 'subject', 'body_html', 'body_text'],
        'products' => ['name', 'short_description', 'long_description', 'seo_title', 'seo_description', 'og_title', 'og_description', 'warranty_info', 'installation_note'],
        'product_categories' => ['name', 'intro', 'content', 'seo_title', 'seo_description', 'og_title', 'og_description'],
        'brands' => ['name', 'description'],
        'posts' => ['title', 'excerpt', 'content', 'seo_title', 'seo_description'],
        'post_categories' => ['name', 'description'],
        'tags' => ['name', 'description'],
        'policy_pages' => ['title', 'content'],
        'landing_sections' => ['title', 'subtitle', 'content'],
        'faqs' => ['question', 'answer'],
        'testimonials' => ['content', 'author_name'],
        'case_studies' => ['title', 'description', 'content'],
        'product_reviews' => ['content', 'author_name'],
        'product_questions' => ['question', 'answer'],
        'leads' => ['name', 'message'],
        'quote_requests' => ['customer_name', 'message'],
    ];

    public function __construct(private readonly UTF8RepairService $repairService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        $onlyTable = $this->option('table');
        $minConfidence = (float) $this->option('min-confidence');
        $limit = max(0, (int) $this->option('limit'));

        if (! $dryRun && ! $apply) {
            $this->error('Specify --dry-run or --apply');
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $reportDir = $this->resolveReportDir((string) $this->option('report-dir'), $timestamp);
        $logFile = $reportDir."/encoding-repair-backup-{$timestamp}.jsonl";
        $csvReport = $reportDir."/encoding-repair-{$timestamp}.csv";
        $jsonReport = $reportDir."/encoding-repair-{$timestamp}.json";

        $targets = $onlyTable
            ? array_intersect_key($this->targets, [$onlyTable => []])
            : $this->targets;

        $existing = $this->buildTableLookup();

        $summaryRows = [];
        $reportRows = [];
        $appliedCount = 0;
        $scannedCount = 0;
        $tableScanStats = [];

        foreach ($targets as $table => $columns) {
            if (! isset($existing[$table])) {
                continue;
            }

            $tableColumns = Schema::getColumnListing($table);
            $cols = array_values(array_intersect($columns, $tableColumns));
            if ($cols === []) {
                continue;
            }

            foreach ($cols as $col) {
                $tableKey = "{$table}.{$col}";
                $tableScanStats[$tableKey] = [
                    'scanned' => 0,
                    'candidates' => 0,
                ];

                $query = DB::table($table)
                    ->select('id', $col)
                    ->whereNotNull($col)
                    ->where($col, '<>', '');

                if ($limit > 0) {
                    $query->limit($limit);
                }

                foreach ($query->cursor() as $row) {
                    $value = $row->$col;
                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $scannedCount++;
                    $tableScanStats[$tableKey]['scanned']++;
                    $analysis = $this->repairService->analyze($value);
                    if ($analysis['classification'] === 'clean_utf8') {
                        continue;
                    }
                    $tableScanStats[$tableKey]['candidates']++;

                    $canApply = $analysis['improved']
                        && $analysis['classification'] === 'mojibake_recoverable'
                        && $analysis['confidence'] >= $minConfidence;

                    $action = 'skip';
                    if ($canApply) {
                        $action = $apply ? 'update' : 'preview_update';
                    } elseif ($analysis['classification'] === 'low_confidence') {
                        $action = 'manual_review';
                    } elseif ($analysis['classification'] === 'permanently_corrupted') {
                        $action = 'unsafe_skip';
                    }

                    $reportRows[] = [
                        'table' => $table,
                        'id' => (int) ($row->id ?? 0),
                        'field' => $col,
                        'current_text' => $analysis['original'],
                        'repaired_text' => $analysis['repaired'],
                        'confidence' => $analysis['confidence'],
                        'classification' => $analysis['classification'],
                        'original_score' => $analysis['original_score'],
                        'repaired_score' => $analysis['repaired_score'],
                        'action' => $action,
                    ];

                    $summaryRows[] = [
                        $table,
                        $row->id ?? '?',
                        $col,
                        mb_substr($analysis['original'], 0, 40),
                        mb_substr($analysis['repaired'], 0, 40),
                        number_format((float) $analysis['confidence'], 2),
                        $analysis['classification'],
                        $action,
                    ];

                    if ($apply && $canApply) {
                        file_put_contents($logFile, EncodingGuard::jsonEncode([
                            'table' => $table,
                            'id' => $row->id ?? null,
                            'field' => $col,
                            'old' => $analysis['original'],
                            'new' => $analysis['repaired'],
                            'confidence' => $analysis['confidence'],
                            'classification' => $analysis['classification'],
                            'updated_at' => now()->toIso8601String(),
                        ])."\n", FILE_APPEND);

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$col => $analysis['repaired']]);
                        $appliedCount++;
                    }
                }
            }
        }

        $this->writeReports($csvReport, $jsonReport, $reportRows);

        if ($summaryRows === []) {
            foreach ($tableScanStats as $tableCol => $stats) {
                $this->line(sprintf(
                    '%s => scanned: %d, candidates: %d',
                    $tableCol,
                    $stats['scanned'],
                    $stats['candidates']
                ));
            }
            $this->info('No mojibake candidate found.');
            $this->line("JSON report: {$jsonReport}");
            return self::SUCCESS;
        }

        $this->warn(($dryRun ? '[DRY-RUN]' : '[APPLY]')." scanned {$scannedCount} rows, candidates: ".count($summaryRows));
        $this->table(
            ['Table', 'ID', 'Field', 'Old (40c)', 'New (40c)', 'Conf', 'Class', 'Action'],
            $summaryRows
        );

        if ($apply) {
            $this->info("Applied {$appliedCount} high-confidence fixes.");
            $this->line("Backup JSONL: {$logFile}");
        } else {
            $this->line('Use --apply to commit only high-confidence recoverable rows.');
        }
        $this->line("CSV report: {$csvReport}");
        $this->line("JSON report: {$jsonReport}");

        return self::SUCCESS;
    }

    private function resolveReportDir(string $customDir, string $timestamp): string
    {
        $relativeDir = trim($customDir) !== ''
            ? trim(str_replace('\\', '/', $customDir), '/')
            : "private/reports/utf8-repair-{$timestamp}";

        $absoluteDir = storage_path('app/'.$relativeDir);
        if (! is_dir($absoluteDir)) {
            @mkdir($absoluteDir, 0777, true);
        }

        return $absoluteDir;
    }

    /**
     * @return array<string, true>
     */
    private function buildTableLookup(): array
    {
        $lookup = [];

        foreach (Schema::getTableListing() as $tableName) {
            $raw = (string) $tableName;
            if ($raw === '') {
                continue;
            }

            $lookup[$raw] = true;
            if (str_contains($raw, '.')) {
                $parts = explode('.', $raw);
                $short = (string) end($parts);
                if ($short !== '') {
                    $lookup[$short] = true;
                }
            }
        }

        return $lookup;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeReports(string $csvPath, string $jsonPath, array $rows): void
    {
        file_put_contents($jsonPath, EncodingGuard::jsonEncode([
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT));

        $handle = fopen($csvPath, 'wb');
        if (! $handle) {
            return;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['table', 'id', 'field', 'current_text', 'repaired_text', 'confidence', 'classification', 'original_score', 'repaired_score', 'action']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['table'] ?? '',
                $row['id'] ?? '',
                $row['field'] ?? '',
                $row['current_text'] ?? '',
                $row['repaired_text'] ?? '',
                $row['confidence'] ?? '',
                $row['classification'] ?? '',
                $row['original_score'] ?? '',
                $row['repaired_score'] ?? '',
                $row['action'] ?? '',
            ]);
        }
        fclose($handle);
    }
}
