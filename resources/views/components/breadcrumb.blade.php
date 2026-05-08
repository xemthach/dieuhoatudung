@props([
    'items' => [],
])

<nav aria-label="Breadcrumb" class="border-b border-surface-100 bg-white">
    <div class="container-main">
        <ol class="flex flex-wrap items-center gap-2 py-3 text-sm text-surface-500" itemscope itemtype="https://schema.org/BreadcrumbList">
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="{{ route('home') }}" itemprop="item" class="flex items-center gap-1 transition-colors hover:text-primary-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span itemprop="name">Trang chủ</span>
                </a>
                <meta itemprop="position" content="1">
            </li>

            @foreach($items as $index => $item)
                <li class="flex items-center gap-2" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <svg class="h-4 w-4 text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    @if(isset($item['url']) && !$loop->last)
                        <a href="{{ $item['url'] }}" itemprop="item" class="transition-colors hover:text-primary-600">
                            <span itemprop="name">{{ $item['label'] }}</span>
                        </a>
                    @else
                        <span itemprop="name" class="font-medium text-surface-800">{{ $item['label'] }}</span>
                    @endif
                    <meta itemprop="position" content="{{ $index + 2 }}">
                </li>
            @endforeach
        </ol>
    </div>

    @push('schema')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@@type": "ListItem",
                "position": 1,
                "name": "Trang chủ",
                "item": "{{ route('home') }}"
            }
            @foreach($items as $index => $item)
            ,{
                "@@type": "ListItem",
                "position": {{ $index + 2 }},
                "name": "{{ e($item['label']) }}"
                @if(isset($item['url']))
                ,"item": "{{ $item['url'] }}"
                @endif
            }
            @endforeach
        ]
    }
    </script>
    @endpush
</nav>
