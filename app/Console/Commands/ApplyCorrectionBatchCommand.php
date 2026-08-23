<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Services\Product\ProductTechnicalSpecWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ApplyCorrectionBatchCommand extends Command
{
    protected $signature = 'catalog:apply-correction-batch
        {--batch= : Explicit batch UUID}
        {--manifest= : Approved JSON manifest path}
        {--apply : Execute writes; without this the command is dry-run}
        {--approved : Explicit approval required for writes}';

    protected $description = 'Dry-run or apply a provenance-backed catalog correction manifest.';

    public function handle(ProductTechnicalFactResolver $resolver, ProductTechnicalSpecWriter $writer): int
    {
        $batch = (string) $this->option('batch');
        if ($batch === '') return $this->abortCommand('Missing --batch option.');
        if ($this->option('apply') && ! $this->option('approved')) return $this->abortCommand('Blocked: --apply requires --approved.');
        $manifestPath = $this->option('manifest') ?: storage_path('app/private/reports/phase1f1_batch_'.$batch.'.json');
        if (! is_file($manifestPath)) return $this->abortCommand('Manifest not found: '.$manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ($manifest['batch_uuid'] ?? '') !== $batch) return $this->abortCommand('Manifest batch UUID mismatch.');
        if (($manifest['approval_status'] ?? '') !== 'approved') return $this->abortCommand('Manifest is not approved.');
        if (($manifest['source_pdf_sha256'] ?? '') !== 'C5A89D8559C359009431BD48D869DF4947154DDD6DA466F0DD40B6DF66041AC6') return $this->abortCommand('Source PDF checksum mismatch.');
        if ($this->option('apply') && ! $this->isApplyTargetAllowed($batch)) return $this->abortCommand('Safety abort: apply target is not an approved Phase 1F clone or an explicitly named Phase 1G current-DB batch.');

        try {
            $this->preflight($manifest, $resolver);
        } catch (RuntimeException $e) {
            return $this->abortCommand($e->getMessage());
        }
        $this->info($this->option('apply') ? 'Preflight PASS; APPLY mode requested.' : 'DRY-RUN PASS; no database write performed.');
        $this->line('Products: '.count($manifest['products'] ?? []).'; technical fields: '.count($manifest['technical_corrections'] ?? []));
        if (! $this->option('apply')) return self::SUCCESS;

        $snapshot = $this->snapshot($manifest);
        $snapshotPath = storage_path('app/private/reports/phase1f1_snapshot_'.$batch.'.json');
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $audit = ['batch_uuid' => $batch, 'status' => 'applying', 'source_pdf' => $manifest['source_pdf'] ?? '', 'source_sha256' => $manifest['source_pdf_sha256'], 'items' => [], 'snapshot' => $snapshotPath, 'started_at' => now()->toIso8601String()];
        try {
            DB::transaction(function () use ($manifest, $writer, &$audit): void {
                $this->applySource($manifest['source'] ?? [], $audit);
                foreach ($manifest['brand_corrections'] ?? [] as $item) $this->applyBrand($item, $audit);
                foreach ($manifest['capacity_corrections'] ?? [] as $item) $this->applyCapacity($item, $audit);
                foreach ($manifest['technical_corrections'] ?? [] as $item) {
                    $product = Product::query()->findOrFail((int) $item['product_id']);
                    $result = $writer->write($product, (string) $item['field'], $item['after_value'], $item['provenance']);
                    $audit['items'][] = ['product_id' => $product->id, 'field' => $item['field'], 'before' => $result['before'], 'after' => $result['after'], 'source_pdf' => $item['provenance']['source_pdf'], 'source_page' => $item['provenance']['source_page'], 'source_row' => $item['provenance']['source_row'], 'source_column' => $item['provenance']['source_column'], 'status' => 'applied', 'timestamp' => now()->toIso8601String()];
                }
            });
        } catch (\Throwable $e) {
            $audit['status'] = 'rolled_back_on_error'; $audit['error'] = $e->getMessage();
            file_put_contents(storage_path('app/private/reports/phase1f1_audit_'.$batch.'.json'), json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $this->abortCommand('Transaction rolled back: '.$e->getMessage());
        }
        $audit['status'] = 'applied'; $audit['finished_at'] = now()->toIso8601String();
        file_put_contents(storage_path('app/private/reports/phase1f1_audit_'.$batch.'.json'), json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Correction batch applied: '.$batch);
        return self::SUCCESS;
    }

    private function preflight(array $manifest, ProductTechnicalFactResolver $resolver): void
    {
        $source = $manifest['source'] ?? [];
        $currentSource = DB::table('catalog_sources')->where('id', (int) ($source['id'] ?? 0))->first();
        if (! $currentSource || (string) $currentSource->authority !== (string) ($source['before']['authority'] ?? '') || (string) $currentSource->source_status !== (string) ($source['before']['source_status'] ?? '')) throw new RuntimeException('STALE_BATCH: source precondition failed.');
        foreach ($manifest['products'] ?? [] as $item) {
            $product = Product::query()->find((int) $item['product_id']);
            if (! $product || (string) $product->model_code !== (string) $item['model']) throw new RuntimeException('STALE_BATCH: model mismatch for product '.$item['product_id']);
        }
        foreach ($manifest['brand_corrections'] ?? [] as $item) {
            $product = Product::query()->findOrFail((int) $item['product_id']);
            if ((int) $product->brand_id !== (int) $item['before_brand_id']) throw new RuntimeException('STALE_BATCH: brand precondition for product '.$item['product_id']);
        }
        foreach (array_merge($manifest['capacity_corrections'] ?? [], $manifest['technical_corrections'] ?? []) as $item) {
            $product = Product::query()->findOrFail((int) $item['product_id']);
            if (isset($item['before_marketing']) && (string) $product->marketing_capacity_btu !== (string) $item['before_marketing']) throw new RuntimeException('STALE_BATCH: marketing capacity for product '.$item['product_id']);
            if (isset($item['before_technical']) && (string) $product->technical_capacity_btu !== (string) $item['before_technical']) throw new RuntimeException('STALE_BATCH: technical capacity for product '.$item['product_id']);
            if (isset($item['field']) && $this->normalize($resolver->value($product, $item['field'])) !== $this->normalize($item['before_value'])) throw new RuntimeException('STALE_BATCH: field '.$item['field'].' for product '.$item['product_id']);
        }
    }

    private function snapshot(array $manifest): array
    {
        $ids = collect($manifest['products'] ?? [])->pluck('product_id')->map(fn ($id) => (int) $id)->values();
        $products = DB::table('products')->whereIn('id', $ids)->get()->map(fn ($row) => (array) $row)->all();
        $source = DB::table('catalog_sources')->where('id', (int) $manifest['source']['id'])->first();
        $payload = ['batch_uuid' => $manifest['batch_uuid'], 'created_at' => now()->toIso8601String(), 'products' => $products, 'source' => (array) $source];
        $payload['checksum'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $payload;
    }

    private function applySource(array $item, array &$audit): void
    {
        DB::table('catalog_sources')->where('id', $item['id'])->update(['authority' => $item['after']['authority'], 'source_status' => $item['after']['source_status'], 'section_type' => $item['after']['section_type'], 'updated_at' => now()]);
        $audit['items'][] = ['scope' => 'catalog_sources', 'id' => $item['id'], 'field' => 'authority/source_status/section_type', 'before' => $item['before'], 'after' => $item['after'], 'status' => 'applied', 'timestamp' => now()->toIso8601String()];
    }

    private function applyBrand(array $item, array &$audit): void
    {
        DB::table('products')->where('id', $item['product_id'])->where('brand_id', $item['before_brand_id'])->update(['brand_id' => $item['after_brand_id'], 'updated_at' => now()]);
        $audit['items'][] = ['product_id' => $item['product_id'], 'field' => 'brand_id', 'before' => $item['before_brand_id'], 'after' => $item['after_brand_id'], 'status' => 'applied', 'timestamp' => now()->toIso8601String()];
    }

    private function applyCapacity(array $item, array &$audit): void
    {
        DB::table('products')->where('id', $item['product_id'])->update(['marketing_capacity_btu' => $item['after_marketing'], 'technical_capacity_btu' => $item['after_technical'], 'technical_capacity_status' => 'verified_candidate', 'updated_at' => now()]);
        $audit['items'][] = ['product_id' => $item['product_id'], 'field' => 'marketing_capacity_btu/technical_capacity_btu', 'before' => [$item['before_marketing'], $item['before_technical']], 'after' => [$item['after_marketing'], $item['after_technical']], 'status' => 'applied', 'timestamp' => now()->toIso8601String()];
    }

    private function normalize(mixed $value): string { return trim((string) $value); }
    private function isApplyTargetAllowed(string $batch): bool
    {
        $database = (string) config('database.connections.mysql.database');
        $clone = str_starts_with($database, 'dieuhoatudung_phase1f_apply_test_')
            || str_starts_with($database, 'dieuhoatudung_phase1f_final_')
            || str_starts_with($database, 'dieuhoatudung_phase1f_safe_')
            || str_starts_with($database, 'dieuhoatudung_phase1h_');
        $currentApproved = $database === 'dieuhoa-tudung'
            && str_starts_with($batch, 'phase1g_current_db_')
            && (bool) $this->option('approved');
        return $clone || $currentApproved;
    }
    private function abortCommand(string $message): int { $this->error($message); return self::FAILURE; }
}
