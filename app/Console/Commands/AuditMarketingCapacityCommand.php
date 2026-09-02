<?php

namespace App\Console\Commands;

use App\Services\Product\ProductMarketingCapacityAuditService;
use Illuminate\Console\Command;

class AuditMarketingCapacityCommand extends Command
{
    protected $signature = 'catalog:audit-marketing-capacity {--json : Emit machine-readable read-only results} {--product= : Audit one Product ID only}';

    protected $description = 'Read-only audit of Product marketing and technical BTU storage; never writes Product data.';

    public function handle(ProductMarketingCapacityAuditService $audit): int
    {
        $productId = $this->option('product');
        $result = $audit->audit($productId !== null && ctype_digit((string) $productId) ? (int) $productId : null);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Metric', 'Count'], collect($result['stats'])->map(fn (int $count, string $metric) => [$metric, $count])->all());
            $this->warn('No Product data was changed. Review candidates require an approved, provenance-backed correction manifest.');
        }

        return self::SUCCESS;
    }
}
