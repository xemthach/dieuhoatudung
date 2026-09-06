<?php

namespace App\Services\Product;

use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProductDataApprovalService
{
    public const TAXONOMY = 'TAXONOMY';
    public const MARKETING_CAPACITY = 'MARKETING_CAPACITY';

    public function approveTaxonomy(string $ledgerDigest, array $rows, string $reason, User $actor): ProductDataGovernanceEvent
    {
        $this->authorize($actor, 'product_taxonomy.approve');
        $ids = collect($rows)->whereIn('classification', ['KEEP', 'PROPOSE_UPDATE'])
            ->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        if ($ids === [] || ! preg_match('/^[a-f0-9]{64}$/', $ledgerDigest)) {
            throw new InvalidArgumentException('A valid non-empty taxonomy ledger is required.');
        }

        $sourceDigest = hash('sha256', json_encode(collect($rows)->whereIn('classification', ['KEEP', 'PROPOSE_UPDATE'])
            ->map(fn (array $row): array => [
                'product_id' => (int) $row['product_id'],
                'source' => $row['evidence_source'],
                'location' => $row['evidence_location'],
            ])->sortBy('product_id')->values()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $this->record(self::TAXONOMY, 'APPROVE', $ledgerDigest, $sourceDigest, null, $ids, [
            'classification' => ['KEEP', 'PROPOSE_UPDATE'],
            'row_count' => count($ids),
        ], $reason, $actor);
    }

    public function approveMarketingGroup(array $group, int $capacity, string $reason, User $actor): ProductDataGovernanceEvent
    {
        $this->authorize($actor, 'marketing_capacity.approve');
        if (! ProductFilterService::isAllowedMarketingTier($capacity)) {
            throw new InvalidArgumentException('Marketing tier is outside the approved public filter contract.');
        }
        $ids = collect($group['product_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all();
        if ($ids === []) throw new InvalidArgumentException('Mapping group is empty.');
        $sourceDigest = (string) ($group['source_digest'] ?? '');
        $mappingDigest = hash('sha256', json_encode([
            'group_key' => $group['group_key'],
            'product_ids' => $ids,
            'source_digest' => $sourceDigest,
            'taxonomy' => $group['taxonomy'],
            'marketing_capacity_btu' => $capacity,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $this->record(self::MARKETING_CAPACITY, 'APPROVE', (string) $group['ledger_digest'], $sourceDigest, $mappingDigest, $ids, [
            'group_key' => $group['group_key'],
            'taxonomy' => $group['taxonomy'],
            'marketing_capacity_btu' => $capacity,
            'source' => $group['source'],
            'technical_capacity_btu' => $group['technical_capacity_btu'],
            'capacity_kw' => $group['capacity_kw'],
        ], $reason, $actor);
    }

    public function recordReviewAction(string $domain, string $action, string $ledgerDigest, array $ids, string $reason, User $actor, array $descriptor = []): ProductDataGovernanceEvent
    {
        $permission = $domain === self::TAXONOMY ? 'product_taxonomy.approve' : 'marketing_capacity.approve';
        $this->authorize($actor, $permission);
        if (! in_array($action, ['REJECT', 'MARK_REVIEW', 'REVOKE'], true)) throw new InvalidArgumentException('Invalid review action.');

        return $this->record($domain, $action, $ledgerDigest, null, $descriptor['mapping_digest'] ?? null, $ids, $descriptor, $reason, $actor);
    }

    public function taxonomyLedgerIsApproved(string $digest): bool
    {
        $event = ProductDataGovernanceEvent::query()->where('domain', self::TAXONOMY)
            ->where('ledger_digest', $digest)
            ->whereIn('action', ['APPROVE', 'REVOKE'])
            ->latest('id')->first();

        return $event?->action === 'APPROVE';
    }

    /** @return list<int> */
    public function taxonomyProductsExcludedAfterApproval(string $digest): array
    {
        $approval = ProductDataGovernanceEvent::query()->where('domain', self::TAXONOMY)
            ->where('ledger_digest', $digest)->where('action', 'APPROVE')->latest('id')->first();
        if (! $approval) return [];

        return ProductDataGovernanceEvent::query()->where('domain', self::TAXONOMY)
            ->where('ledger_digest', $digest)->where('id', '>', $approval->id)
            ->whereIn('action', ['REJECT', 'MARK_REVIEW'])
            ->orderBy('id')->get()
            ->flatMap(fn (ProductDataGovernanceEvent $event): array => $event->product_ids_json ?: [])
            ->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /** @return array<int, array{capacity:int,mapping_digest:string,ledger_digest:string,source_digest:?string,event_id:int,group_key:?string}> */
    public function approvedMarketingMappings(): array
    {
        $latest = ProductDataGovernanceEvent::query()->where('domain', self::MARKETING_CAPACITY)
            ->orderBy('id')->get()->groupBy('mapping_digest')->map->last()
            ->filter(fn (ProductDataGovernanceEvent $event): bool => $event->action === 'APPROVE');
        $mappings = [];
        foreach ($latest as $event) {
            foreach ($event->product_ids_json as $id) {
                $id = (int) $id;
                if (isset($mappings[$id])) {
                    $mappings[$id] = ['conflict' => true];
                    continue;
                }
                $mappings[$id] = [
                    'capacity' => (int) data_get($event->descriptor_json, 'marketing_capacity_btu'),
                    'mapping_digest' => (string) $event->mapping_digest,
                    'ledger_digest' => (string) $event->ledger_digest,
                    'source_digest' => $event->source_digest,
                    'event_id' => $event->id,
                    'group_key' => data_get($event->descriptor_json, 'group_key'),
                ];
            }
        }

        return $mappings;
    }

    private function record(string $domain, string $action, string $ledgerDigest, ?string $sourceDigest, ?string $mappingDigest, array $ids, array $descriptor, string $reason, User $actor): ProductDataGovernanceEvent
    {
        if (trim($reason) === '') throw new InvalidArgumentException('Approval/review reason is required.');

        return DB::transaction(function () use ($domain, $action, $ledgerDigest, $sourceDigest, $mappingDigest, $ids, $descriptor, $reason, $actor): ProductDataGovernanceEvent {
            $previous = ProductDataGovernanceEvent::query()->where('domain', $domain)
                ->where('ledger_digest', $ledgerDigest)
                ->when($mappingDigest, fn ($query) => $query->where('mapping_digest', $mappingDigest))
                ->latest('id')->first();

            return ProductDataGovernanceEvent::query()->create([
                'domain' => $domain,
                'action' => $action,
                'scope_type' => count($ids) === 1 ? 'PRODUCT' : 'GROUP',
                'batch_uuid' => (string) Str::uuid(),
                'ledger_digest' => $ledgerDigest,
                'source_digest' => $sourceDigest ?: null,
                'mapping_digest' => $mappingDigest,
                'product_ids_json' => array_values($ids),
                'descriptor_json' => $descriptor,
                'before_json' => [
                    'action' => $previous?->action ?? 'UNREVIEWED',
                    'event_id' => $previous?->id,
                ],
                'after_json' => ['action' => $action, 'descriptor' => $descriptor],
                'reason' => trim($reason),
                'operator_id' => $actor->id,
                'occurred_at' => now(),
            ]);
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless($actor->isSuperAdmin() || $actor->can($permission), 403);
    }
}
