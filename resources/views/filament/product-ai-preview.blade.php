@php
    $payload = $item?->generated_payload_json ?? [];
@endphp

<div class="space-y-4 text-sm">
    @if (! $item)
        <p>Chưa có AI draft cho sản phẩm này. Hãy chạy Generate AI Product và đợi queue hoàn thành.</p>
    @else
        <div>
            <p><strong>Job item:</strong> #{{ $item->id }}</p>
            <p><strong>Status:</strong> {{ $item->status }}</p>
            <p><strong>Score:</strong> {{ $item->seo_score_before ?? '-' }} → {{ $item->seo_score_after ?? '-' }}</p>
            <p><strong>Warnings:</strong> {{ implode(', ', $item->warnings_json ?? []) ?: '-' }}</p>
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
                <li>Merchant title: {{ $payload['merchant_title'] ?? '-' }}</li>
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
