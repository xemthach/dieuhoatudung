<x-filament-panels::page>
    @if($job)
    <div class="space-y-6">

        {{-- Result Summary --}}
        <div class="rounded-xl {{ $job->status === 'completed' ? 'bg-success-50 dark:bg-success-400/10 ring-success-200 dark:ring-success-400/20' : 'bg-danger-50 dark:bg-danger-400/10 ring-danger-200 dark:ring-danger-400/20' }} shadow-sm ring-1 p-6">
            <div class="flex items-center gap-3 mb-4">
                @if($job->status === 'completed')
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-600 dark:text-success-400" />
                    <h3 class="text-xl font-bold text-success-700 dark:text-success-400">Import hoàn tất!</h3>
                @else
                    <x-heroicon-o-x-circle class="h-8 w-8 text-danger-600 dark:text-danger-400" />
                    <h3 class="text-xl font-bold text-danger-700 dark:text-danger-400">Import thất bại</h3>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Tổng dòng</div>
                    <div class="text-lg font-bold text-gray-950 dark:text-white">{{ number_format($job->total_rows) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Thành công</div>
                    <div class="text-lg font-bold text-success-600 dark:text-success-400">{{ number_format($job->success_rows) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Tạo mới</div>
                    <div class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ number_format($job->created_rows) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Cập nhật</div>
                    <div class="text-lg font-bold text-warning-600 dark:text-warning-400">{{ number_format($job->updated_rows) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Lỗi</div>
                    <div class="text-lg font-bold text-danger-600 dark:text-danger-400">{{ number_format($job->failed_rows) }}</div>
                </div>
            </div>
        </div>

        {{-- Job Details --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">Chi tiết Job</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Module:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white">
                        {{ \App\Services\DataTransfer\ModuleRegistry::modules()[$job->module] ?? $job->module }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-500">File:</span>
                    <span class="ml-1 font-medium font-mono text-xs text-gray-950 dark:text-white">{{ $job->file_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Chế độ:</span>
                    <span class="ml-1 font-medium uppercase text-gray-950 dark:text-white">{{ $job->mode }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Thời gian:</span>
                    <span class="ml-1 font-medium text-gray-950 dark:text-white">
                        @if($job->started_at && $job->finished_at)
                            {{ $job->started_at->diffInSeconds($job->finished_at) }}s
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Errors --}}
        @if($job->error_report_json && count($job->error_report_json) > 0)
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="text-base font-semibold text-danger-600 dark:text-danger-400 flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    Chi tiết lỗi ({{ count($job->error_report_json) }})
                </h3>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 w-20">Dòng</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Lỗi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach($job->error_report_json as $error)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $error['row'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-danger-600 dark:text-danger-400 text-xs">
                                {{ implode(' | ', $error['errors'] ?? []) }}
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
