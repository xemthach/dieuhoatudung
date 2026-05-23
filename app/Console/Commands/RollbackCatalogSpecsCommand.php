<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSpecsSnapshot;
use Illuminate\Console\Command;

class RollbackCatalogSpecsCommand extends Command
{
    protected $signature = 'products:rollback-catalog-specs {--batch= : Snapshot batch identifier in reason}';

    protected $description = 'Rollback product specs from snapshots by batch marker.';

    public function handle(): int
    {
        $batch = (string) $this->option('batch');
        if ($batch === '') {
            $this->error('Missing --batch option.');

            return self::FAILURE;
        }

        $snapshots = ProductSpecsSnapshot::query()
            ->where('reason', 'catalog_sync_batch:'.$batch)
            ->orderByDesc('id')
            ->get();

        $count = $snapshots->count();
        if ($count === 0) {
            $this->warn('No snapshots found for batch: '.$batch);

            return self::SUCCESS;
        }

        $restored = 0;
        foreach ($snapshots as $snapshot) {
            $payload = is_array($snapshot->snapshot_json) ? $snapshot->snapshot_json : [];
            $old = $payload['old_specs'] ?? null;
            if (! is_array($old)) {
                continue;
            }

            $product = Product::query()->find($snapshot->product_id);
            if (! $product) {
                continue;
            }

            $product->update([
                'btu' => $old['btu'] ?? null,
                'refrigerant_gas' => $old['refrigerant_gas'] ?? null,
                'voltage' => $old['voltage'] ?? null,
                'airflow' => $old['airflow'] ?? null,
                'noise_level' => $old['noise_level'] ?? null,
                'indoor_dimensions' => $old['indoor_dimensions'] ?? null,
                'outdoor_dimensions' => $old['outdoor_dimensions'] ?? null,
                'specs_json' => $old['specs_json'] ?? [],
                'technical_specs_source' => null,
            ]);
            $restored++;
        }

        $this->info("Rollback completed. snapshots={$count}, restored={$restored}");

        return self::SUCCESS;
    }
}
