<x-filament-panels::page>
    <x-filament::section>
        {{ $this->settingsSchema }}
    </x-filament::section>

    <div class="mt-4 flex justify-start">
        <x-filament::button wire:click="saveSettings" color="primary" size="lg">
             Lưu cấu hình
        </x-filament::button>
    </div>
</x-filament-panels::page>
