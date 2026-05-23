<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Services\HVAC\HVACTechnicalNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CatalogExtractPdfModelsCommand extends Command
{
    protected $signature = 'catalog:extract-pdf-models
        {--dir=data dieu hoa : Base directory containing internal catalog PDFs}
        {--apply : Persist extracted model identities}
        {--report : Write JSON report}
        {--max-files=0 : Limit number of pdf files (0 = no limit)}';

    protected $description = 'Extract model identities from internal PDF catalog files (no external sources).';

    public function handle(HVACTechnicalNormalizer $normalizer): int
    {
        $baseDir = base_path((string) $this->option('dir'));
        if (! is_dir($baseDir)) {
            $this->error("Directory not found: {$baseDir}");

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $maxFiles = max(0, (int) $this->option('max-files'));

        $pdfs = collect((array) glob($baseDir.'/**/*.pdf', GLOB_BRACE))
            ->merge((array) glob($baseDir.'/*.pdf'))
            ->unique()
            ->values();

        if ($maxFiles > 0) {
            $pdfs = $pdfs->take($maxFiles)->values();
        }

        $results = [];
        $modelsTotal = 0;

        foreach ($pdfs as $pdf) {
            $lines = $this->extractLines((string) $pdf);
            if ($lines === []) {
                continue;
            }

            $tokens = $this->extractModelTokens($lines);
            if ($tokens === []) {
                continue;
            }

            $modelsTotal += count($tokens);
            $source = null;

            if ($apply) {
                $source = CatalogSource::query()->firstOrCreate(
                    ['uploaded_file' => str_replace('\\', '/', (string) $pdf)],
                    [
                        'source_name' => basename((string) $pdf),
                        'source_type' => 'pdf',
                        'version' => date('Y-m-d'),
                        'parsed_status' => 'parsed',
                        'imported_status' => 'pending',
                    ]
                );

                foreach ($tokens as $token) {
                    $normalized = $normalizer->normalizeModel($token);
                    if ($normalized === '') {
                        continue;
                    }

                    $exists = CatalogModel::query()
                        ->where('catalog_source_id', $source->id)
                        ->where('normalized_model', $normalized)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    CatalogModel::query()->create([
                        'catalog_source_id' => $source->id,
                        'model' => $token,
                        'sku' => str_replace('/', '-', $token),
                        'normalized_model' => $normalized,
                        'normalized_sku' => $normalizer->normalizeSku(str_replace('/', '-', $token)),
                        'technical_data_json' => null,
                        'source_page' => null,
                        'confidence_score' => 0.85,
                        'import_status' => 'parsed',
                    ]);
                }
            }

            $results[] = [
                'file' => str_replace('\\', '/', (string) $pdf),
                'models_found' => count($tokens),
                'sample_models' => array_slice($tokens, 0, 10),
                'catalog_source_id' => $source?->id,
            ];
        }

        $this->table(['Metric', 'Count'], [
            ['PDF files processed', count($results)],
            ['Model identities extracted', $modelsTotal],
            ['Apply mode', $apply ? 'yes' : 'no (dry-run)'],
        ]);

        if ($this->option('report')) {
            $path = storage_path('app/private/reports/catalog-extract-pdf-models-'.now()->format('Ymd_His').'.json');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'apply' => $apply,
                'results' => $results,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info("Report written: {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int,string>
     */
    private function extractLines(string $pdf): array
    {
        $cmd = 'pdftotext -enc UTF-8 -layout '.escapeshellarg($pdf).' -';
        $output = shell_exec($cmd);
        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $output) ?: [])));
    }

    /**
     * @param  array<int,string>  $lines
     * @return array<int,string>
     */
    private function extractModelTokens(array $lines): array
    {
        $tokens = [];
        foreach ($lines as $line) {
            if (mb_strlen($line) > 180) {
                continue;
            }

            if (! preg_match_all('/\b[A-Z0-9]{2,}(?:[\/-][A-Z0-9]{1,}){1,5}\b/u', Str::upper($line), $matches)) {
                continue;
            }

            foreach ($matches[0] as $token) {
                $token = trim((string) $token);
                if (strlen($token) < 6) {
                    continue;
                }
                if (preg_match('/^(?:\d+\/\d+|[A-Z]{1,3}-\d{1,2})$/', $token)) {
                    continue;
                }
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }
}

