<?php

namespace App\Services\AI;

use App\Models\AiProductJob;
use App\Support\SchemaColumns;
use Illuminate\Support\Str;
use RuntimeException;

/** Persists and verifies the frozen generation target; never re-resolves UI scope. */
class ProductBulkGenerationManifest
{
    public function freeze(AiProductJob $job, string $scope, array $ids, int $createdBy, array $filterSnapshot = [], array $options = [], ?object $actor = null): array
    {
        if ($actor) {
            app(BulkRuntimeAuthorizationService::class)->requireGenerate($actor);
            if ($actor instanceof \App\Models\User && app(SingleOperatorControlledRolloutPolicy::class)->active()) {
                app(SingleOperatorControlledRolloutPolicy::class)->assertAction($actor, 'GENERATE');
                app(SingleOperatorControlledRolloutPolicy::class)->assertDraftOnly($options);
            }
        }
        if ($job->target_manifest_hash) throw new RuntimeException('MANIFEST_ALREADY_FROZEN');
        $resolver = app(ProductBulkTargetResolver::class);
        $manifest = $resolver->manifest($scope, $ids, $createdBy, $filterSnapshot, array_merge($options, [
            'batch_uuid' => (string) ($job->batch_uuid ?: Str::uuid()),
        ]));
        $job->update(SchemaColumns::existing('ai_product_jobs', [
            'batch_uuid' => $manifest['batch_uuid'],
            'scope_type' => $manifest['scope_type'],
            'target_manifest_json' => $manifest,
            'target_manifest_hash' => $manifest['target_manifest_hash'],
            'manifest_frozen_at' => now(),
        ]));
        return $manifest;
    }

    public function loadVerified(AiProductJob $job): array
    {
        $manifest = is_array($job->target_manifest_json) ? $job->target_manifest_json : [];
        if ($manifest === [] || ! app(ProductBulkTargetResolver::class)->verify($manifest)) {
            throw new RuntimeException('MANIFEST_TAMPERED');
        }
        if (($job->target_manifest_hash ?? null) !== ($manifest['target_manifest_hash'] ?? null)) {
            throw new RuntimeException('MANIFEST_TAMPERED');
        }
        return $manifest;
    }
}
