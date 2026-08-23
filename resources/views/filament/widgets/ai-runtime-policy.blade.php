<x-filament-widgets::widget>
    @php($policy = $this->getPolicy())
    <x-filament::section heading="Chính sách vận hành AI" description="Cấu hình chỉ đọc đang áp dụng cho nội dung AI.">
        <div class="admin-kv-grid admin-kv-grid-4">
            @foreach([
                'provider' => 'Provider',
                'model' => 'Model',
                'request_timeout_seconds' => 'Timeout (s)',
                'max_attempts' => 'Max attempts',
                'max_retries' => 'Retries',
                'fallback' => 'Fallback',
                'prompt_version' => 'Prompt version',
                'governance_version' => 'Governance version',
                'worker_queue' => 'Worker queue',
                'hard_budget_mode' => 'Hard budget',
                'single_operator_policy' => 'Single-operator policy',
                'single_operator_active' => 'Single-operator active',
                'single_operator_auto_approve' => 'Auto-approve',
                'single_operator_auto_apply' => 'Auto-apply',
            ] as $key => $label)
                <div class="min-w-0">
                    <div class="admin-kv-label">{{ $label }}</div>
                    <div class="admin-kv-value">{{ is_bool($policy[$key]) ? ($policy[$key] ? 'Bật' : 'Tắt') : $policy[$key] }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
