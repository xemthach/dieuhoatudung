<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CatalogSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogResolveSourcesCommand extends Command
{
    protected $signature = 'catalog:resolve-sources
        {--apply : Persist inferred brand assignments}
        {--report : Write JSON report}';

    protected $description = 'Infer and resolve catalog source brand metadata to reduce ambiguous product matching.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $updated = 0;

        $brands = Brand::query()->select('id', 'name', 'slug')->get();

        foreach (CatalogSource::query()->get() as $source) {
            $inferredBrandId = $this->inferBrandId(
                (string) $source->source_name.' '.(string) $source->uploaded_file,
                $brands->all(),
            );

            $action = 'keep';
            if (! $source->brand_id && $inferredBrandId) {
                $action = $apply ? 'updated' : 'would_update';
                if ($apply) {
                    $source->update(['brand_id' => $inferredBrandId]);
                    $updated++;
                }
            }

            $rows[] = [
                'catalog_source_id' => $source->id,
                'source_name' => $source->source_name,
                'source_type' => $source->source_type,
                'uploaded_file' => $source->uploaded_file,
                'brand_id_before' => $source->brand_id,
                'brand_id_inferred' => $inferredBrandId,
                'action' => $action,
            ];
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total sources', count($rows)],
                ['Updated', $updated],
                ['Apply mode', $apply ? 'yes' : 'no (dry-run)'],
            ]
        );

        if ($this->option('report')) {
            $path = 'private/reports/catalog-resolve-sources-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'apply' => $apply,
                'updated' => $updated,
                'rows' => $rows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }

    private function inferBrandId(string $haystack, array $brands): ?int
    {
        $haystack = Str::ascii(Str::lower($haystack));

        foreach ($brands as $brand) {
            $slug = Str::ascii(Str::lower((string) ($brand->slug ?? '')));
            $name = Str::ascii(Str::lower((string) ($brand->name ?? '')));

            if (($slug !== '' && Str::contains($haystack, $slug)) || ($name !== '' && Str::contains($haystack, $name))) {
                return (int) $brand->id;
            }
        }

        return null;
    }
}

