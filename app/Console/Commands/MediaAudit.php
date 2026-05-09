<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CaseStudy;
use App\Models\LandingSection;
use App\Models\MediaFile;
use App\Models\PolicyPage;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductReview;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaAudit extends Command
{
    protected $signature = 'media:audit
                            {--model= : Audit a specific model (e.g., Product, Brand)}
                            {--fix : Show repair suggestions}';

    protected $description = 'Audit all media fields across the system — report DB paths, local existence, R2 sync status, and broken URLs.';

    private int $totalFields = 0;
    private int $okCount = 0;
    private int $missingLocal = 0;
    private int $fakeCdnUrls = 0;
    private int $fullUrls = 0;
    private int $emptyPaths = 0;
    private int $syncedToR2 = 0;

    /**
     * Map of model class => [field => directory_hint].
     */
    private function getModelsToAudit(): array
    {
        return [
            SiteSetting::class => [
                '_filter' => fn ($query) => $query->where('group', 'branding')->whereIn('key', [
                    'logo_image', 'logo_dark_image', 'logo_mobile_image', 'favicon', 'apple_touch_icon',
                ]),
                '_field' => 'value',
                '_label_field' => 'key',
            ],
            Product::class => [
                'main_image' => 'products',
                'og_image' => 'products/seo',
            ],
            Brand::class => [
                'logo' => 'brands',
            ],
            Post::class => [
                'cover_image' => 'posts',
                'og_image' => 'posts/seo',
            ],
            CaseStudy::class => [
                'cover_image' => 'case-studies',
                'og_image' => 'case-studies/seo',
            ],
            ProductDocument::class => [
                'file_path' => 'products/documents',
            ],
            Testimonial::class => [
                'avatar' => 'testimonials',
                'image' => 'testimonials',
            ],
            ProductReview::class => [
                'images_json' => 'reviews',
            ],
            User::class => [
                'avatar_url' => 'avatars',
            ],
        ];
    }

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║        MEDIA AUDIT — Full System Scan        ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        $r2Enabled = setting('r2_storage.r2_enabled', false);
        $publicUrl = setting('r2_storage.r2_public_url');
        $this->info("R2 Status: " . ($r2Enabled ? '🟢 Enabled' : '🔴 Disabled'));
        $this->info("R2 Public URL: " . ($publicUrl ?: '(not set)'));
        $this->info("MediaFile records: " . MediaFile::count());
        $this->info("Synced to R2: " . MediaFile::where('is_synced_to_r2', true)->count());
        $this->info('');

        $models = $this->getModelsToAudit();
        $filterModel = $this->option('model');

        foreach ($models as $modelClass => $config) {
            $shortName = class_basename($modelClass);

            if ($filterModel && strtolower($shortName) !== strtolower($filterModel)) {
                continue;
            }

            // Special handling for SiteSetting (audit specific keys)
            if ($modelClass === SiteSetting::class) {
                $this->auditSiteSettings($config);
                continue;
            }

            $this->info("── {$shortName} ──");
            $records = $modelClass::all();

            if ($records->isEmpty()) {
                $this->line("   (no records)");
                $this->info('');
                continue;
            }

            $rows = [];
            foreach ($records as $record) {
                foreach ($config as $field => $directory) {
                    $value = $record->getRawOriginal($field);
                    $this->auditField($rows, $shortName, $record->id, $field, $value);
                }
            }

            if (!empty($rows)) {
                $this->table(
                    ['ID', 'Field', 'DB Value', 'Local?', 'R2 Synced?', 'Status'],
                    $rows
                );
            }
            $this->info('');
        }

        $this->printSummary();

        return 0;
    }

    private function auditSiteSettings(array $config): void
    {
        $this->info("── SiteSetting (Branding) ──");

        $filter = $config['_filter'];
        $field = $config['_field'];
        $labelField = $config['_label_field'];

        $records = SiteSetting::query()->tap($filter)->get();
        $rows = [];

        foreach ($records as $record) {
            $value = $record->$field;
            $label = $record->$labelField;
            $this->auditField($rows, 'Branding', $label, $label, $value);
        }

        if (!empty($rows)) {
            $this->table(
                ['ID', 'Field', 'DB Value', 'Local?', 'R2 Synced?', 'Status'],
                $rows
            );
        }
        $this->info('');
    }

    private function auditField(array &$rows, string $model, mixed $id, string $field, mixed $value): void
    {
        $this->totalFields++;

        // Handle JSON arrays (gallery, images_json)
        if (is_array($value) || $this->isJson($value)) {
            $paths = is_array($value) ? $value : json_decode($value, true);
            if (!is_array($paths)) {
                $this->emptyPaths++;
                return;
            }
            foreach ($paths as $i => $path) {
                if (is_string($path) && filled($path)) {
                    $this->auditSinglePath($rows, $model, $id, "{$field}[{$i}]", $path);
                }
            }
            return;
        }

        if (empty($value) || in_array($value, ['{}', '[]'], true)) {
            $this->emptyPaths++;
            return;
        }

        $this->auditSinglePath($rows, $model, $id, $field, $value);
    }

    private function auditSinglePath(array &$rows, string $model, mixed $id, string $field, string $path): void
    {
        $isFullUrl = filter_var($path, FILTER_VALIDATE_URL);

        if ($isFullUrl) {
            $this->fullUrls++;

            // Check if this is a fake CDN URL (CDN base URL + path but not synced)
            $publicUrl = setting('r2_storage.r2_public_url');
            if ($publicUrl && str_starts_with($path, $publicUrl)) {
                $relativePath = substr($path, strlen(rtrim($publicUrl, '/') . '/'));
                $defaultFolder = setting('r2_storage.r2_default_folder');
                if ($defaultFolder && str_starts_with($relativePath, trim($defaultFolder, '/') . '/')) {
                    $relativePath = substr($relativePath, strlen(trim($defaultFolder, '/') . '/'));
                }

                $synced = MediaFile::where('path', $relativePath)->where('is_synced_to_r2', true)->exists();
                if (!$synced) {
                    $this->fakeCdnUrls++;
                    $rows[] = [$id, $field, $this->truncate($path), '-', '❌', '🔴 FAKE CDN URL'];
                    return;
                }
                $this->syncedToR2++;
                $rows[] = [$id, $field, $this->truncate($path), '-', '✅', '✅ CDN URL (synced)'];
                return;
            }

            $rows[] = [$id, $field, $this->truncate($path), '-', '-', '🔵 External URL'];
            return;
        }

        // Relative path
        $relativePath = ltrim($path, '/');
        if (str_starts_with($relativePath, 'storage/')) {
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        $localExists = Storage::disk('public')->exists($relativePath);
        $synced = MediaFile::where('path', $relativePath)->where('is_synced_to_r2', true)->exists();

        if ($synced) $this->syncedToR2++;

        if ($localExists) {
            $this->okCount++;
            $status = $synced ? '✅ OK (local + R2)' : '✅ OK (local only)';
        } else {
            $this->missingLocal++;
            $status = $synced ? '⚠️ Local missing, R2 OK' : '🔴 MISSING everywhere';
        }

        $rows[] = [
            $id,
            $field,
            $this->truncate($relativePath),
            $localExists ? '✅' : '❌',
            $synced ? '✅' : '—',
            $status,
        ];
    }

    private function printSummary(): void
    {
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║              AUDIT SUMMARY                   ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');
        $this->info("  Total fields scanned:   {$this->totalFields}");
        $this->info("  Empty/null paths:       {$this->emptyPaths}");
        $this->info("  ✅ OK (local exists):   {$this->okCount}");
        $this->info("  ✅ Synced to R2:        {$this->syncedToR2}");
        $this->info("  🔵 External URLs:       {$this->fullUrls}");
        $this->info("  ❌ Missing local:       {$this->missingLocal}");
        $this->info("  🔴 Fake CDN URLs:       {$this->fakeCdnUrls}");
        $this->info('');

        if ($this->fakeCdnUrls > 0) {
            $this->error("⚠ Found {$this->fakeCdnUrls} fake CDN URL(s) — files not synced to R2!");
            if ($this->option('fix')) {
                $this->warn("  → Run: php artisan media:repair-paths");
            }
        }

        if ($this->missingLocal > 0) {
            $this->warn("⚠ Found {$this->missingLocal} path(s) with missing local files.");
        }

        if ($this->fakeCdnUrls === 0 && $this->missingLocal === 0) {
            $this->info('🎉 All media paths are healthy!');
        }
    }

    private function truncate(string $value, int $max = 60): string
    {
        return strlen($value) > $max ? '…' . substr($value, -($max - 1)) : $value;
    }

    private function isJson(mixed $value): bool
    {
        if (!is_string($value)) return false;
        return str_starts_with($value, '[') || str_starts_with($value, '{');
    }
}
