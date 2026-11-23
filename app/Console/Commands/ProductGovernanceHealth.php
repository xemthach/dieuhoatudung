<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ProductGovernanceHealth extends Command
{
    protected $signature = 'products:governance-health {--json}';
    protected $description = 'Read-only product governance schema and evidence readiness check.';

    public function handle(\App\Services\Product\ProductGovernanceHealth $health): int
    {
        $result = $health->inspect();
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
