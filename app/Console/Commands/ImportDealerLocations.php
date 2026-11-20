<?php

namespace App\Console\Commands;

use App\Models\AdministrativeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportDealerLocations extends Command
{
    protected $signature = 'dealer:import-locations {--apply : Import the pinned master data; default is dry-run}';

    protected $description = 'Validate checksums and import versioned administrative units without changing Dealers.';

    public function handle(): int
    {
        $directory = config('dealers.master_path', resource_path('geography'));
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $datasets = [];
        foreach (['current', 'legacy'] as $scheme) {
            $file = $directory.'/'.$scheme.'.json';
            if (! hash_equals($manifest[$scheme]['sha256'], hash_file('sha256', $file))) {
                throw new RuntimeException('Geography checksum mismatch.');
            }
            if ($manifest[$scheme]['version'] !== config('dealers.'.$scheme.'_version')) {
                throw new RuntimeException('Dataset version mismatch.');
            }
            $rows = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $seen = [];
            foreach ($rows as $row) {
                if (isset($seen[$row['code']]) || ! in_array($row['kind'], ['province', 'district', 'ward'], true)) {
                    throw new RuntimeException('Duplicate or invalid location.');
                }
                $expectedParent = $row['kind'] === 'district' || $scheme === 'current' ? 'province' : 'district';
                if ($row['kind'] !== 'province' && ($seen[$row['parent_code']] ?? null) !== $expectedParent) {
                    throw new RuntimeException('Invalid parent.');
                }
                $seen[$row['code']] = $row['kind'];
            }
            foreach ($manifest[$scheme]['counts'] as $kind => $count) {
                if (count(array_filter($seen, fn ($v) => $v === $kind)) !== $count) {
                    throw new RuntimeException('Location count mismatch.');
                }
            }
            $datasets[$scheme] = $rows;
            $this->line($scheme.': '.json_encode($manifest[$scheme]['counts']));
        }
        if (! $this->option('apply')) {
            $this->info('DRY_RUN: no database changes.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($datasets, $manifest) {
            foreach ($datasets as $scheme => $rows) {
                $ids = [];
                foreach (['province', 'district', 'ward'] as $kind) {
                    $batch = [];
                    foreach ($rows as $row) {
                        if ($row['kind'] !== $kind) {
                            continue;
                        }
                        $batch[] = [
                            'scheme' => $scheme, 'dataset_version' => $manifest[$scheme]['version'],
                            'code' => $row['code'], 'kind' => $kind, 'parent_id' => $row['parent_code'] === null ? null : $ids[$row['parent_code']],
                            'name' => $row['name'], 'full_name' => $row['full_name'], 'slug' => $row['slug'],
                            'created_at' => now(), 'updated_at' => now(),
                        ];
                    }
                    foreach (array_chunk($batch, 300) as $chunk) {
                        DB::table('administrative_units')->upsert($chunk, ['scheme', 'dataset_version', 'code'], ['name', 'full_name', 'parent_id', 'updated_at']);
                    }
                    $ids = AdministrativeUnit::where('scheme', $scheme)->where('dataset_version', $manifest[$scheme]['version'])->pluck('id', 'code')->all();
                }
            }
        });
        $this->info('Imported master data. Dealer rows unchanged.');

        return self::SUCCESS;
    }
}
