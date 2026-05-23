<?php

namespace App\Console\Commands;

use App\Services\Encoding\UTF8RepairService;
use App\Support\EncodingGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class EncodingGovernanceAuditCommand extends Command
{
    protected $signature = 'encoding:governance-audit
                            {--report-dir= : Output directory under storage/app/private/reports}
                            {--limit=200 : Max suspicious DB rows per table-column in detail report}';

    protected $description = 'Full UTF-8 governance audit: DB collation, mojibake scan, source file encoding matrix';

    private const DB_TARGETS = [
        'site_settings' => ['value'],
        'mail_templates' => ['name', 'subject', 'body_html', 'body_text'],
        'products' => ['name', 'short_description', 'long_description', 'seo_title', 'seo_description', 'og_title', 'og_description', 'warranty_info', 'installation_note'],
        'product_categories' => ['name', 'intro', 'content', 'seo_title', 'seo_description', 'og_title', 'og_description'],
        'brands' => ['name', 'description'],
        'posts' => ['title', 'excerpt', 'content', 'seo_title', 'seo_description'],
        'post_categories' => ['name', 'description'],
        'promotions' => ['name', 'description', 'cta_text', 'seo_title', 'seo_description', 'og_title', 'og_description'],
        'policy_pages' => ['title', 'content'],
        'faqs' => ['question', 'answer'],
        'hero_slides' => ['title', 'subtitle', 'description', 'button_text'],
        'home_benefit_items' => ['title', 'description'],
        'landing_sections' => ['title', 'subtitle', 'content'],
        'site_campaigns' => ['name', 'title', 'subtitle', 'body'],
        'ai_product_drafts' => ['title', 'content'],
    ];

    private const SOURCE_PATHS = [
        'app',
        'config',
        'database',
        'resources',
        'routes',
        'tests',
        'lang',
        'storage/app',
    ];

    private const TEXT_EXTENSIONS = [
        'php', 'blade.php', 'js', 'ts', 'json', 'md', 'txt', 'yaml', 'yml', 'xml', 'csv', 'env',
    ];

    public function __construct(private readonly UTF8RepairService $repairService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $timestamp = now()->format('Ymd_His');
        $reportDir = $this->resolveReportDir((string) $this->option('report-dir'), $timestamp);
        $limit = max(1, (int) $this->option('limit'));

        $this->info('Auditing DB charset/collation matrix...');
        $dbMatrix = $this->auditDbMatrix();
        file_put_contents($reportDir.'/db-matrix.json', EncodingGuard::jsonEncode($dbMatrix, JSON_PRETTY_PRINT));
        $this->writeCsv($reportDir.'/db-table-collations.csv', ['table', 'collation'], $dbMatrix['tables']);
        $this->writeCsv($reportDir.'/db-column-collations.csv', ['table', 'column', 'charset', 'collation', 'data_type'], $dbMatrix['columns']);

        $this->info('Scanning source encoding matrix...');
        $sourceRows = $this->auditSourceEncoding();
        $this->writeCsv($reportDir.'/source-encoding-matrix.csv', ['path', 'size', 'status', 'has_bom', 'valid_utf8', 'mojibake_score'], $sourceRows);

        $this->info('Scanning DB mojibake rows (dry-run)...');
        $dbRows = $this->auditDbMojibake($limit);
        $this->writeCsv($reportDir.'/db-mojibake-dry-run.csv', ['table', 'id', 'field', 'classification', 'confidence', 'current_text', 'repaired_text', 'action'], $dbRows);

        $summary = [
            'generated_at' => now()->toIso8601String(),
            'database' => $dbMatrix['database'],
            'non_utf8_table_count' => $dbMatrix['non_utf8_table_count'],
            'non_utf8_column_count' => $dbMatrix['non_utf8_column_count'],
            'source_issue_count' => count(array_filter($sourceRows, fn ($r) => ($r['status'] ?? 'ok') !== 'ok')),
            'db_mojibake_candidate_count' => count($dbRows),
            'report_dir' => $reportDir,
        ];

        file_put_contents($reportDir.'/summary.json', EncodingGuard::jsonEncode($summary, JSON_PRETTY_PRINT));

        $this->info("UTF-8 governance audit completed. Report dir: {$reportDir}");

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   database:array<string,mixed>,
     *   tables:array<int,array<string,mixed>>,
     *   columns:array<int,array<string,mixed>>,
     *   non_utf8_table_count:int,
     *   non_utf8_column_count:int
     * }
     */
    private function auditDbMatrix(): array
    {
        $driver = DB::getDriverName();
        $tables = [];
        $columns = [];
        $database = [
            'charset' => null,
            'collation' => null,
            'connection_charset' => null,
            'connection_collation' => null,
            'driver' => $driver,
        ];

        if ($driver === 'mysql') {
            $dbVars = DB::selectOne('SELECT @@character_set_database AS charset, @@collation_database AS collation, @@character_set_connection AS connection_charset, @@collation_connection AS connection_collation');
            $database = [
                'charset' => $dbVars->charset ?? null,
                'collation' => $dbVars->collation ?? null,
                'connection_charset' => $dbVars->connection_charset ?? null,
                'connection_collation' => $dbVars->connection_collation ?? null,
                'driver' => $driver,
            ];

            $tables = collect(DB::select('SELECT TABLE_NAME AS table_name, TABLE_COLLATION AS table_collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'))
                ->map(fn ($r) => [
                    'table' => $r->table_name,
                    'collation' => $r->table_collation,
                ])
                ->values()
                ->all();

            $columns = collect(DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, CHARACTER_SET_NAME AS charset_name, COLLATION_NAME AS collation_name, DATA_TYPE AS data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL ORDER BY TABLE_NAME, ORDINAL_POSITION'))
                ->map(fn ($r) => [
                    'table' => $r->table_name,
                    'column' => $r->column_name,
                    'charset' => $r->charset_name,
                    'collation' => $r->collation_name,
                    'data_type' => $r->data_type,
                ])
                ->values()
                ->all();
        } else {
            foreach (Schema::getTableListing() as $table) {
                $tables[] = ['table' => $table, 'collation' => null];
                foreach (Schema::getColumnListing($table) as $column) {
                    $columns[] = [
                        'table' => $table,
                        'column' => $column,
                        'charset' => null,
                        'collation' => null,
                        'data_type' => null,
                    ];
                }
            }
        }

        $nonUtf8TableCount = $driver === 'mysql'
            ? count(array_filter($tables, fn ($t) => ! str_starts_with((string) ($t['collation'] ?? ''), 'utf8mb4')))
            : 0;
        $nonUtf8ColumnCount = $driver === 'mysql'
            ? count(array_filter($columns, fn ($c) => ($c['charset'] ?? '') !== 'utf8mb4'))
            : 0;

        return [
            'database' => $database,
            'tables' => $tables,
            'columns' => $columns,
            'non_utf8_table_count' => $nonUtf8TableCount,
            'non_utf8_column_count' => $nonUtf8ColumnCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditDbMojibake(int $limitPerColumn): array
    {
        $existingTables = $this->buildTableLookup();

        $rows = [];

        foreach (self::DB_TARGETS as $table => $columns) {
            if (! isset($existingTables[$table])) {
                continue;
            }

            $actualColumns = Schema::getColumnListing($table);
            foreach (array_values(array_intersect($columns, $actualColumns)) as $column) {
                $query = DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->limit($limitPerColumn);

                foreach ($query->get() as $record) {
                    $value = $record->$column;
                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    if (! $this->repairService->isLikelyMojibake($value)) {
                        continue;
                    }

                    $analysis = $this->repairService->analyze($value);
                    $rows[] = [
                        'table' => $table,
                        'id' => (int) ($record->id ?? 0),
                        'field' => $column,
                        'classification' => $analysis['classification'],
                        'confidence' => $analysis['confidence'],
                        'current_text' => $analysis['original'],
                        'repaired_text' => $analysis['repaired'],
                        'action' => $analysis['classification'] === 'mojibake_recoverable' && $analysis['confidence'] >= 0.85 ? 'candidate_update' : 'manual_review',
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditSourceEncoding(): array
    {
        $rows = [];
        foreach ($this->sourceFiles() as $file) {
            $content = @file_get_contents($file->getPathname());
            if (! is_string($content) || str_contains($content, "\0")) {
                continue;
            }

            $stripped = EncodingGuard::stripBom($content);
            $hasBom = EncodingGuard::hasBom($content);
            $validUtf8 = EncodingGuard::isValidUtf8($stripped);
            $score = $validUtf8 ? EncodingGuard::mojibakeScore($stripped) : 999;

            $status = 'ok';
            if (! $validUtf8) {
                $status = 'legacy_or_broken';
            } elseif ($hasBom) {
                $status = 'utf8_bom';
            } elseif ($score > 0) {
                $status = 'mojibake';
            }

            $rows[] = [
                'path' => $this->relativePath($file->getPathname()),
                'size' => $file->getSize(),
                'status' => $status,
                'has_bom' => $hasBom ? 'yes' : 'no',
                'valid_utf8' => $validUtf8 ? 'yes' : 'no',
                'mojibake_score' => $score,
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function sourceFiles(): iterable
    {
        foreach (self::SOURCE_PATHS as $path) {
            $absolute = base_path($path);
            if (! file_exists($absolute)) {
                continue;
            }

            if (is_file($absolute)) {
                $file = new SplFileInfo($absolute);
                if ($this->isTextFile($file)) {
                    yield $file;
                }
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                if (! $this->isTextFile($file)) {
                    continue;
                }
                yield $file;
            }
        }
    }

    private function isTextFile(SplFileInfo $file): bool
    {
        $name = strtolower($file->getFilename());
        $ext = strtolower($file->getExtension());
        if (in_array($ext, self::TEXT_EXTENSIONS, true)) {
            return true;
        }

        foreach (self::TEXT_EXTENSIONS as $suffix) {
            if (str_ends_with($name, '.'.$suffix) || $name === $suffix) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $headers
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if (! $handle) {
            return;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line);
        }
        fclose($handle);
    }

    private function resolveReportDir(string $customDir, string $timestamp): string
    {
        $relativeDir = trim($customDir) !== ''
            ? trim(str_replace('\\', '/', $customDir), '/')
            : "private/reports/utf8-governance-{$timestamp}";

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
}
