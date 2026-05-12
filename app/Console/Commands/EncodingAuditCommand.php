<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan encoding:audit
 *
 * Scans all text columns in key tables for common UTF-8 mojibake patterns
 * that result from double-encoding (ISO-8859-1 bytes stored in a UTF-8 column).
 */
class EncodingAuditCommand extends Command
{
    protected $signature = 'encoding:audit {--table= : Audit a single table}';
    protected $description = 'Audit database for UTF-8 mojibake (double-encoded Vietnamese text)';

    /** Tables + columns to audit */
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
        'leads' => ['name', 'message'],
        'quote_requests' => ['customer_name', 'message'],
    ];

    /**
     * Patterns that indicate double-encoded Latin-1 → UTF-8 text.
     * These are the byte sequences you see when UTF-8 Vietnamese is
     * re-interpreted as Latin-1 and then re-encoded to UTF-8.
     */
    protected array $patterns = [
        'MÃ', // 'M' + 0xC3 0x83 — "Mã", "Mô", "Má"…
        'Ã¢', // 'â'
        'Ã´', // 'ô'
        'Ã ', // 'à'
        'Ã¡', // 'á'
        'Ã¢', // 'â'
        'Ã©', // 'é'
        'Ã', // generic starter
        'á»', // very common Vietnamese mojibake
        'áº', // Vietnamese
        'Ä', // Vietnamese đ, ă, ơ, ư…
        'Æ°', // ư
        'Æ', // ơ
        'â€', // smart quotes
        'Ã¬', // ì
        'Ã­', // í
        'Ã®', // î
        'Ã°', // ð
    ];

    public function handle(): int
    {
        $onlyTable = $this->option('table');
        $targets = $onlyTable
            ? array_intersect_key($this->targets, [$onlyTable => []])
            : $this->targets;

        if (empty($targets)) {
            $this->error("Table '{$onlyTable}' not in audit list.");
            return 1;
        }

        // Get tables that actually exist
        $existing = collect(DB::select("SHOW TABLES"))
            ->map(fn ($r) => array_values((array) $r)[0])
            ->flip();

        $totalIssues = 0;
        $rows = [];

        foreach ($targets as $table => $columns) {
            if (!isset($existing[$table])) {
                $this->line("<fg=gray> [SKIP] {$table} — table does not exist</>");
                continue;
            }

            // Get columns that actually exist in this table
            $tableColumns = collect(DB::select("SHOW COLUMNS FROM `{$table}`"))
                ->pluck('Field')->toArray();

            $cols = array_intersect($columns, $tableColumns);
            if (empty($cols)) continue;

            foreach ($cols as $col) {
                // Build LIKE clause for all patterns
                $wheres = [];
                $bindings = [];
                foreach ($this->patterns as $p) {
                    $wheres[] = "BINARY `{$col}` LIKE ?";
                    $bindings[] = '%' . $p . '%';
                }

                $sql = "SELECT id, `{$col}` FROM `{$table}` WHERE " . implode(' OR ', $wheres) . " LIMIT 50";
                $results = DB::select($sql, $bindings);

                foreach ($results as $r) {
                    $value = $r->$col ?? '';
                    $sample = mb_substr($value, 0, 80);
                    $totalIssues++;
                    $rows[] = [$table, $col, $r->id ?? '?', $sample];
                }
            }
        }

        if (empty($rows)) {
            $this->info('No mojibake detected in any audited table/column.');
            return 0;
        }

        $this->warn(" Found {$totalIssues} mojibake occurrences:");
        $this->table(['Table', 'Column', 'ID', 'Sample (80 chars)'], $rows);

        $this->newLine();
        $this->line("Run <fg=yellow>php artisan encoding:repair --dry-run</> to preview fixes.");
        $this->line("Run <fg=red>php artisan encoding:repair --apply</> to apply fixes (backup first!).");

        return 0;
    }
}
