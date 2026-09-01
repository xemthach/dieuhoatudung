@php
    $view = $status['status'] ?? ['label' => 'Chưa tạo', 'color' => 'gray'];
    $latestJobId = $status['job_id'] ?? $status['history_job_id'] ?? null;
    $historyView = !empty($status['history_status'])
        ? app(\App\Services\AI\AiContentStatusPresenter::class)->present($status['history_status'])
        : null;
    $tone = match ($view['color'] ?? 'gray') {
        'success' => 'bg-success-50 text-success-700 ring-success-600/20',
        'info' => 'bg-info-50 text-info-700 ring-info-600/20',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
        default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
    };
@endphp

<div wire:poll.10s="refreshStatus" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Nội dung AI</div>
            <div class="mt-1 text-xs text-gray-500">Cập nhật {{ $status['updated_human'] ?? 'chưa có' }}</div>
        </div>
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $tone }}">
            {{ $view['label'] ?? 'Chưa tạo' }}
        </span>
    </div>

    @if(!empty($status['warning']))
        <div class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-800 ring-1 ring-warning-600/20">
            {{ $status['warning'] }}
        </div>
    @endif

    @if(!empty($status['safe_reason']))
        <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
            {{ $status['safe_reason'] }}
        </div>
    @endif

    @if($historyView)
        <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
            <span class="font-medium">Lịch sử gần nhất:</span>
            {{ $historyView['label'] }}
            @if(!empty($status['history_reason']))
                · {{ app(\App\Services\AI\AiContentStatusPresenter::class)->safeReason($status['history_reason']) }}
            @endif
        </div>
    @endif

    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
            <dt class="text-xs text-gray-500">Công việc gần nhất</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $latestJobId ? '#'.$latestJobId : 'Chưa có' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
            <dt class="text-xs text-gray-500">Bản nháp</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ !empty($status['draft_id']) ? 'Có bản nháp' : 'Không có' }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
            <dt class="text-xs text-gray-500">Duyệt</dt>
            <dd class="font-medium text-gray-900 dark:text-white">
                {{ !empty($status['review_required']) ? 'Cần duyệt' : (!empty($status['approved_at']) ? 'Đã duyệt' : 'Không chờ duyệt') }}
            </dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
            <dt class="text-xs text-gray-500">Áp dụng</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ !empty($status['applied_at']) ? 'Đã áp dụng' : (!empty($status['approved_unapplied']) ? 'Sẵn sàng áp dụng' : 'Chưa áp dụng') }}</dd>
        </div>
    </dl>

    @if(!empty($status['progress']))
        <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
            <div class="flex justify-between gap-3">
                <span>{{ $status['progress']['processed'] }} / {{ $status['progress']['total'] }} sản phẩm đã xử lý</span>
                <span>{{ $status['progress']['percent'] }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div class="h-full rounded-full bg-info-600" style="width: {{ $status['progress']['percent'] }}%"></div>
            </div>
        </div>
    @endif

    @if(!empty($status['fields']))
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            @foreach($status['fields'] as $field)
                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                    <span class="text-gray-700 dark:text-gray-200">{{ $field['label'] }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $field['status']['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if($jobUrl || !empty($status['review_required']))
        <div class="mt-4 flex flex-wrap gap-2">
            @if($jobUrl)
                <a href="{{ $jobUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500">Xem công việc AI</a>
            @endif
            @if(!empty($status['review_required']) && auth()->user()?->can('product.ai_generate'))
                <span class="text-sm text-warning-700">Dùng các action Duyệt / Apply phía trên sau khi kiểm tra nội dung.</span>
            @endif
        </div>
    @endif
</div>
