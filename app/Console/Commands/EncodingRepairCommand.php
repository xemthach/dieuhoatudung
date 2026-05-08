<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * php artisan encoding:repair --dry-run (safe preview)
 * php artisan encoding:repair --apply (write to DB, logs backup)
 *
 * Strategy: tries mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1')
 * and only replaces if the result looks more like valid UTF-8 Vietnamese.
 */
class EncodingRepairCommand extends Command
{
    protected $signature = 'encoding:repair
                                {--dry-run : Preview what would be fixed without writing}
                                {--apply : Apply fixes and write backup to log}
                                {--table= : Limit to one table}';

    protected $description = 'Repair UTF-8 mojibake in DB (dry-run or apply)';

    protected array $targets = [
        'site_settings' => ['value'],
        'mail_templates' => ['name', 'subject', 'body_html', 'body_text'],
        'products' => ['name', 'description', 'short_description', 'meta_title', 'meta_description'],
        'product_categories' => ['name', 'description', 'meta_title', 'meta_description'],
        'brands' => ['name', 'description'],
        'posts' => ['title', 'excerpt', 'content', 'meta_title', 'meta_description'],
        'post_categories' => ['name', 'description'],
        'tags' => ['name', 'description'],
        'policy_pages' => ['title', 'content'],
        'landing_sections' => ['title', 'subtitle', 'content'],
        'faqs' => ['question', 'answer'],
        'testimonials' => ['content', 'author_name'],
        'case_studies' => ['title', 'description', 'content'],
        'product_reviews' => ['content', 'author_name'],
        'product_questions' => ['question', 'answer'],
    ];

    protected array $patterns = [
        'MÃ', 'Ã¢', 'Ã´', 'Ã ', 'Ã¡', 'Ã©', 'Ã', 'á»', 'áº', 'Ä', 'Æ°', 'Æ', 'â€',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $apply = $this->option('apply');
        $onlyTable = $this->option('table');

        if (!$dryRun && !$apply) {
            $this->error('Specify --dry-run or --apply');
            return 1;
        }

        $logFile = storage_path('logs/encoding-repair-' . date('Y-m-d') . '.log');
        $targets = $onlyTable
            ? array_intersect_key($this->targets, [$onlyTable => []])
            : $this->targets;

        $existing = collect(DB::select("SHOW TABLES"))
            ->map(fn ($r) => array_values((array) $r)[0])
            ->flip();

        $repairRows = [];
        $totalFixed = 0;

        foreach ($targets as $table => $columns) {
            if (!isset($existing[$table])) continue;

            $tableColumns = collect(DB::select("SHOW COLUMNS FROM `{$table}`"))
                ->pluck('Field')->toArray();
            $cols = array_intersect($columns, $tableColumns);
            if (empty($cols)) continue;

            foreach ($cols as $col) {
                $wheres = [];
                $bindings = [];
                foreach ($this->patterns as $p) {
                    $wheres[] = "`{$col}` LIKE ?";
                    $bindings[] = '%' . $p . '%';
                }

                $sql = "SELECT id, `{$col}` FROM `{$table}` WHERE " . implode('OR ', $wheres);
                $results = DB::select($sql, $bindings);

                foreach ($results as $r) {
                    $original = $r->$col ?? '';
                    $fixed = $this->attemptFix($original);

                    if ($fixed === null || $fixed === $original) continue;

                    $totalFixed++;

                    $repairRows[] = [
                        $table,
                        $r->id ?? '?',
                        $col,
                        mb_substr($original, 0, 50),
                        mb_substr($fixed, 0, 50),
                    ];

                    if ($apply) {
                        // Write backup log first
                        $backupLine = json_encode([
                            'table' => $table,
                            'id' => $r->id ?? null,
                            'column' => $col,
                            'old' => $original,
                            'new' => $fixed,
                            'time' => now()->toIso8601String(),
                        ], JSON_UNESCAPED_UNICODE) . "\n";
                        file_put_contents($logFile, $backupLine, FILE_APPEND);

                        DB::table($table)
                            ->where('id', $r->id)
                            ->update([$col => $fixed]);
                    }
                }
            }
        }

        if (empty($repairRows)) {
            $this->info('No repairable mojibake found.');
            return 0;
        }

        $label = $dryRun ? '[DRY-RUN]' : '[APPLIED]';
        $this->warn("{$label} {$totalFixed} values to fix:");
        $this->table(['Table', 'ID', 'Column', 'Old (50c)', 'New (50c)'], $repairRows);

        if ($apply) {
            $this->info(" Applied {$totalFixed} fixes. Backup saved to: {$logFile}");
        } else {
            $this->line("Run with <fg=red>--apply</> to write these changes.");
        }

        return 0;
    }

    /**
     * Attempt to fix a mojibake string.
     * Returns the fixed string, or null if fix is not better.
     */
    protected function attemptFix(string $value): ?string
    {
        if (empty($value)) return null;

        // Strategy 1: treat string bytes as ISO-8859-1 and convert to UTF-8
        $fixed = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');

        // Only accept if the result is valid UTF-8 and looks better
        if (!mb_check_encoding($fixed, 'UTF-8')) return null;
        if ($fixed === $value) return null;

        // Heuristic: fixed version should have fewer "Ã" sequences
        $mojibakeCount = substr_count($value, 'Ã') + substr_count($value, 'á»') + substr_count($value, 'áº');
        $fixedCount = substr_count($fixed, 'Ã') + substr_count($fixed, 'á»') + substr_count($fixed, 'áº');

        if ($fixedCount >= $mojibakeCount) return null; // not better

        return $fixed;
    }
}
