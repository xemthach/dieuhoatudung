<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveEmojiCommand extends Command
{
    protected $signature = 'text:remove-emoji
        {--dry-run : Preview changes without applying}
        {--apply : Actually apply changes}
        {--tables= : Comma-separated list of tables to clean (default: all known)}';

    protected $description = 'Remove emoji characters from database text columns safely (UTF-8 safe)';

    /**
     * Emoji regex pattern — targets only emoji codepoints, NOT Vietnamese/CJK/Latin.
     */
    private const EMOJI_PATTERN = '/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2300}-\x{23FF}\x{2B50}\x{2B55}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{FE0F}]/u';

    /**
     * Tables and their text columns to scan.
     */
    private function getTargetColumns(): array
    {
        return [
            'products' => ['name', 'short_description', 'long_description', 'warranty_info', 'installation_note', 'seo_title', 'seo_description'],
            'posts' => ['title', 'content', 'excerpt', 'seo_title', 'seo_description'],
            'policy_pages' => ['title', 'content', 'seo_title', 'seo_description'],
            'site_settings' => ['value'],
            'brands' => ['name', 'description', 'seo_title', 'seo_description'],
            'product_categories' => ['name', 'description', 'seo_title', 'seo_description'],
            'post_categories' => ['name', 'description'],
            'faqs' => ['question', 'answer'],
            'testimonials' => ['content', 'reviewer_name'],
            'case_studies' => ['title', 'content', 'seo_title', 'seo_description'],
            'leads' => ['name', 'message'],
            'quote_requests' => ['name', 'notes'],
            'product_reviews' => ['title', 'content'],
            'product_questions' => ['question', 'answer'],
            'mail_templates' => ['subject', 'body_html', 'body_text'],
            'tags' => ['name'],
        ];
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $apply = $this->option('apply');

        if (!$dryRun && !$apply) {
            $this->error('Specify --dry-run to preview or --apply to execute.');
            return 1;
        }

        $this->info($dryRun ? '=== DRY RUN MODE ===' : '=== APPLYING CHANGES ===');

        $targetColumns = $this->getTargetColumns();

        // Filter tables if specified
        if ($tables = $this->option('tables')) {
            $only = array_map('trim', explode(',', $tables));
            $targetColumns = array_intersect_key($targetColumns, array_flip($only));
        }

        $logEntries = [];
        $totalAffected = 0;

        foreach ($targetColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $this->warn("  Table '{$table}' does not exist, skipping.");
                continue;
            }

            // Filter to only existing columns
            $existingColumns = Schema::getColumnListing($table);
            $columns = array_intersect($columns, $existingColumns);
            if (empty($columns)) continue;

            $this->info("Scanning: {$table} (" . implode(', ', $columns) . ")");

            $rows = DB::table($table)->get();
            $tableAffected = 0;

            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $col) {
                    $original = $row->$col ?? null;
                    if ($original === null || $original === '') continue;

                    $cleaned = preg_replace(self::EMOJI_PATTERN, '', $original);
                    // Clean up double spaces left by emoji removal
                    $cleaned = preg_replace('/  +/', ' ', $cleaned);
                    $cleaned = trim($cleaned);

                    if ($cleaned !== $original) {
                        $updates[$col] = $cleaned;
                        $logEntries[] = [
                            'table' => $table,
                            'id' => $row->id ?? '?',
                            'column' => $col,
                            'before' => mb_substr($original, 0, 80),
                            'after' => mb_substr($cleaned, 0, 80),
                        ];
                    }
                }

                if (!empty($updates)) {
                    $tableAffected++;
                    if ($apply && isset($row->id)) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            }

            if ($tableAffected > 0) {
                $this->info("  -> {$tableAffected} row(s) affected");
                $totalAffected += $tableAffected;
            } else {
                $this->line("  -> Clean (no emoji)");
            }
        }

        // Summary
        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->info("Total rows with emoji: {$totalAffected}");
        $this->info("Total changes: " . count($logEntries));

        if (!empty($logEntries)) {
            $this->table(
                ['Table', 'ID', 'Column', 'Before', 'After'],
                array_map(fn($e) => [$e['table'], $e['id'], $e['column'], $e['before'], $e['after']], array_slice($logEntries, 0, 30))
            );

            if (count($logEntries) > 30) {
                $this->info("... and " . (count($logEntries) - 30) . " more entries");
            }
        }

        // Write log file
        $logFile = storage_path('logs/emoji-clean.log');
        $logContent = "Emoji Cleanup Log — " . now()->toDateTimeString() . "\n";
        $logContent .= "Mode: " . ($dryRun ? 'DRY RUN' : 'APPLIED') . "\n";
        $logContent .= "Total affected: {$totalAffected}\n\n";
        foreach ($logEntries as $entry) {
            $logContent .= "[{$entry['table']}#{$entry['id']}] {$entry['column']}\n";
            $logContent .= "  BEFORE: {$entry['before']}\n";
            $logContent .= "  AFTER:  {$entry['after']}\n\n";
        }
        file_put_contents($logFile, $logContent);
        $this->info("Log saved to: {$logFile}");

        if ($dryRun && $totalAffected > 0) {
            $this->warn("Run with --apply to execute these changes.");
        }

        return 0;
    }
}
