<x-filament-panels::page>
    <div class="space-y-4">
        @foreach($this->policies() as $policy)
            <x-filament::section>
                <div class="grid gap-3 md:grid-cols-7">
                    <div class="md:col-span-2"><div class="font-semibold">{{ $policy['key'] }}</div><div class="text-sm text-gray-500">{{ $policy['description'] }}</div></div>
                    <div><div class="text-xs text-gray-500">Module</div>{{ $policy['module'] }}</div>
                    <div><div class="text-xs text-gray-500">Risk / Default</div>{{ $policy['risk'] }} / {{ $policy['default'] }}</div>
                    <div><div class="text-xs text-gray-500">Last change</div>{{ $policy['last_changed_by'] ?? 'Never' }}<div class="text-xs text-gray-500">{{ $policy['last_changed_at'] }} {{ $policy['last_reason'] }}</div></div>
                    <div>
                        @if($policy['system_locked'] ?? false)
                            <x-filament::badge color="danger">LOCKED — SYSTEM INTEGRITY</x-filament::badge>
                        @else
                            <select wire:model="modes.{{ $this->stateKey($policy['key']) }}" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" data-policy-mode="{{ $policy['key'] }}">
                                @foreach($policy['modes'] as $mode)<option value="{{ $mode }}">{{ $mode }}</option>@endforeach
                            </select>
                            <input wire:model="reasons.{{ $this->stateKey($policy['key']) }}" class="mt-2 w-full rounded-lg border-gray-300 dark:bg-gray-900" placeholder="Required change reason" data-policy-reason="{{ $policy['key'] }}">
                        @endif
                    </div>
                    <div>
                        @if(!($policy['system_locked'] ?? false))
                            <x-filament::button wire:click="savePolicy('{{ $policy['key'] }}')" wire:confirm="Confirm this governance policy change?" data-policy-save="{{ $policy['key'] }}">Save</x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
    <x-filament::section heading="Recent high-risk governance changes">
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Policy</th><th>Old</th><th>New</th><th>Operator</th><th>Time</th><th>Reason</th></tr></thead><tbody>
        @foreach($this->recentAudits() as $audit)<tr><td>{{ $audit->policy_key }}</td><td>{{ $audit->old_mode }}</td><td>{{ $audit->new_mode }}</td><td>{{ $audit->operator?->name ?? '#'.$audit->changed_by }}</td><td>{{ $audit->changed_at }}</td><td>{{ $audit->reason }}</td></tr>@endforeach
        </tbody></table></div>
    </x-filament::section>
</x-filament-panels::page>
