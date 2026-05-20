<?php

namespace App\Services\Bulk;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BulkSelectionResolver
{
    public const SCOPES = ['selected', 'current_page', 'filter'];

    public function resolve(Request $request, string $entityType, Builder $baseQuery): BulkSelectionResult
    {
        return $this->resolvePayload($request->all(), $entityType, $baseQuery);
    }

    public function resolvePayload(array $payload, string $entityType, Builder $baseQuery): BulkSelectionResult
    {
        $scope = $payload['scope'] ?? null;
        $errors = [];
        $ids = [];
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $selectedIds = $this->normalizeIds($payload['selected_ids'] ?? []);
        $currentPageIds = $this->normalizeIds($payload['current_page_ids'] ?? []);
        $selectedCountFromUi = isset($payload['selected_count_from_ui']) ? (int) $payload['selected_count_from_ui'] : null;
        $currentPageCountFromUi = isset($payload['current_page_count_from_ui']) ? (int) $payload['current_page_count_from_ui'] : null;
        $query = clone $baseQuery;
        $filterCount = 0;

        if (! in_array($scope, self::SCOPES, true)) {
            $errors[] = 'bulk_scope_missing';
            $query->whereRaw('1 = 0');

            return $this->invalid($scope, $ids, $query, $filters, count($selectedIds), count($currentPageIds), $filterCount, $errors, $entityType);
        }

        if ($scope === 'selected') {
            if ($selectedIds === []) {
                $errors[] = 'bulk_selected_ids_missing';
            }

            $ids = $this->existingIds($baseQuery, $selectedIds);

            if ($selectedCountFromUi !== null && count($ids) !== $selectedCountFromUi) {
                $errors[] = 'bulk_scope_mismatch';
            }

            if (count($ids) !== count($selectedIds)) {
                $errors[] = 'bulk_scope_mismatch';
            }

            $query = (clone $baseQuery)->whereKey($ids ?: [0]);
        }

        if ($scope === 'current_page') {
            if ($currentPageIds === []) {
                $errors[] = 'bulk_current_page_ids_missing';
            }

            $ids = $this->existingIds($baseQuery, $currentPageIds);

            if ($currentPageCountFromUi !== null && count($ids) !== $currentPageCountFromUi) {
                $errors[] = 'bulk_scope_mismatch';
            }

            if (count($ids) !== count($currentPageIds)) {
                $errors[] = 'bulk_scope_mismatch';
            }

            $query = (clone $baseQuery)->whereKey($ids ?: [0]);
        }

        if ($scope === 'filter') {
            if (($payload['confirm_filter_scope'] ?? false) !== true) {
                $errors[] = 'bulk_filter_not_confirmed';
            }

            $filterQuery = clone $baseQuery;
            $filterCount = (clone $filterQuery)->count();
            $ids = (clone $filterQuery)
                ->pluck($this->qualifiedKeyName($filterQuery))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $query = $filterQuery;
        }

        $result = new BulkSelectionResult(
            scope: $scope,
            ids: $ids,
            query: $query,
            filters: $filters,
            total_count: count($ids),
            selected_count: count($selectedIds),
            current_page_count: count($currentPageIds),
            filter_count: $scope === 'filter' ? $filterCount : 0,
            summary_text: $this->summary($entityType, (string) $scope, count($ids)),
            is_valid: $errors === [],
            errors: array_values(array_unique($errors)),
        );

        if (! $result->is_valid) {
            $this->logFailure($entityType, $result, $payload);
        }

        return $result;
    }

    public function logAction(string $module, string $action, BulkSelectionResult $result, array $extra = []): void
    {
        Log::info('bulk_action_resolved', array_merge([
            'module' => $module,
            'action' => $action,
            'scope' => $result->scope,
            'selected_ids_count' => $result->selected_count,
            'current_page_ids_count' => $result->current_page_count,
            'filter_count' => $result->filter_count,
            'resolved_total_count' => $result->total_count,
            'ids_sample' => array_slice($result->ids, 0, 25),
            'filters' => $result->filters,
            'user_id' => auth()->id(),
            'created_at' => now()->toIso8601String(),
        ], $extra));
    }

    private function invalid(?string $scope, array $ids, Builder $query, array $filters, int $selectedCount, int $currentPageCount, int $filterCount, array $errors, string $entityType): BulkSelectionResult
    {
        $result = new BulkSelectionResult(
            scope: $scope,
            ids: $ids,
            query: $query,
            filters: $filters,
            total_count: 0,
            selected_count: $selectedCount,
            current_page_count: $currentPageCount,
            filter_count: $filterCount,
            summary_text: $this->summary($entityType, (string) $scope, 0),
            is_valid: false,
            errors: array_values(array_unique($errors)),
        );
        $this->logFailure($entityType, $result, []);

        return $result;
    }

    private function logFailure(string $entityType, BulkSelectionResult $result, array $payload): void
    {
        foreach ($result->errors as $error) {
            Log::warning($error, [
                'entity_type' => $entityType,
                'scope' => $result->scope,
                'selected_ids_count' => $result->selected_count,
                'current_page_ids_count' => $result->current_page_count,
                'resolved_total_count' => $result->total_count,
                'payload_keys' => array_keys($payload),
                'user_id' => auth()->id(),
                'created_at' => now()->toIso8601String(),
            ]);
        }
    }

    private function existingIds(Builder $baseQuery, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return (clone $baseQuery)
            ->whereKey($ids)
            ->pluck($this->qualifiedKeyName($baseQuery))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function qualifiedKeyName(Builder $query): string
    {
        return $query->getModel()->getQualifiedKeyName();
    }

    private function normalizeIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function summary(string $entityType, string $scope, int $count): string
    {
        return match ($scope) {
            'selected' => "{$count} {$entityType} da duoc tick checkbox.",
            'current_page' => "{$count} {$entityType} dang hien thi tren trang hien tai.",
            'filter' => "{$count} {$entityType} khop filter hien tai.",
            default => "Bulk scope khong hop le cho {$entityType}.",
        };
    }
}
