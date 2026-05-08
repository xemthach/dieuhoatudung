<?php

namespace App\Services\Media;

use App\Models\R2SyncJob;
use App\Models\R2SyncItem;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\DB;
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

    public function __construct(private SettingService $settingService) {}

    public function replaceUrls(R2SyncJob $job): void
    {
        $oldUrls = $job->old_base_urls;
        $newBaseUrl = $job->new_base_url;

        if (empty($oldUrls) || empty($newBaseUrl)) {
            $job->update(['status' => 'failed', 'error_message' => 'Thiếu old_urls hoặc new_base_url.']);
            return;
        }

        $job->update(['status' => 'replacing']);

        $replacedRecords = 0;
        $replacedOccurrences = 0;

        foreach ($this->modelsToScan as $modelClass => $fields) {
            if (!class_exists($modelClass)) continue;

            $records = $modelClass::all();

            foreach ($records as $record) {
                $recordChanged = false;
                $recordOccurrences = 0;

                foreach ($fields as $field) {
                    $originalValue = $record->getRawOriginal($field);
                    if (empty($originalValue)) continue;

                    $newValue = $originalValue;
                    
                    // Arrays/JSON
                    if (is_array($originalValue)) {
                        $newValueJson = json_encode($originalValue, JSON_UNESCAPED_SLASHES);
                        $count = 0;
                        foreach ($oldUrls as $oldUrl) {
                            $oldUrlEscaped = str_replace('/', '\/', $oldUrl);
                            $newValueJson = str_replace($oldUrl, rtrim($newBaseUrl, '/'), $newValueJson, $c1);
                            $newValueJson = str_replace($oldUrlEscaped, str_replace('/', '\/', rtrim($newBaseUrl, '/')), $newValueJson, $c2);
                            $count += ($c1 + $c2);
                        }
                        if ($count > 0) {
                            $recordOccurrences += $count;
                            $newValue = json_decode($newValueJson, true);
                        }
                    } 
                    // Strings (text, html, urls)
                    elseif (is_string($originalValue)) {
                        $count = 0;
                        foreach ($oldUrls as $oldUrl) {
                            $newValue = str_replace($oldUrl, rtrim($newBaseUrl, '/'), $newValue, $c);
                            $count += $c;
                        }
                        if ($count > 0) {
                            $recordOccurrences += $count;
                        }
                    }

                    if ($recordOccurrences > 0 && !$job->dry_run) {
                        $record->{$field} = $newValue;
                        $recordChanged = true;
                    }
                }

                if ($recordOccurrences > 0) {
                    $replacedOccurrences += $recordOccurrences;
                    $replacedRecords++;
                    if (!$job->dry_run && $recordChanged) {
                        $record->saveQuietly();
                    }
                }
            }
        }

        $job->update([
            'replaced_records' => $replacedRecords,
            'replaced_occurrences' => $replacedOccurrences,
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    }
}
