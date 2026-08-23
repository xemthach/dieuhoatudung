<x-filament-widgets::widget>
    @php($policy = $this->getPolicy())
    <x-filament::section heading="AI Runtime Policy" description="Read-only policy resolved from the governed runtime configuration.">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
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
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ is_bool($policy[$key]) ? ($policy[$key] ? 'true' : 'false') : $policy[$key] }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
