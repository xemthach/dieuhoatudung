@props(['testimonial'])

<div {{ $attributes->merge(['class' => 'flex flex-col justify-between rounded-2xl border border-surface-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md']) }}>
    <div>
        <div class="flex items-center gap-4">
            @if($testimonial->avatar)
                <img src="{{ media_url($testimonial->avatar) }}" alt="{{ $testimonial->customer_name }}" class="h-12 w-12 rounded-full object-cover shadow-sm">
            @else
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700">
                    {{ mb_substr($testimonial->customer_name, 0, 1) }}
                </div>
            @endif
            
            <div>
                <h4 class="font-bold text-surface-900">{{ $testimonial->customer_name }}</h4>
                @if($testimonial->customer_title || $testimonial->company_name)
                    <p class="text-xs text-surface-500 mt-0.5">
                        {{ $testimonial->customer_title }}
                        @if($testimonial->customer_title && $testimonial->company_name) - @endif
                        <span class="font-medium">{{ $testimonial->company_name }}</span>
                    </p>
                @endif
                @if($testimonial->location)
                    <p class="text-xs text-surface-400 mt-0.5">{{ $testimonial->location }}</p>
                @endif
            </div>
        </div>
        
        <div class="mt-4 flex text-warning-400">
            @for($i = 0; $i < ($testimonial->rating ?? 5); $i++)
                <x-heroicon-s-star class="h-4 w-4" />
            @endfor
        </div>
        
        <div class="mt-4 text-sm text-surface-700 leading-relaxed italic">
            "{!! nl2br(e($testimonial->content)) !!}"
        </div>
    </div>
    
    @if($testimonial->image)
        <div class="mt-6 overflow-hidden rounded-xl">
            <img src="{{ media_url($testimonial->image) }}" alt="Hình ảnh công trình {{ $testimonial->customer_name }}" class="h-32 w-full object-cover transition-transform duration-300 hover:scale-105">
        </div>
    @endif
</div>
