<?php

namespace App\Services\Product;

use Illuminate\Support\Facades\Schema;

final class ProductGovernanceHealth
{
    /** Diagnostics only: never generates evidence or changes approvals. */
    public function inspect(): array
    {
        $issues = [];
        foreach (['products', 'product_categories', 'product_data_governance_events'] as $table) {
            if (! Schema::hasTable($table)) $issues[] = ['code'=>'SCHEMA_MISSING', 'resource'=>$table];
        }
        foreach (['taxonomy_status', 'marketing_capacity_status', 'technical_capacity_btu'] as $column) {
            if (! Schema::hasColumn('products', $column)) $issues[] = ['code'=>'SCHEMA_MISSING', 'resource'=>'products.'.$column];
        }
        $files = [
            'coverage'=>(string) config('catalog_evidence.coverage_path'),
            'gree_sections'=>(string) config('catalog_evidence.gree_sections_path'),
            'skyair_matrix'=>resource_path('catalog/skyair_combination_matrix.csv'),
        ];
        foreach ($files as $name => $file) {
            if (! is_file($file) || ! is_readable($file)) {
                $issues[] = ['code'=>'EVIDENCE_MISSING', 'resource'=>$name];
                continue;
            }
            if ($name === 'skyair_matrix') continue;
            try {
                $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($data) || ! is_array($data[$name === 'coverage' ? 'rows' : 'documents'] ?? null)) {
                    throw new \RuntimeException('Invalid evidence shape.');
                }
                if ($name === 'gree_sections') {
                    foreach ($data['documents'] as $document) {
                        $path = base_path($document['relative_path'] ?? '');
                        if (! is_file($path)) $issues[] = ['code'=>'EVIDENCE_MISSING', 'resource'=>'catalogue_source'];
                        elseif (! hash_equals(strtolower((string) ($document['sha256'] ?? '')), hash_file('sha256', $path))) {
                            $issues[] = ['code'=>'EVIDENCE_MISMATCH', 'resource'=>'catalogue_source'];
                        }
                    }
                }
            } catch (\Throwable) {
                $issues[] = ['code'=>'EVIDENCE_MISMATCH', 'resource'=>$name];
            }
        }

        return ['ready'=>$issues === [], 'status'=>$issues === [] ? 'READY' : $issues[0]['code'], 'issues'=>$issues];
    }
}
