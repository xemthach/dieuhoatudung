<x-filament-panels::page>
    @php
        $integrations = $health['integrations'] ?? [];
        $summary = $health['summary'] ?? [];
        $events = $health['recommended_events'] ?? [];
    @endphp

    <div class="space-y-6">
        @if (! empty($lastUploadResult))
            <x-filament::section>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="font-semibold">Last Google Ads upload</span>
                    <span>Checked: {{ $lastUploadResult['checked'] ?? 0 }}</span>
                    <span>Uploaded: {{ $lastUploadResult['uploaded'] ?? 0 }}</span>
                    <span>Failed: {{ $lastUploadResult['failed'] ?? 0 }}</span>
                    <span>Skipped: {{ $lastUploadResult['skipped'] ?? 0 }}</span>
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Configured</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ $summary['configured_count'] ?? 0 }}/{{ $summary['total_count'] ?? count($integrations) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Critical missing</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ count($summary['critical_missing'] ?? []) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tracked event plan</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ count($events) }}
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($integrations as $key => $integration)
                <x-filament::section>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold">{{ $integration['label'] ?? $key }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $integration['configured'] ?? false ? 'Configured' : 'Needs configuration' }}
                            </p>
                        </div>

                        <span
                            @class([
                                'rounded-md px-2 py-1 text-xs font-medium',
                                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' => $integration['configured'] ?? false,
                                'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => ! ($integration['configured'] ?? false) && (($integration['severity'] ?? null) !== 'critical'),
                                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => ! ($integration['configured'] ?? false) && (($integration['severity'] ?? null) === 'critical'),
                            ])
                        >
                            {{ $integration['configured'] ?? false ? 'Ready' : 'Missing' }}
                        </span>
                    </div>

                    @if (! empty($integration['missing']))
                        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                            <div class="font-medium">Missing</div>
                            <div class="mt-1 text-gray-600 dark:text-gray-300">
                                {{ implode(', ', $integration['missing']) }}
                            </div>
                        </div>
                    @endif

                    @if (! empty($integration['capabilities']))
                        <div class="mt-4">
                            <div class="text-sm font-medium">Capabilities</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($integration['capabilities'] as $capability)
                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $capability }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($integration['values']))
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-300">
                                Technical values
                            </summary>
                            <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ json_encode($integration['values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <h2 class="text-base font-semibold">Recommended conversion events</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($events as $event)
                    <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ $event }}
                    </span>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
