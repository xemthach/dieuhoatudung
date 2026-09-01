<?php

namespace App\Console\Commands;

use App\Services\AI\AiProductIntegrityAuditor;
use Illuminate\Console\Command;

final class AiProductIntegrityAudit extends Command
{
    protected $signature = 'ai:product-integrity-audit {--json : Output JSON} {--csv : Output CSV}';
    protected $description = 'Read-only audit of AI Product lineage and state invariants.';

    public function handle(AiProductIntegrityAuditor $auditor): int
    {
        $result = $auditor->audit();
        if ($this->option('csv')) {
            $this->line('code,entity,id,classification,context');
            foreach ($result['violations'] as $row) {
                $context = $row;
                unset($context['code'], $context['entity'], $context['id'], $context['classification']);
                $this->line(implode(',', [
                    $this->csv($row['code']), $this->csv($row['entity']), $this->csv((string) $row['id']),
                    $this->csv($row['classification']), $this->csv(json_encode($context, JSON_UNESCAPED_UNICODE)),
                ]));
            }
        } else {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $result['summary']['unknown'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function csv(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
