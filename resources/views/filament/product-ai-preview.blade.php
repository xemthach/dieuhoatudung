@php
    $draft = $item?->draft;
    $payload = $draft?->normalized_output_json ?: ($item?->generated_payload_json ?? []);
    $readiness ??= app(\App\Services\AI\ProductAiApplyReadiness::class)->resolve($draft);
    $warnings = $readiness['soft_warnings'] ?? [];
    $processed = $readiness['technical_processed'] ?? [];
    $blockers = $readiness['hard_blockers'] ?? [];
@endphp

<div class="space-y-5 text-sm">
    @if (! $item)
        <p>Chưa có bản nháp AI cho sản phẩm này.</p>
    @else
        <section class="grid gap-3 rounded-lg border border-gray-200 p-4 sm:grid-cols-2">
            <div><p class="text-xs font-medium uppercase text-gray-500">Trạng thái</p><p class="font-semibold">Bản nháp #{{ $draft?->id ?? '-' }} · {{ $draft?->approval_status ?? $item->canonical_status ?? $item->status }}</p></div>
            <div><p class="text-xs font-medium uppercase text-gray-500">Điểm chất lượng</p><p class="font-semibold">{{ $item->seo_score_before ?? '-' }} → {{ $item->seo_score_after ?? '-' }}</p></div>
            <div class="sm:col-span-2 flex flex-wrap gap-2">
                <span class="rounded bg-warning-50 px-2 py-1 text-warning-700">Cảnh báo chất lượng: {{ count($warnings) }}</span>
                <span class="rounded bg-info-50 px-2 py-1 text-info-700">Cảnh báo kỹ thuật đã xử lý: {{ count($processed) }}</span>
                <span class="rounded bg-danger-50 px-2 py-1 text-danger-700">Hard blockers: {{ count($blockers) }}</span>
            </div>
        </section>

        @if ($blockers !== [])
            <section class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-danger-800">
                <h3 class="font-semibold">Không thể áp dụng bản nháp này</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($blockers as $blocker)<li>{{ $blocker['label'] }}</li>@endforeach</ul>
            </section>
        @endif

        @if ($readiness['stale_target'] ?? false)
            <section class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-warning-800">
                <h3 class="font-semibold">Sản phẩm đã được chỉnh sửa sau khi AI tạo bản nháp.</h3>
                <p class="mt-1">Không thể ghi đè thay đổi mới. Hãy xem khác biệt hoặc tạo lại bản nháp.</p>
            </section>
        @endif

        <section class="space-y-4">
            <h3 class="text-base font-semibold">Nội dung AI</h3>
            @if (filled($payload['excerpt'] ?? null))
                <div class="rounded-lg border border-gray-200 p-3"><h4 class="font-medium">Mô tả ngắn</h4><p class="mt-1">{{ $payload['excerpt'] }}</p></div>
            @endif
            @if (filled($payload['content_html'] ?? null))
                <div class="rounded-lg border border-gray-200 p-3"><h4 class="font-medium">Nội dung dài</h4><div class="prose mt-2 max-h-80 max-w-none overflow-auto">{!! $payload['content_html'] !!}</div></div>
            @endif
            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-3">
                    <h4 class="font-medium">SEO / Open Graph</h4>
                    <p class="mt-1"><strong>SEO title:</strong> {{ $payload['seo_title'] ?? '-' }}</p><p><strong>Meta:</strong> {{ $payload['meta_description'] ?? '-' }}</p>
                    <p><strong>OG title:</strong> {{ $payload['og_title'] ?? '-' }}</p><p><strong>OG description:</strong> {{ $payload['og_description'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <h4 class="font-medium">Google Merchant</h4><p class="mt-1"><strong>Title:</strong> {{ $payload['merchant_title'] ?? '-' }}</p><p><strong>Description:</strong> {{ $payload['merchant_description'] ?? '-' }}</p>
                </div>
            </div>
            @if (! empty($payload['faq'] ?? []))
                <div class="rounded-lg border border-gray-200 p-3"><h4 class="font-medium">FAQ</h4><ul class="mt-2 space-y-2">@foreach ($payload['faq'] as $faq)<li><strong>{{ $faq['question'] ?? '' }}</strong><div>{!! $faq['answer'] ?? '' !!}</div></li>@endforeach</ul></div>
            @endif
        </section>

        <section class="rounded-lg border border-gray-200 p-4">
            <h3 class="font-semibold">Bản nháp AI sẽ cập nhật các trường sau</h3><p class="mt-2">{{ implode(', ', $readiness['field_labels'] ?? []) ?: 'Chưa có trường được duyệt để áp dụng.' }}</p>
            <p class="mt-3 font-medium">Không thay đổi</p><p>{{ implode(', ', $readiness['protected_fields'] ?? []) }}</p>
        </section>

        @if ($warnings !== [])
            <section class="rounded-lg border border-warning-200 bg-warning-50 p-4">
                <h3 class="font-semibold text-warning-800">Cảnh báo cần operator xem xét</h3><ul class="mt-2 list-disc space-y-1 pl-5 text-warning-800">@foreach ($warnings as $warning)<li>{{ $warning['label'] }}</li>@endforeach</ul>
            </section>
        @endif

        <details class="rounded-lg border border-gray-200 p-4">
            <summary class="cursor-pointer font-semibold">Chi tiết kỹ thuật</summary>
            <div class="mt-3 space-y-3 break-words text-xs text-gray-600">
                <p><strong>Job item:</strong> #{{ $item->id }} · <strong>Job:</strong> #{{ $item->ai_product_job_id }}</p><p><strong>Trạng thái:</strong> {{ $item->canonical_status ?? $item->status }}</p>
                <p><strong>Raw warning codes:</strong> {{ implode(', ', array_map(fn ($warning) => $warning['code'], array_merge($warnings, $processed, $blockers))) ?: '-' }}</p>
                <p><strong>Used verified facts:</strong> {{ implode(', ', $payload['used_facts'] ?? []) ?: '-' }}</p>
            </div>
        </details>
    @endif
</div>
