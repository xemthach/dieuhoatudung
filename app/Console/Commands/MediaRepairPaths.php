<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CaseStudy;
use App\Models\LandingSection;
use App\Models\PolicyPage;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MediaRepairPaths extends Command
{
    protected $signature = 'media:repair-paths
                            {--dry-run : Preview changes without writing}
                            {--model= : Repair a specific model only}';

    protected $description = 'Convert full CDN/local URLs in database back to relative paths. Does NOT delete files.';

    private int $scanned = 0;
    private int $converted = 0;
    private int $skipped = 0;

    /**
     * Models and their media fields to check.
     */
    private function getModelsToRepair(): array
    {
        return [
            Product::class => ['main_image', 'og_image'],
            Brand::class => ['logo'],
            Post::class => ['cover_image', 'og_image'],
            CaseStudy::class => ['cover_image', 'og_image'],
            ProductDocument::class => ['file_path'],
            Testimonial::class => ['avatar', 'image'],
            SiteSetting::class => ['value'],
            LandingSection::class => ['content'],
            PolicyPage::class => ['content'],
        ];
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $filterModel = $this->option('model');

        $this->info('');
        $this->info($dryRun
            ? '╔══════════════════════════════════════════════╗'
            : '╔══════════════════════════════════════════════╗');
        $this->info($dryRun
            ? '║     MEDIA REPAIR PATHS — DRY RUN             ║'
            : '║     MEDIA REPAIR PATHS — LIVE                 ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        // Collect all base URLs that should be stripped
        $baseUrls = $this->collectBaseUrls();

        if (empty($baseUrls)) {
            $this->warn('Không tìm thấy base URL nào để xử lý.');
            return 1;
        }

        $this->info('Base URLs to strip:');
        foreach ($baseUrls as $url) {
            $this->line("  → {$url}");
        }
        $this->info('');

        if (!$dryRun && !$this->confirm('Bạn có chắc muốn sửa DB? Backup trước khi tiếp tục.')) {
            $this->info('Cancelled.');
            return 0;
        }

        foreach ($this->getModelsToRepair() as $modelClass => $fields) {
            $shortName = class_basename($modelClass);

            if ($filterModel && strtolower($shortName) !== strtolower($filterModel)) {
                continue;
            }

            $this->info("── {$shortName} ──");
            $this->repairModel($modelClass, $fields, $baseUrls, $dryRun);
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║              REPAIR SUMMARY                  ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info("  Scanned:   {$this->scanned}");
        $this->info("  Converted: {$this->converted}");
        $this->info("  Skipped:   {$this->skipped}");
        $this->info('');

        if ($dryRun && $this->converted > 0) {
            $this->warn("→ Run without --dry-run to apply changes.");
        }

        return 0;
    }

    private function repairModel(string $modelClass, array $fields, array $baseUrls, bool $dryRun): void
    {
        $records = $modelClass::all();

        foreach ($records as $record) {
            $changed = false;

            foreach ($fields as $field) {
                $value = $record->getRawOriginal($field);
                if (empty($value)) continue;

                $this->scanned++;

                // Handle JSON arrays (gallery_json, images_json, etc.)
                if ($this->isJsonArray($value)) {
                    $decoded = is_array($value) ? $value : json_decode($value, true);
                    if (!is_array($decoded)) continue;

                    $newArray = [];
                    $arrayChanged = false;
                    foreach ($decoded as $item) {
                        if (is_string($item)) {
                            $converted = $this->convertToRelative($item, $baseUrls);
                            if ($converted !== $item) {
                                $arrayChanged = true;
                            }
                            $newArray[] = $converted;
                        } else {
                            $newArray[] = $item;
                        }
                    }

                    if ($arrayChanged) {
                        $this->converted++;
                        if (!$dryRun) {
                            $record->{$field} = $newArray;
                            $changed = true;
                        }
                        $this->line("  [{$record->id}] {$field}: (array) → converted");
                    }
                    continue;
                }

                // Handle HTML content (content fields) — replace URLs inside HTML
                if ($this->isHtmlContent($value)) {
                    $newValue = $value;
                    $count = 0;
                    foreach ($baseUrls as $baseUrl) {
                        $newValue = str_replace($baseUrl . '/', '', $newValue, $c);
                        $count += $c;
                    }
                    if ($count > 0) {
                        // Don't convert HTML content paths — they need full URLs to render
                        // Only log as info, don't modify
                        $this->skipped++;
                        continue;
                    }
                    continue;
                }

                // Handle simple string paths
                if (is_string($value)) {
                    $converted = $this->convertToRelative($value, $baseUrls);
                    if ($converted !== $value) {
                        $this->converted++;
                        $this->line("  [{$record->id}] {$field}: {$this->truncate($value)} → {$converted}");
                        if (!$dryRun) {
                            $record->{$field} = $converted;
                            $changed = true;
                        }
                    }
                }
            }

            if ($changed) {
                $record->saveQuietly();
            }
        }
    }

    /**
     * Strip base URL prefixes to get a relative path.
     * Preserves external URLs that don't match any base URL.
     */
    private function convertToRelative(string $value, array $baseUrls): string
    {
        // Skip empty or already relative
        if (empty($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            // Also handle /storage/ prefix
            if (str_starts_with($value, '/storage/')) {
                return substr($value, strlen('/storage/'));
            }
            return $value;
        }

        foreach ($baseUrls as $baseUrl) {
            $baseUrlTrimmed = rtrim($baseUrl, '/') . '/';
            if (str_starts_with($value, $baseUrlTrimmed)) {
                return substr($value, strlen($baseUrlTrimmed));
            }
        }

        // Not a recognized base URL — keep as-is (external URL)
        $this->skipped++;
        return $value;
    }

    /**
     * Collect all base URLs that could appear in the database.
     */
    private function collectBaseUrls(): array
    {
        $urls = [];

        // APP_URL/storage
        $appUrl = config('app.url');
        if ($appUrl) {
            $urls[] = rtrim($appUrl, '/') . '/storage';
        }

        // R2 CDN URL
        $r2PublicUrl = setting('r2_storage.r2_public_url');
        if ($r2PublicUrl) {
            $urls[] = rtrim($r2PublicUrl, '/');

            // With default folder
            $defaultFolder = setting('r2_storage.r2_default_folder');
            if ($defaultFolder) {
                $urls[] = rtrim($r2PublicUrl, '/') . '/' . trim($defaultFolder, '/');
            }
        }

        // Old base URLs from settings
        $oldUrls = setting('r2_storage.r2_old_base_urls');
        if ($oldUrls) {
            $parsed = array_filter(array_map('trim', explode("\n", $oldUrls)));
            foreach ($parsed as $url) {
                $urls[] = rtrim($url, '/');
            }
        }

        // Common localhost patterns
        $urls[] = 'http://localhost/storage';
        $urls[] = 'http://127.0.0.1/storage';

        return array_unique($urls);
    }

    private function isJsonArray(mixed $value): bool
    {
        if (is_array($value)) return true;
        if (!is_string($value)) return false;
        return str_starts_with($value, '[');
    }

    private function isHtmlContent(mixed $value): bool
    {
        return is_string($value) && (str_contains($value, '<') && str_contains($value, '>'));
    }

    private function truncate(string $value, int $max = 70): string
    {
        return strlen($value) > $max ? substr($value, 0, $max) . '…' : $value;
    }
}
