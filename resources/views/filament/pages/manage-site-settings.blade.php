<x-filament-panels::page>
    @if($savedStatus ?? null)<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ $savedStatus }}</p>@endif
    <x-filament::section>
        {{ $this->settingsSchema }}
    </x-filament::section>

    <div class="mt-4 flex justify-start">
        <x-filament::button wire:click="saveSettings" color="primary" size="lg">
             Lưu cấu hình
        </x-filament::button>
    </div>
</x-filament-panels::page>
