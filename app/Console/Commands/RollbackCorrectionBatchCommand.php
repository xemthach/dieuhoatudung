<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollbackCorrectionBatchCommand extends Command
{
    protected $signature = 'catalog:rollback-correction-batch
        {--batch= : Applied batch UUID}
        {--apply : Execute rollback; without this the command is dry-run}
        {--approved : Explicit approval required for rollback}';

    protected $description = 'Dry-run or restore a guarded catalog correction snapshot.';

    public function handle(): int
    {
        $batch = (string) $this->option('batch');
        if ($batch === '') return $this->abortCommand('Missing --batch option.');
        if ($this->option('apply') && ! $this->option('approved')) return $this->abortCommand('Blocked: --apply requires --approved.');
        if ($this->option('apply') && ! $this->isPhase1FClone()) return $this->abortCommand('Safety abort: rollback is allowed only on a Phase 1F clone.');
        $snapshotPath = storage_path('app/private/reports/phase1f1_snapshot_'.$batch.'.json');
        $auditPath = storage_path('app/private/reports/phase1f1_audit_'.$batch.'.json');
        if (! is_file($snapshotPath) || ! is_file($auditPath)) return $this->abortCommand('Snapshot or audit log missing.');
        $audit = json_decode((string) file_get_contents($auditPath), true);
        if (($audit['status'] ?? '') !== 'applied') return $this->abortCommand('Batch is not in applied state.');
        $snapshot = json_decode((string) file_get_contents($snapshotPath), true);
        if (! is_array($snapshot) || empty($snapshot['products'])) return $this->abortCommand('Invalid snapshot.');
        if (! $this->option('apply')) { $this->info('DRY-RUN PASS; would restore '.count($snapshot['products']).' products and one source.'); return self::SUCCESS; }
        try {
            DB::transaction(function () use ($snapshot): void {
                $source = $snapshot['source'];
                DB::table('catalog_sources')->where('id', $source['id'])->update(collect($source)->except(['id'])->all());
                foreach ($snapshot['products'] as $product) DB::table('products')->where('id', $product['id'])->update(collect($product)->except(['id'])->all());
            });
        } catch (\Throwable $e) { return $this->abortCommand('Rollback transaction failed: '.$e->getMessage()); }
        $audit['status'] = 'rolled_back'; $audit['rolled_back_at'] = now()->toIso8601String();
        file_put_contents($auditPath, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Correction batch rolled back: '.$batch);
        return self::SUCCESS;
    }
    private function isPhase1FClone(): bool
    {
        $database = (string) config('database.connections.mysql.database');
        return str_starts_with($database, 'dieuhoatudung_phase1f_apply_test_')
            || str_starts_with($database, 'dieuhoatudung_phase1f_final_')
            || str_starts_with($database, 'dieuhoatudung_phase1f_safe_');
    }
    private function abortCommand(string $message): int { $this->error($message); return self::FAILURE; }
}
