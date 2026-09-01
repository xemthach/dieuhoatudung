<?php

namespace App\Services\AI;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use Illuminate\Support\Facades\DB;

final class AiProductIntegrityAuditor
{
    /** @return array{summary:array<string,int>,violations:array<int,array<string,mixed>>} */
    public function audit(): array
    {
        $violations = [];
        $this->appendOrphans($violations);
        $this->appendParentMismatches($violations);
        $this->appendDraftMismatches($violations);
        $this->appendItemMismatches($violations);
        $this->appendQueueCorrelationMismatches($violations);

        return [
            'summary' => [
                'violations' => count($violations),
                'unknown' => count(array_filter($violations, fn (array $row): bool => ($row['classification'] ?? '') === 'UNKNOWN')),
            ],
            'violations' => $violations,
        ];
    }

    private function appendOrphans(array &$violations): void
    {
        foreach (AiProductJobItem::query()->whereDoesntHave('job')->pluck('id') as $id) $violations[] = $this->row('ITEM_WITHOUT_JOB', 'item', $id);
        foreach (AiProductJobItem::query()->whereDoesntHave('product')->pluck('id') as $id) $violations[] = $this->row('ITEM_WITHOUT_PRODUCT', 'item', $id);
        foreach (AiProductDraft::query()->whereDoesntHave('product')->pluck('id') as $id) $violations[] = $this->row('DRAFT_WITHOUT_PRODUCT', 'draft', $id);
    }

    private function appendParentMismatches(array &$violations): void
    {
        AiProductJob::query()->with('items')->chunkById(100, function ($jobs) use (&$violations): void {
            foreach ($jobs as $job) {
                $active = $job->items->filter(fn (AiProductJobItem $item): bool =>
                    app(AiProductStateCompatibility::class)->isActive(app(AiProductStateCompatibility::class)->item($item)['status'])
                );
                if ($active->isEmpty() && in_array((string) $job->canonical_status, AiProductStateCompatibility::ACTIVE, true)) {
                    $violations[] = $this->row('PARENT_ACTIVE_WITHOUT_ACTIVE_CHILD', 'job', $job->id);
                }
                if ($active->isNotEmpty() && $job->finished_at) {
                    $violations[] = $this->row('ACTIVE_PARENT_HAS_FINISHED_AT', 'job', $job->id);
                }
            }
        });
    }

    private function appendDraftMismatches(array &$violations): void
    {
        $groups = AiProductDraft::query()
            ->select('product_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNull('applied_at')
            ->where(function ($query): void {
                $query->where(function ($review): void {
                    $review->where('approval_status', 'REVIEW_REQUIRED')->whereIn('status', ['needs_review', 'REVIEW_REQUIRED']);
                })->orWhere('approval_status', 'APPROVED_FOR_APPLY');
            })
            ->groupBy('product_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $group) $violations[] = $this->row('MULTIPLE_ACTIONABLE_DRAFTS', 'product', $group->product_id, ['count' => (int) $group->aggregate]);

        foreach (AiProductDraft::query()->where('approval_status', 'APPROVED_FOR_APPLY')->whereNull('approved_content_hash')->pluck('id') as $id) {
            $violations[] = $this->row('APPROVED_DRAFT_MISSING_CONTENT_HASH', 'draft', $id);
        }
    }

    private function appendItemMismatches(array &$violations): void
    {
        AiProductJobItem::query()->chunkById(200, function ($items) use (&$violations): void {
            foreach ($items as $item) {
                $state = app(AiProductStateCompatibility::class)->item($item);
                if ($state['violation']) {
                    $violations[] = $this->row($state['violation'], 'item', $item->id, [
                        'canonical' => $state['canonical'], 'legacy' => $state['legacy'],
                    ]);
                }
            }
        });
    }

    private function appendQueueCorrelationMismatches(array &$violations): void
    {
        AiProductJobItem::query()->chunkById(200, function ($items) use (&$violations): void {
            foreach ($items as $item) {
                $state = app(AiProductStateCompatibility::class)->item($item)['status'];
                if (! app(AiProductStateCompatibility::class)->isActive($state)) continue;

                if (! $item->dispatch_uuid) {
                    $violations[] = $this->row('ACTIVE_ITEM_MISSING_DISPATCH_UUID', 'item', $item->id);
                    continue;
                }

                $queuedPayloads = DB::table('jobs')
                    ->where('queue', config('ai.governed_queue', 'ai_governed'))
                    ->where('payload', 'like', '%'.$item->dispatch_uuid.'%')
                    ->count();
                if ($queuedPayloads > 1) {
                    $violations[] = $this->row('MULTIPLE_QUEUE_PAYLOADS_FOR_ACTIVE_ITEM', 'item', $item->id, [
                        'dispatch_uuid' => $item->dispatch_uuid,
                        'queue_payloads' => $queuedPayloads,
                    ]);
                }

                if (app(AiProductLifecycleService::class)->isRecoverable($item)) {
                    $violations[] = $this->row('RECOVERABLE_STALE_ACTIVE_ITEM', 'item', $item->id, [
                        'dispatch_uuid' => $item->dispatch_uuid,
                        'queue_payloads' => $queuedPayloads,
                    ]);
                }
            }
        });
    }

    private function row(string $code, string $entity, int|string $id, array $context = []): array
    {
        return ['code' => $code, 'entity' => $entity, 'id' => $id, 'classification' => 'KNOWN'] + $context;
    }
}
