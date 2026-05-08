<x-filament-panels::page>
    <style>
        .result-stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
        .result-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        @media (min-width: 640px) { .result-stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .result-stats-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } .result-info-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    </style>
    @if($job)
    <div class="space-y-6">

        {{-- Result Banner --}}
        <div class="rounded-xl shadow-sm ring-1 p-6 {{ $job->status === 'completed' ? 'bg-success-50 ring-success-200 dark:bg-success-400/10 dark:ring-success-400/20' : 'bg-danger-50 ring-danger-200 dark:bg-danger-400/10 dark:ring-danger-400/20' }}">
            <div class="flex items-start gap-4">
                @if($job->status === 'completed')
                    <div class="shrink-0 flex items-center justify-center rounded-full bg-success-100 dark:bg-success-400/20" style="width: 48px; height: 48px;">
                        <x-filament::icon icon="heroicon-o-check-circle" class="text-success-600 dark:text-success-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-bold text-success-800 dark:text-success-300">Import hoan tat</h3>
                        <p class="mt-1 text-sm text-success-700 dark:text-success-400">
                            Da import thanh cong {{ number_format($job->success_rows) }} dong du lieu.
                        </p>
                    </div>
                @else
                    <div class="shrink-0 flex items-center justify-center rounded-full bg-danger-100 dark:bg-danger-400/20" style="width: 48px; height: 48px;">
                        <x-filament::icon icon="heroicon-o-x-circle" class="text-danger-600 dark:text-danger-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-bold text-danger-800 dark:text-danger-300">Import that bai</h3>
                        <p class="mt-1 text-sm text-danger-700 dark:text-danger-400">
                            Co {{ number_format($job->failed_rows) }} dong loi trong qua trinh xu ly.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Stats Grid --}}
        <div class="result-stats-grid">
            @php
                $cards = [
                    ['icon' => 'heroicon-o-document-text', 'label' => 'Tong dong', 'value' => number_format($job->total_rows), 'color' => 'gray'],
                    ['icon' => 'heroicon-o-check-circle', 'label' => 'Thanh cong', 'value' => number_format($job->success_rows), 'color' => 'success'],
                    ['icon' => 'heroicon-o-plus-circle', 'label' => 'Tao moi', 'value' => number_format($job->created_rows), 'color' => 'primary'],
                    ['icon' => 'heroicon-o-pencil-square', 'label' => 'Cap nhat', 'value' => number_format($job->updated_rows), 'color' => 'warning'],
                    ['icon' => 'heroicon-o-x-circle', 'label' => 'Loi', 'value' => number_format($job->failed_rows), 'color' => 'danger'],
                ];
            @endphp
            @foreach($cards as $card)
            <div class="fi-wi-stats-overview-stat rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-filament::icon :icon="$card['icon']" @class([
                        'fi-wi-stats-overview-stat-icon',
                        match($card['color']) {
                            'success' => 'text-success-500 dark:text-success-400',
                            'danger' => 'text-danger-500 dark:text-danger-400',
                            'primary' => 'text-primary-500 dark:text-primary-400',
                            'warning' => 'text-warning-500 dark:text-warning-400',
                            default => 'text-gray-400 dark:text-gray-500',
                        },
                    ]) />
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
                </div>
                <div @class([
                    'mt-1 text-2xl font-bold',
                    match($card['color']) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'danger' => 'text-danger-600 dark:text-danger-400',
                        'primary' => 'text-primary-600 dark:text-primary-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        default => 'text-gray-950 dark:text-white',
                    },
                ])>{{ $card['value'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Job Details --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="fi-wi-stats-overview-stat-icon text-gray-400" />
                    Chi tiet Job
                </h3>
            </div>
            <div class="p-6">
                <div class="result-info-grid">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Module</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-950 dark:text-white">
                            {{ \App\Services\DataTransfer\ModuleRegistry::modules()[$job->module] ?? $job->module }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Ten file</dt>
                        <dd class="mt-0.5 text-sm font-medium font-mono text-xs text-gray-950 dark:text-white break-all">{{ $job->file_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Che do import</dt>
                        <dd class="mt-0.5">
                            <x-filament::badge color="gray" size="sm">
                                {{ strtoupper($job->mode) }}
                            </x-filament::badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Thoi gian xu ly</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-950 dark:text-white">
                            @if($job->started_at && $job->finished_at)
                                {{ $job->started_at->diffInSeconds($job->finished_at) }}s
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </dd>
                    </div>
                </div>
            </div>
        </div>

        {{-- Error Details --}}
        @if($job->error_report_json && count($job->error_report_json) > 0)
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-danger-600 dark:text-danger-400 flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fi-wi-stats-overview-stat-icon shrink-0" />
                    Chi tiet loi
                    <x-filament::badge color="danger" size="sm">
                        {{ count($job->error_report_json) }}
                    </x-filament::badge>
                </h3>
            </div>
            <div class="overflow-x-auto overflow-y-auto" style="max-height: 384px;">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400" style="width: 80px;">Dong</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Chi tiet loi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach($job->error_report_json as $error)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-2.5 align-top">
                                <x-filament::badge color="danger" size="sm">
                                    #{{ $error['row'] ?? '-' }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-2.5 text-danger-600 dark:text-danger-400 text-xs">
                                @foreach(($error['errors'] ?? []) as $errMsg)
                                    <div class="flex items-start gap-1.5 {{ !$loop->first ? 'mt-1' : '' }}">
                                        <span class="shrink-0 mt-1 block" style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                                        <span>{{ $errMsg }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
    @endif
</x-filament-panels::page>
