<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\R2SyncJob;
use App\Models\R2SyncItem;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Exception;

class MediaUrlReplaceService
{
    private array $modelsToScan = [
        \App\Models\Product::class => ['main_image', 'gallery_json', 'documents_json', 'short_description', 'long_description', 'og_image'],
        \App\Models\Brand::class => ['logo', 'content'],
        \App\Models\Post::class => ['cover_image', 'content', 'og_image'],
        \App\Models\CaseStudy::class => ['cover_image', 'gallery_json', 'problem', 'solution', 'result', 'og_image'],
        \App\Models\ProductDocument::class => ['file_path'],
        \App\Models\Testimonial::class => ['avatar', 'image'],
        \App\Models\SiteSetting::class => ['value'],
        \App\Models\LandingSection::class => ['content', 'settings_json'],
        \App\Models\PolicyPage::class => ['content'],
    ];

    /** Cache of confirmed-synced paths to avoid repeated DB queries */
    private array $syncedPathsCache = [];

    public function __construct(private SettingService $settingService) {}

    /**
     * Replace old base URLs with new CDN base URL in all media fields.
     *
     * SAFETY: Only replaces URLs for files that are confirmed synced to R2
     * (tracked in media_files table with is_synced_to_r2 = true).
     * Unsynced file references are skipped and logged.
     */
    public function replaceUrls(R2SyncJob $job): void
    {
        $oldUrls = $job->old_base_urls;
        $newBaseUrl = $job->new_base_url;

        if (empty($oldUrls) || empty($newBaseUrl)) {
            $job->update(['status' => 'failed', 'error_message' => 'Thiếu old_urls hoặc new_base_url.']);
            return;
        }

        $job->update(['status' => 'replacing']);

        // Pre-load all synced paths into cache for performance
        $this->warmSyncedPathsCache();

        $replacedRecords = 0;
        $replacedOccurrences = 0;
        $skippedOccurrences = 0;

        foreach ($this->modelsToScan as $modelClass => $fields) {
            if (!class_exists($modelClass)) continue;

            $records = $modelClass::all();

            foreach ($records as $record) {
                $recordChanged = false;
                $recordOccurrences = 0;
                $recordSkipped = 0;

                foreach ($fields as $field) {
                    $originalValue = $record->getRawOriginal($field);
                    if (empty($originalValue)) continue;

                    $newValue = $originalValue;
                    
                    // Arrays/JSON
                    if (is_array($originalValue)) {
                        $newValueJson = json_encode($originalValue, JSON_UNESCAPED_SLASHES);
                        $result = $this->replaceInString($newValueJson, $oldUrls, $newBaseUrl);
                        if ($result['replaced'] > 0) {
                            $recordOccurrences += $result['replaced'];
                            $recordSkipped += $result['skipped'];
                            $newValue = json_decode($result['value'], true);
                        }
                    } 
                    // Strings (text, html, urls)
                    elseif (is_string($originalValue)) {
                        $result = $this->replaceInString($originalValue, $oldUrls, $newBaseUrl);
                        if ($result['replaced'] > 0) {
                            $recordOccurrences += $result['replaced'];
                            $recordSkipped += $result['skipped'];
                            $newValue = $result['value'];
                        }
                    }

                    if ($recordOccurrences > 0 && !$job->dry_run) {
                        $record->{$field} = $newValue;
                        $recordChanged = true;
                    }
                }

                if ($recordOccurrences > 0) {
                    $replacedOccurrences += $recordOccurrences;
                    $skippedOccurrences += $recordSkipped;
                    $replacedRecords++;
                    if (!$job->dry_run && $recordChanged) {
                        $record->saveQuietly();
                    }
                }
            }
        }

        $status = $skippedOccurrences > 0 ? 'completed_with_errors' : 'completed';
        $errorMessage = $skippedOccurrences > 0
            ? "Đã bỏ qua {$skippedOccurrences} URL vì file chưa được đồng bộ lên R2."
            : null;

        $job->update([
            'replaced_records' => $replacedRecords,
            'replaced_occurrences' => $replacedOccurrences,
            'skipped_files' => $skippedOccurrences,
            'status' => $status,
            'error_message' => $errorMessage,
            'finished_at' => now(),
        ]);

        if ($skippedOccurrences > 0) {
            Log::warning("[MediaUrlReplace] Job #{$job->id}: Skipped {$skippedOccurrences} URL(s) — files not confirmed synced to R2.");
        }
    }

