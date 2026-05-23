<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\HVAC\HVACTechnicalNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BuildOfficialGreeSyncPayloadCommand extends Command
{
    protected $signature = 'products:build-official-gree-payload
        {--source=storage/catalogs/verified_pdf_extract_gree.json}
        {--batch= : Batch ID}
        {--min-confidence=0.85}
        {--output= : Output JSON under storage/app/private/catalogs}';

    protected $description = 'Build sync payload for GREE from official LAC 2025 source rows with safe unique containment matching.';

    public function handle(HVACTechnicalNormalizer $normalizer): int
    {
        $source = (string) $this->option('source');
        $sourcePath = str_starts_with($source, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $source)
            ? $source
            : base_path($source);
        if (! is_file($sourcePath)) {
            $this->error("Source not found: {$sourcePath}");
            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($sourcePath), true);
        if (! is_array($rows)) {
            $this->error('Invalid source JSON.');
            return self::FAILURE;
        }

        $batch = trim((string) $this->option('batch'));
        if ($batch === '') {
            $batch = 'gree_official_'.now()->format('Ymd_His');
        }
        $minConfidence = (float) $this->option('min-confidence');
        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = "private/catalogs/sync_payload_{$batch}.json";
        }

        $greeProducts = Product::query()
            ->where(function ($q): void {
                $q->whereHas('brand', fn ($b) => $b->where('name', 'GREE')->orWhere('slug', 'gree'))
                    ->orWhere('name', 'like', '%Gree%')
                    ->orWhere('model_code', 'like', '%G%')
                    ->orWhere('sku', 'like', '%G%');
            })
            ->get(['id', 'name', 'sku', 'model_code']);

        $payload = [];
        $direct = 0;
        $uniqueContain = 0;
        $skippedAmbiguousContain = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $firstSourceFile = '';
            foreach ($row as $k => $v) {
                if (is_string($k) && str_ends_with($k, '__source_file') && is_string($v) && $v !== '') {
                    $firstSourceFile = strtolower($v);
                    break;
                }
            }
            if (! str_contains($firstSourceFile, 'e-catalogue lac 2025.pdf')) {
                continue;
            }

            $model = trim((string) ($row['model'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($model === '' && $sku === '') {
                continue;
            }

            $normModel = $normalizer->normalizeModel($model);
            $normSku = $normalizer->normalizeSku($sku);

            $matchedProduct = Product::query()
                ->where('model_code', $model)
                ->orWhere('sku', $sku)
                ->first();

            if (! $matchedProduct && ($normModel !== '' || $normSku !== '')) {
                $matchedProduct = $greeProducts->first(function (Product $p) use ($normalizer, $normModel, $normSku): bool {
                    $pm = $normalizer->normalizeModel((string) $p->model_code);
                    $ps = $normalizer->normalizeSku((string) $p->sku);
                    return ($normModel !== '' && ($pm === $normModel || $ps === $normModel))
                        || ($normSku !== '' && ($pm === $normSku || $ps === $normSku));
                });
            }

            if ($matchedProduct) {
                $direct++;
            } else {
                $needle = $normModel !== '' ? $normModel : $normSku;
                if ($needle === '') {
                    continue;
                }
                $candidates = $greeProducts->filter(function (Product $p) use ($normalizer, $needle): bool {
                    $pm = $normalizer->normalizeModel((string) $p->model_code);
                    $ps = $normalizer->normalizeSku((string) $p->sku);
                    return str_contains($pm, $needle) || str_contains($ps, $needle);
                })->values();

                if ($candidates->count() === 1) {
                    $matchedProduct = $candidates->first();
                    $uniqueContain++;
                } elseif ($candidates->count() > 1) {
                    $skippedAmbiguousContain++;
                    continue;
                } else {
                    continue;
                }
            }

            $out = [
                'model' => (string) $matchedProduct->model_code,
                'sku' => (string) $matchedProduct->sku,
            ];
            $valid = 0;
            foreach ($row as $k => $v) {
                if (! is_string($k) || str_contains($k, '__') || in_array($k, ['model', 'sku'], true)) {
                    continue;
                }
                $val = trim((string) $v);
                if ($val === '') {
                    continue;
                }
                $sf = $row[$k.'__source_file'] ?? null;
                $st = $row[$k.'__source_text'] ?? null;
                $sp = $row[$k.'__source_page'] ?? null;
                $cf = (float) ($row[$k.'__confidence'] ?? 0);
                if (! $sf || ! $st || $cf < $minConfidence) {
                    continue;
                }
                $out[$k] = $val;
                $out[$k.'__source_file'] = $sf;
                $out[$k.'__source_page'] = $sp;
                $out[$k.'__source_text'] = $st;
                $out[$k.'__confidence'] = round($cf, 4);
                $valid++;
            }
            if ($valid > 0) {
                $payload[] = $out;
            }
        }

        Storage::disk('local')->put($output, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $this->table(['Metric', 'Count'], [
            ['Payload rows', count($payload)],
            ['Direct/normalized match', $direct],
            ['Unique containment match', $uniqueContain],
            ['Skipped ambiguous containment', $skippedAmbiguousContain],
        ]);
        $this->info('Payload written: '.storage_path('app/'.$output));
        $this->info('Batch: '.$batch);

        return self::SUCCESS;
    }
}

