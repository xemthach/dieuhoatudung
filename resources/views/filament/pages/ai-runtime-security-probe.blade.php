<x-filament-panels::page>
    <div data-authenticated-user-id="{{ auth()->id() }}" class="sr-only">Authenticated user ID {{ auth()->id() }}</div>
    <x-filament::section heading="AI Runtime Security Probe" description="Non-mutating authorization fixture. Each action stops at the governed service boundary.">
        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="probeApprove">Probe approve</x-filament::button>
            <x-filament::button wire:click="probeApply">Probe apply</x-filament::button>
            <x-filament::button wire:click="probeApplyWithoutConfirmation">Probe apply without confirmation</x-filament::button>
            <x-filament::button wire:click="probeStaleContext">Probe stale context</x-filament::button>
            <x-filament::button wire:click="probeRollback">Probe rollback</x-filament::button>
        </div>
        @if($results !== [])
            <pre class="mt-4 whitespace-pre-wrap">{{ json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </x-filament::section>
</x-filament-panels::page>
