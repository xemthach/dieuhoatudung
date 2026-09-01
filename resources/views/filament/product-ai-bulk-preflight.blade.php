@php
    $counts = $preflight['counts'];
    $classes = $preflight['classifications'];
    $rows = collect($preflight['rows']);
    $copy = [
        'selected' => "\u{0110}\u{00E3} ch\u{1ECD}n", 'products' => "s\u{1EA3}n ph\u{1EA9}m", 'review' => "C\u{1EA7}n duy\u{1EC7}t", 'approved' => "\u{0110}\u{00E3} duy\u{1EC7}t",
        'processing' => "\u{0110}ang x\u{1EED} l\u{00FD}", 'blocked' => "B\u{1ECB} ch\u{1EB7}n / l\u{1ED7}i", 'no_draft' => "Ch\u{01B0}a c\u{00F3} draft",
        'ready_approve' => "S\u{1EB5}n s\u{00E0}ng duy\u{1EC7}t", 'ready_apply' => "S\u{1EB5}n s\u{00E0}ng Apply", 'regenerate' => "C\u{00F3} th\u{1EC3} t\u{1EA1}o l\u{1EA1}i",
        'details' => "Chi ti\u{1EBF}t preflight theo s\u{1EA3}n ph\u{1EA9}m", 'product' => "S\u{1EA3}n ph\u{1EA9}m", 'state' => "Tr\u{1EA1}ng th\u{00E1}i",
        'field' => "Tr\u{01B0}\u{1EDD}ng", 'warning' => "C\u{1EA3}nh b\u{00E1}o", 'view' => 'Xem', 'yes' => "C\u{00F3}", 'no' => "Kh\u{00F4}ng",
    ];
@endphp

<div class="space-y-4" data-testid="ai-bulk-preflight" data-selected-count="{{ $preflight['selected'] }}">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $copy['selected'] }} {{ $preflight['selected'] }} {{ $copy['products'] }}</div>
        <div class="mt-3 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['review'] }} <strong class="block text-lg">{{ $counts['REVIEW_REQUIRED'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['approved'] }} <strong class="block text-lg">{{ $counts['APPROVED'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['processing'] }} <strong class="block text-lg">{{ $counts['QUEUED'] + $counts['PROCESSING'] + $counts['VALIDATING'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['blocked'] }} <strong class="block text-lg">{{ $counts['BLOCKED'] + $counts['FAILED'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['no_draft'] }} <strong class="block text-lg">{{ $counts['AVAILABLE'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['ready_approve'] }} <strong class="block text-lg">{{ $classes['READY_TO_APPROVE'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['ready_apply'] }} <strong class="block text-lg">{{ $classes['READY_TO_APPLY'] }}</strong></div>
            <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">{{ $copy['regenerate'] }} <strong class="block text-lg">{{ $classes['REGENERATE_AVAILABLE'] }}</strong></div>
        </div>
        @if ($mode === 'regenerate')
            <p class="mt-3 text-sm text-warning-700 dark:text-warning-400">{{ "T\u{1ED1}i \u{0111}a {$classes['REGENERATE_AVAILABLE']} y\u{00EA}u c\u{1EA7}u provider s\u{1EBD} \u{0111}\u{01B0}\u{1EE3}c g\u{1EED}i. H\u{00E0}ng \u{0111}ang x\u{1EED} l\u{00FD}, hard-block ho\u{1EB7}c \u{0111}\u{00E3} Apply kh\u{00F4}ng \u{0111}\u{01B0}\u{1EE3}c t\u{1EA1}o l\u{1EA1}i." }}</p>
        @elseif ($mode === 'apply')
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ "Ch\u{1EC9} {$classes['READY_TO_APPLY']} s\u{1EA3}n ph\u{1EA9}m \u{0111}\u{00E3} duy\u{1EC7}t, kh\u{00F4}ng stale v\u{00E0} kh\u{00F4}ng hard-block \u{0111}\u{01B0}\u{1EE3}c Apply. SKU, gi\u{00E1}, danh m\u{1EE5}c v\u{00E0} th\u{00F4}ng s\u{1ED1} k\u{1EF9} thu\u{1EAD}t lu\u{00F4}n \u{0111}\u{01B0}\u{1EE3}c b\u{1EA3}o v\u{1EC7}." }}</p>
        @endif
    </div>
    <details class="rounded-xl border border-gray-200 dark:border-white/10" open>
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">{{ $copy['details'] }}</summary>
        <div class="max-h-96 overflow-auto border-t border-gray-200 dark:border-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10"><thead class="sticky top-0 bg-gray-50 dark:bg-gray-900"><tr>
                <th class="px-3 py-2 text-left">{{ $copy['product'] }}</th><th class="px-3 py-2 text-left">Draft</th><th class="px-3 py-2 text-left">{{ $copy['state'] }}</th><th class="px-3 py-2 text-right">{{ $copy['field'] }}</th><th class="px-3 py-2 text-right">{{ $copy['warning'] }}</th><th class="px-3 py-2 text-right">Hard block</th><th class="px-3 py-2 text-right">Score</th><th class="px-3 py-2 text-center">Provider</th><th class="px-3 py-2 text-right">{{ $copy['view'] }}</th>
            </tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($rows->take(100) as $row)
                    <tr data-product-id="{{ $row['product_id'] }}" data-ai-state="{{ $row['state'] }}"><td class="max-w-xs truncate px-3 py-2" title="{{ $row['product_name'] }}">{{ $row['product_name'] }}</td><td class="px-3 py-2">{{ $row['draft_id'] ? '#'.$row['draft_id'] : '—' }}</td><td class="px-3 py-2 font-medium">{{ $row['state'] }}</td><td class="px-3 py-2 text-right">{{ $row['generated_fields'] }}</td><td class="px-3 py-2 text-right">{{ $row['soft_warning_count'] }}</td><td class="px-3 py-2 text-right {{ $row['hard_blocker_count'] ? 'text-danger-600' : '' }}">{{ $row['hard_blocker_count'] }}</td><td class="px-3 py-2 text-right">{{ $row['score'] ?? '—' }}</td><td class="px-3 py-2 text-center">{{ $row['provider_called'] ? $copy['yes'] : $copy['no'] }}</td><td class="px-3 py-2 text-right"><a class="text-primary-600 hover:underline" target="_blank" href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $row['product_id']]) }}">{{ $copy['view'] }}</a></td></tr>
                @endforeach
            </tbody></table>
        </div>
        @if ($rows->count() > 100)<p class="px-4 py-3 text-xs text-gray-500">{{ "Hi\u{1EC3}n th\u{1ECB} 100/{$rows->count()} h\u{00E0}ng. To\u{00E0}n b\u{1ED9} ID v\u{1EAB}n \u{0111}\u{00E3} \u{0111}\u{01B0}\u{1EE3}c snapshot trong operation manifest." }}</p>@endif
    </details>
</div>
