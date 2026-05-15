<x-filament-panels::page>
    <div wire:poll.10s="reload" class="space-y-6">
        @if(empty(data_get($health, 'worker_heartbeat.is_running')))
            <x-filament::section>
                <div class="text-sm font-semibold text-danger-600">
                    AI queue worker is not running or no recent heartbeat was recorded.
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Queue">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500">QUEUE_CONNECTION</div>
                    <div class="font-medium">{{ data_get($health, 'queue_connection', '-') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Pending jobs</div>
                    <div class="font-medium">{{ data_get($health, 'pending_jobs_count', '-') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Failed jobs</div>
                    <div class="font-medium">{{ data_get($health, 'failed_jobs_count', '-') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">AI processing</div>
                    <div class="font-medium">
                        Blog: {{ data_get($health, 'ai_content_processing_count', '-') }},
                        Product: {{ data_get($health, 'ai_product_processing_count', '-') }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">AI stuck</div>
                    <div class="font-medium">{{ data_get($health, 'ai_jobs_stuck_count', '-') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Scheduler heartbeat</div>
                    <div class="font-medium">{{ data_get($health, 'scheduler_heartbeat', '-') }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Worker heartbeat">
            <pre class="overflow-auto rounded bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode(data_get($health, 'worker_heartbeat', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-filament::section>

        <x-filament::section heading="Last processed AI job">
            <pre class="overflow-auto rounded bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode(data_get($health, 'last_processed_job', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-filament::section>
    </div>
</x-filament-panels::page>
