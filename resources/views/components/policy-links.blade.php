{{-- Policy Links Component --}}
{{-- Variants: list (default), inline, checkbox --}}

@if($variant === 'list')
    {{-- Vertical list of links (footer, sidebar) --}}
    <ul {{ $attributes->merge(['class' => 'space-y-2 text-sm']) }}>
        @foreach($policies as $policy)
            <li>
                <a href="{{ $policy->public_url }}"
                   class="transition-colors hover:text-white">{{ $policy->title }}</a>
            </li>
        @endforeach
    </ul>

@elseif($variant === 'inline')
    {{-- Inline links separated by separator (header top bar) --}}
    <span {{ $attributes->merge(['class' => 'flex items-center gap-3 text-sm']) }}>
        @foreach($policies as $policy)
            @if(!$loop->first)
                <span class="text-surface-600">|</span>
            @endif
            <a href="{{ $policy->public_url }}"
               class="transition-colors hover:text-white">{{ $policy->title }}</a>
        @endforeach
    </span>

@elseif($variant === 'checkbox')
    {{-- Checkbox agreement links (forms) --}}
    <div {{ $attributes->merge(['class' => 'space-y-2 text-sm text-surface-600']) }}>
        @foreach($policies as $policy)
            <p>
                Bằng cách gửi form, bạn đồng ý với
                <a href="{{ $policy->public_url }}" target="_blank"
                   class="text-primary-600 underline hover:text-primary-700">{{ $policy->title }}</a>.
            </p>
        @endforeach
    </div>

@else
    {{-- Fallback: simple link block (product detail) --}}
    <div {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach($policies as $policy)
            <a href="{{ $policy->public_url }}"
               class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 hover:underline">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ $policy->title }}
            </a>
        @endforeach
    </div>
@endif
