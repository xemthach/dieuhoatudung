<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Services\Product\ProductContentEligibilityPolicy;

final class ProductAiGenerationReadiness
{
    public function __construct(
        private readonly ProductContentEligibilityPolicy $eligibility,
        private readonly AIWorkerReadinessService $worker,
        private readonly AiProviderReadinessService $provider,
    ) {}

    /** @return array<string,mixed> */
    public function resolve(Product $product, array|string $scope, ?array $runtime = null): array
    {
        $content = $this->eligibility->evaluate($product, $scope);
        $runtime ??= $this->runtimeSnapshot();
        $worker = $runtime['worker'];
        $provider = $runtime['provider'];
        $blockers = array_map(fn (string $code): array => $this->blocker($code), (array) $content['reasons']);

        if (! $worker['ready']) $blockers[] = $this->blocker('WORKER_OFFLINE');
        if (! $provider['ready']) $blockers[] = $this->blocker('PROVIDER_NOT_CONFIGURED');

        return [
            'can_generate' => $blockers === [],
            'mandatory_blockers' => $blockers,
            'warnings' => [],
            'worker' => $worker,
            'provider' => $provider,
            'next_actions' => array_values(array_unique(array_column($blockers, 'next_action'))),
        ];
    }

    /** @return array{worker:array<string,mixed>,provider:array<string,mixed>} */
    public function runtimeSnapshot(): array
    {
        return [
            'worker' => $this->worker->snapshot(),
            'provider' => $this->provider->summary(),
        ];
    }

    /** @return array<int,true> */
    public function activeConflictProductIds(array $productIds, array $excludedDraftIds = [], array $excludedItemIds = []): array
    {
        return $this->eligibility->activeConflictProductIds($productIds, $excludedDraftIds, $excludedItemIds);
    }

    /**
     * Bounded-query generation preflight shared by Product-list entry points.
     * Existing actionable drafts remain blockers unless their IDs are explicitly
     * supplied as evidence being superseded by a regenerate operation.
     *
     * @return array{selected:int,ready:int,blocked:int,warning:int,ready_ids:array<int,int>,rows:array<int,array<string,mixed>>,runtime:array<string,mixed>}
     */
    public function resolveMany(array $productIds, array $scope, array $excludedDraftIds = [], array $excludedItemIds = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $products = Product::query()->whereKey($ids)->with(['brand:id,name', 'category:id,name'])->get()->keyBy('id');
        $runtime = $this->runtimeSnapshot();
        $conflicts = $this->activeConflictProductIds($ids, $excludedDraftIds, $excludedItemIds);
        $rows = [];
        $readyIds = [];

        foreach ($ids as $id) {
            $product = $products->get($id);
            if (! $product) {
                $rows[] = [
                    'product_id' => $id,
                    'can_generate' => false,
                    'mandatory_blockers' => [$this->blocker('INVALID_TARGET_PRODUCT')],
                    'warnings' => [],
                    'next_actions' => ['FIX_PRODUCT_DATA'],
                ];
                continue;
            }

            $result = $this->resolve($product, $scope + ['_active_conflict' => isset($conflicts[$id])], $runtime);
            $rows[] = ['product_id' => $id, 'product_name' => (string) $product->name] + $result;
            if ($result['can_generate']) $readyIds[] = $id;
        }

        return [
            'selected' => count($ids),
            'ready' => count($readyIds),
            'blocked' => count($ids) - count($readyIds),
            'warning' => count(array_filter($rows, fn (array $row): bool => ($row['warnings'] ?? []) !== [])),
            'ready_ids' => $readyIds,
            'rows' => $rows,
            'runtime' => $runtime,
        ];
    }

    /** @return array{code:string,message:string,next_action:string,overrideable:bool} */
    private function blocker(string $code): array
    {
        return [
            'code' => $code,
            'message' => match (true) {
                $code === 'WORKER_OFFLINE' => 'AI worker hiện không hoạt động.',
                $code === 'PROVIDER_NOT_CONFIGURED' => 'Chưa có AI provider hoạt động và được cấu hình.',
                $code === 'ACTIVE_DRAFT_OR_APPLY_CONFLICT' => 'Đã có bản nháp hoặc thao tác Apply đang chờ xử lý.',
                $code === 'MINIMUM_PRODUCT_IDENTITY_INSUFFICIENT' => 'Sản phẩm thiếu tên/model hoặc ngữ cảnh thương hiệu.',
                default => app(AiContentStatusPresenter::class)->safeReason($code),
            },
            'next_action' => match ($code) {
                'WORKER_OFFLINE' => 'VIEW_AI_STATUS',
                'PROVIDER_NOT_CONFIGURED' => 'CONFIGURE_PROVIDER',
                'ACTIVE_DRAFT_OR_APPLY_CONFLICT' => 'VIEW_EXISTING_DRAFT',
                default => 'FIX_PRODUCT_DATA',
            },
            'overrideable' => false,
        ];
    }
}
