@php
    $payload = $item?->generated_payload_json ?? [];
@endphp

<div class="space-y-4 text-sm">
    @if (! $item)
        <p>Chưa có AI draft cho sản phẩm này. Hãy chạy Generate AI Content và đợi queue hoàn thành.</p>
    @else
        @if (! empty($payload['blocked_claims'] ?? []))
            <div class="rounded border border-danger-200 bg-danger-50 p-3 text-danger-700">
                <strong>Draft bị fact-check chặn:</strong>
                {{ implode(', ', $payload['blocked_claims'] ?? []) }}
            </div>
        @endif

        @if (! empty($payload['blocked_product_data_fields'] ?? []))
            <div class="rounded border border-warning-200 bg-warning-50 p-3 text-warning-800">
                <strong>Field dữ liệu gốc bị bỏ qua:</strong>
                {{ implode(', ', $payload['blocked_product_data_fields'] ?? []) }}
            </div>
        @endif

        <div>
            <p><strong>Job item:</strong> #{{ $item->id }}</p>
            <p><strong>Status:</strong> {{ $item->status }}</p>
            <p><strong>Score:</strong> {{ $item->seo_score_before ?? '-' }} -> {{ $item->seo_score_after ?? '-' }}</p>
            <p><strong>Warnings:</strong> {{ implode(', ', $item->warnings_json ?? []) ?: '-' }}</p>
        </div>

        <div class="rounded border border-gray-200 p-3">
            <h3 class="font-semibold">Fields sẽ được apply</h3>
            <p>short_description, long_description, seo_title, seo_description, og_title, og_description, merchant_title, merchant_description, tags, FAQ.</p>
            <p class="mt-2"><strong>Không apply:</strong> name, slug, model, SKU, brand, category, giá, stock_status, technical specs, specs_json.</p>
        </div>

        <div>
            <h3 class="font-semibold">Used verified facts</h3>
            <p>{{ implode(', ', $payload['used_facts'] ?? []) ?: '-' }}</p>
        </div>

        <div>
            <h3 class="font-semibold">Excerpt</h3>
            <p>{{ $payload['excerpt'] ?? '-' }}</p>
        </div>

        <div>
            <h3 class="font-semibold">SEO / OG / Merchant</h3>
            <ul class="list-disc pl-5">
                <li>SEO title: {{ $payload['seo_title'] ?? '-' }}</li>
                <li>Meta: {{ $payload['meta_description'] ?? '-' }}</li>
                <li>OG title: {{ $payload['og_title'] ?? '-' }}</li>
                <li>OG description: {{ $payload['og_description'] ?? '-' }}</li>
                <li>Merchant title: {{ $payload['merchant_title'] ?? '-' }}</li>
                <li>Merchant description: {{ $payload['merchant_description'] ?? '-' }}</li>
            </ul>
        </div>

        <div>
            <h3 class="font-semibold">Tags</h3>
            <p>{{ implode(', ', $payload['tags'] ?? []) ?: '-' }}</p>
        </div>

        <div>
            <h3 class="font-semibold">FAQ</h3>
            <ul class="list-disc pl-5">
                @foreach (($payload['faq'] ?? []) as $faq)
                    <li><strong>{{ $faq['question'] ?? '' }}</strong> {!! $faq['answer'] ?? '' !!}</li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="font-semibold">Content HTML</h3>
            <div class="max-h-96 overflow-auto rounded border p-3">
                {!! $payload['content_html'] ?? '' !!}
            </div>
        </div>
    @endif
</div>
