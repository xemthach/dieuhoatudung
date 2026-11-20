<?php

namespace App\Services\Product;

use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use Illuminate\Support\Str;

final class ProductTechnicalAudit
{
    /** Called inside the same transaction as the Product writes. */
    public function record(User $actor, array $ids, string $action, array $before, array $after,
        string $reason, array $descriptor = [], ?string $batchId = null): ProductDataGovernanceEvent
    {
        $roles = $actor->getRoleNames()->sort()->values()->all();

        return ProductDataGovernanceEvent::create([
            'domain' => 'TECHNICAL_MANUAL', 'action' => $action,
            'scope_type' => count($ids) === 1 ? 'PRODUCT' : 'GROUP',
            'batch_uuid' => $batchId ?? (string) Str::uuid(),
            'ledger_digest' => hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)),
            'product_ids_json' => array_values($ids),
            'descriptor_json' => $descriptor + [
                'operator_role' => implode(', ', $roles), 'operator_roles' => $roles,
                'provenance' => 'MANUAL_ADMIN',
                'fields_changed' => array_map('array_keys', $after),
            ],
            'before_json' => $before, 'after_json' => $after,
            'reason' => $reason, 'operator_id' => $actor->id, 'occurred_at' => now(),
        ]);
    }
}
