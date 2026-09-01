<?php

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Support\Collection;

final class AiProductLiveStatusService
{
    public function __construct(
        private AiContentStatusPresenter $presenter,
        private AiProductContentStateResolver $stateResolver,
        private AIQueueMonitor $queueMonitor,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forProductIds(array $ids, ?array $health = null): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->take(100)->values();
        $health ??= $this->queueMonitor->liveStatusHealth();
        $worker = [
            'desired_state' => data_get($health, 'worker_desired_state', 'DISABLED'),
            'health' => data_get($health, 'worker_heartbeat.health_status', 'UNKNOWN'),
        ];

        return Product::query()
            ->whereKey($ids->all())
            ->select(['id', 'name', 'model_code', 'ai_status', 'ai_score', 'ai_warning_count', 'ai_last_run_at'])
            ->with([
                'aiProductJobItems' => fn ($query) => $query->latest('id')->with([
                    'job:id,total,processed,success,failed,needs_review,status,config_json,updated_at',
                    'draft:id,status,field_status_json,approval_status,approved_at,approved_by,applied_at',
                ]),
                'aiProductDrafts' => fn ($query) => $query->latest('id'),
            ])
            ->get()
            ->map(function (Product $product) use ($worker): array {
                $resolved = $this->stateResolver->resolve($product);
                $item = $resolved['item'];
                $draft = $resolved['draft'];
                $internal = $resolved['status'];
                $status = $this->presenter->present($internal, $worker);
                $job = $item?->job;
                $total = (int) ($job?->total ?? 0);
                $processed = (int) ($job?->processed ?? 0);
                $fieldStates = (array) ($item?->field_status_json ?: $draft?->field_status_json ?: []);
                $outputs = (array) data_get($job?->config_json, 'outputs', []);
                $requestedFields = collect(array_is_list($outputs)
                    ? $outputs
                    : collect($outputs)->filter(fn ($enabled) => (bool) $enabled)->keys()->all());

                if ($fieldStates === [] && $requestedFields->isNotEmpty()) {
                    $fieldStates = $requestedFields->mapWithKeys(fn ($field) => [$field => $internal])->all();
                }

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'model' => $product->model_code,
                    'status' => $status,
                    'ai_status' => strtolower($status['key']),
                    'ai_status_label' => $status['label'],
                    'warning' => $status['warning'],
                    'safe_reason' => $resolved['state_issue'] === 'REVIEWABLE_DRAFT_MISSING'
                        ? 'Trạng thái cũ không còn bản nháp có thể duyệt; cần tạo lại nội dung AI.'
                        : $this->presenter->safeReason($item?->failed_reason ?: $item?->last_error_code),
                    'seo_score' => (int) ($product->ai_score ?? 0),
                    'warnings_count' => (int) ($product->ai_warning_count ?? 0),
                    'updated_at' => ($item?->state_changed_at ?: $item?->updated_at ?: $product->ai_last_run_at)?->toIso8601String(),
                    'updated_human' => ($item?->state_changed_at ?: $item?->updated_at ?: $product->ai_last_run_at)?->diffForHumans() ?? 'Chưa có cập nhật',
                    'started_at' => $item?->started_at?->toIso8601String(),
                    'progress' => $total > 1 ? [
                        'processed' => $processed,
                        'total' => $total,
                        'percent' => (int) min(100, round(($processed / max(1, $total)) * 100)),
                        'success' => (int) ($job?->success ?? 0),
                        'review' => (int) ($job?->needs_review ?? 0),
                        'failed' => (int) ($job?->failed ?? 0),
                    ] : null,
                    'fields' => collect($fieldStates)->map(fn ($fieldStatus, $field) => [
                        'field' => (string) $field,
                        'label' => $this->presenter->fieldLabel((string) $field),
                        'status' => $this->presenter->present((string) $fieldStatus),
                    ])->values()->all(),
                    'job_id' => $item?->ai_product_job_id,
                    'draft_id' => $item?->draft_id,
                    'retry_allowed' => $item && in_array($item->status, ['failed', 'stuck', 'cancelled'], true),
                    'review_required' => $resolved['reviewable'],
                    'approved_unapplied' => $resolved['approved_unapplied'],
                    'state_issue' => $resolved['state_issue'],
                    'worker' => $worker,
                    'should_poll' => (bool) $status['active'],
                    'approved_at' => $draft?->approved_at?->toIso8601String(),
                    'approved_by' => $draft?->approved_by,
                    'applied_at' => $draft?->applied_at?->toIso8601String(),
                ];
            })
            ->keyBy('id');
    }

    /** @return array<string, mixed> */
    public function forProduct(int $id): array
    {
        return $this->forProductIds([$id])->get($id, []);
    }
}