    /**
     * Replace old URLs in a string, but only for paths confirmed synced to R2.
     *
     * @return array{value: string, replaced: int, skipped: int}
     */
    private function replaceInString(string $value, array $oldUrls, string $newBaseUrl): array
    {
        $replaced = 0;
        $skipped = 0;
        $newValue = $value;

        foreach ($oldUrls as $oldUrl) {
            $oldUrlTrimmed = rtrim($oldUrl, '/');
            $newUrlTrimmed = rtrim($newBaseUrl, '/');

            // Find all occurrences of oldUrl followed by a path
            // Pattern: oldUrl/some/path/file.ext
            $pattern = preg_quote($oldUrlTrimmed, '#') . '/([a-zA-Z0-9_./@-]+\.[a-zA-Z0-9]+)';
            
            if (preg_match_all('#' . $pattern . '#', $newValue, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fullMatch = $match[0]; // e.g., https://old-domain.com/storage/branding/logo.png
                    $relativePath = $match[1]; // e.g., branding/logo.png

                    if ($this->isPathSyncedToR2($relativePath)) {
                        $newValue = str_replace($fullMatch, $newUrlTrimmed . '/' . $relativePath, $newValue);
                        $replaced++;
                    } else {
                        $skipped++;
                        Log::info("[MediaUrlReplace] Skipped: {$fullMatch} — path '{$relativePath}' not synced to R2.");
                    }
                }
            }

            // Also handle escaped slashes (JSON-encoded URLs)
            $oldUrlEscaped = str_replace('/', '\\/', $oldUrlTrimmed);
            $patternEscaped = preg_quote($oldUrlEscaped, '#') . '\\\\/([a-zA-Z0-9_.\\\\/@ -]+\\.[a-zA-Z0-9]+)';
            
            if (preg_match_all('#' . $patternEscaped . '#', $newValue, $matchesEsc, PREG_SET_ORDER)) {
                foreach ($matchesEsc as $match) {
                    $fullMatch = $match[0];
                    $relativePath = str_replace('\\/', '/', $match[1]);

                    if ($this->isPathSyncedToR2($relativePath)) {
                        $replacement = str_replace('/', '\\/', $newUrlTrimmed) . '\\/' . str_replace('/', '\\/', $relativePath);
                        $newValue = str_replace($fullMatch, $replacement, $newValue);
                        $replaced++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return ['value' => $newValue, 'replaced' => $replaced, 'skipped' => $skipped];
    }

    /**
     * Check if a relative path is confirmed synced to R2.
     */
    private function isPathSyncedToR2(string $relativePath): bool
    {
        $relativePath = ltrim($relativePath, '/');

        if (isset($this->syncedPathsCache[$relativePath])) {
            return $this->syncedPathsCache[$relativePath];
        }

        // Fallback query (should rarely hit if cache is warmed)
        $synced = MediaFile::where('path', $relativePath)
            ->where('is_synced_to_r2', true)
            ->exists();

        $this->syncedPathsCache[$relativePath] = $synced;
        return $synced;
    }

    /**
     * Pre-load all synced paths from media_files table.
     */
    private function warmSyncedPathsCache(): void
    {
        $this->syncedPathsCache = MediaFile::where('is_synced_to_r2', true)
            ->pluck('path')
            ->mapWithKeys(fn ($path) => [$path => true])
            ->toArray();
    }
}
