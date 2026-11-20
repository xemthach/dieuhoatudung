@if($style === 'text')
    <span class="text-sm font-medium text-surface-700">{{ $brand->name }}</span>
@elseif($style === 'logo' && $brand->logo_url)
    <span class="inline-flex h-9 min-w-12 items-center justify-center rounded-lg border border-surface-100 bg-white px-2"><img src="{{ $brand->logo_url }}" class="max-h-6 max-w-16 object-contain" alt="{{ $brand->name }}" loading="lazy"></span>
@elseif($style === 'logo_name' && $brand->logo_url)
    <span class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-white py-1 pl-1.5 pr-2.5 text-xs font-medium text-surface-700"><img src="{{ $brand->logo_url }}" class="h-5 w-8 object-contain" alt="" loading="lazy">{{ $brand->name }}</span>
@else
    <span class="inline-flex items-center rounded-full border border-primary-100 bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-800">{{ $brand->name }}</span>
@endif
