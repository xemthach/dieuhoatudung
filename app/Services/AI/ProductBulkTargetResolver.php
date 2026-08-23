<?php

namespace App\Services\AI;

use App\Services\Bulk\BulkSelectionResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use App\Support\CanonicalJsonHasher;

/** Resolves and freezes bulk Product scope exactly once. */
class ProductBulkTargetResolver
{
    public const SELECTED = 'SELECTED';
    public const CURRENT_PAGE = 'CURRENT_PAGE';
    public const CURRENT_FILTER = 'CURRENT_FILTER';
    public const ALL_MATCHING = 'ALL_MATCHING';
    public const EXPLICIT_ALL = 'EXPLICIT_ALL';

    public function resolve(string $scope, array $selected = [], array $page = [], array $filter = [], array $all = [], bool $explicitAll = false, array $permitted = []): array
    {
        $ids = match ($scope) {
            self::SELECTED => $this->requireNonEmpty($selected, 'EMPTY_SELECTED_SCOPE'),
            self::CURRENT_PAGE => $this->requireNonEmpty($page, 'EMPTY_PAGE_SCOPE'),
            self::CURRENT_FILTER => $this->requireNonEmpty($filter, 'EMPTY_FILTER_SCOPE'),
            self::ALL_MATCHING, self::EXPLICIT_ALL => $explicitAll ? $all : throw new RuntimeException('EXPLICIT_ALL_CONFIRMATION_REQUIRED'),
            default => throw new RuntimeException('INVALID_SCOPE'),
        };
        if ($ids === []) throw new RuntimeException('EMPTY_SCOPE');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (in_array(0, $ids, true) || in_array(-1, $ids, true)) throw new RuntimeException('INVALID_PRODUCT_ID');
        if ($permitted !== []) {
            $notPermitted = array_values(array_diff($ids, array_map('intval', $permitted)));
            if ($notPermitted !== []) throw new RuntimeException('PRODUCT_SCOPE_FORBIDDEN');
        }
        return $ids;
    }

    public function manifest(string $scope, array $resolvedIds, int $createdBy, array $filterSnapshot = [], array $options = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $resolvedIds)));
        if ($ids === []) throw new RuntimeException('EMPTY_MANIFEST');
        $manifest = [
            'batch_uuid' => $options['batch_uuid'] ?? bin2hex(random_bytes(16)),
            'scope_type' => $scope,
            'resolved_product_ids' => $ids,
            'target_count' => count($ids),
            'created_by' => $createdBy,
            'created_at' => $options['created_at'] ?? now()->toIso8601String(),
            'filter_snapshot' => $filterSnapshot,
            'operation' => $options['operation'] ?? 'product_content',
            'requested_fields' => array_values($options['requested_fields'] ?? ['content_html']),
            'prompt_version' => $options['prompt_version'] ?? AIContentGovernance::PROMPT_VERSION,
            'provider_policy' => $options['provider_policy'] ?? 'fake_only',
            'permission_snapshot' => $options['permission_snapshot'] ?? [
                'created_by' => $createdBy,
                'permitted_product_ids' => $options['permitted_product_ids'] ?? $ids,
            ],
        ];
        $manifest['target_manifest_hash'] = $this->hash($manifest);
        return $manifest;
    }

    public function verify(array $manifest): bool
    {
        $expected = $manifest['target_manifest_hash'] ?? null;
        if (! is_string($expected) || $expected === '') return false;
        $copy = $manifest; unset($copy['target_manifest_hash']);
        return hash_equals($expected, $this->hash($copy));
    }

    /** Boundary adapter for legacy UI payloads; all AI target resolution remains here. */
    public function resolveLegacyPayload(array $payload, string $entityType, Builder $baseQuery): BulkSelectionResult
    {
        $legacyScope = (string) ($payload['scope'] ?? '');
        $scope = match ($legacyScope) {
            'selected' => self::SELECTED,
            'current_page' => self::CURRENT_PAGE,
            'filter', 'all_filtered' => self::CURRENT_FILTER,
            default => throw new RuntimeException('INVALID_SCOPE'),
        };
        $selected = array_map('intval', $payload['selected_ids'] ?? []);
        $page = array_map('intval', $payload['current_page_ids'] ?? []);
        $filter = $scope === self::CURRENT_FILTER
            ? $baseQuery->pluck($baseQuery->getModel()->getQualifiedKeyName())->map(fn ($id) => (int) $id)->all()
            : [];
        try {
            $ids = $this->resolve($scope, $selected, $page, $filter, [], (bool) ($payload['confirm_filter_scope'] ?? false));
            return new BulkSelectionResult(
                scope: strtolower($legacyScope), ids: $ids, query: (clone $baseQuery)->whereKey($ids),
                filters: is_array($payload['filters'] ?? null) ? $payload['filters'] : [],
                total_count: count($ids), selected_count: count($selected), current_page_count: count($page),
                filter_count: $scope === self::CURRENT_FILTER ? count($filter) : 0,
                summary_text: "{$entityType} target resolved", is_valid: true, errors: []
            );
        } catch (RuntimeException $exception) {
            return new BulkSelectionResult(
                scope: strtolower($legacyScope), ids: [], query: (clone $baseQuery)->whereRaw('1 = 0'), filters: [],
                total_count: 0, selected_count: count($selected), current_page_count: count($page), filter_count: 0,
                summary_text: "{$entityType} target blocked", is_valid: false, errors: [$exception->getMessage()]
            );
        }
    }

    public function logAction(string $module, string $action, BulkSelectionResult $result, array $extra = []): void
    {
        Log::info('bulk_action_resolved', array_merge(['module' => $module, 'action' => $action, 'scope' => $result->scope, 'resolved_total_count' => $result->total_count], $extra));
    }

    private function requireNonEmpty(array $ids, string $error): array
    {
        if ($ids === []) throw new RuntimeException($error);
        return $ids;
    }

    private function hash(array $value): string
    {
        return app(CanonicalJsonHasher::class)->hash($value);
    }
}
