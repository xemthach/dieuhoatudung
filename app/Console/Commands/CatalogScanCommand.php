<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogFileScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogScanCommand extends Command
{
    protected $signature = 'catalog:scan
        {--report : Write JSON report}
        {--dir=* : Extra directories to scan}';

    protected $description = 'Scan internal project directories for catalog files (no external source).';

    public function handle(CatalogFileScanner $scanner): int
    {
        $files = $scanner->scan((array) $this->option('dir'));

        $this->info('Catalog scan completed.');
        $this->table(
            ['Brand', 'Type', 'Count'],
            $files
                ->groupBy(fn (array $row) => $row['brand'].'|'.$row['extension'])
                ->map(fn ($group, $key) => [explode('|', $key)[0], explode('|', $key)[1], $group->count()])
                ->values()
                ->sortByDesc(2)
                ->take(30)
                ->all()
        );

        if ($this->option('report')) {
            $payload = [
                'generated_at' => now()->toIso8601String(),
                'count' => $files->count(),
                'files' => $files->all(),
            ];

            $path = 'private/reports/catalog-scan-'.now()->format('Ymd_His').'.json';
            Storage::disk('local')->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $this->info('Report written: '.storage_path('app/'.$path));
        }

        return self::SUCCESS;
    }
}

