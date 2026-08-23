<x-filament-widgets::widget>
    <x-filament::section heading="System health">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
            @foreach($health['components'] as $name => $component)
                <div class="rounded-lg border p-3">
                    <div class="text-xs uppercase text-gray-500">{{ $name }}</div>
                    <div class="mt-1 font-semibold">{{ $component['state'] }}</div>
                    @if(isset($component['pending']))<div class="text-xs text-gray-500">Pending: {{ $component['pending'] }}</div>@endif
                    @if(isset($component['failed']))<div class="text-xs text-gray-500">Failed: {{ $component['failed'] }}</div>@endif
                    @if(isset($component['desired']))<div class="text-xs text-gray-500">Desired: {{ $component['desired'] }}</div>@endif
                </div>
            @endforeach
        </div>
        <div class="mt-3 text-xs text-gray-500">Snapshot: {{ $health['generated_at'] }}</div>
    </x-filament::section>
</x-filament-widgets::widget>
