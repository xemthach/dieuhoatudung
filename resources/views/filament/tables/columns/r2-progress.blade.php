@php
    $state = $getRecord();
    $progress = 0;
    
    if ($state->mode === 'scan_only') {
        // Scan: completed = 100%, otherwise show total_files found
        $progress = $state->status === 'completed' ? 100 : ($state->status === 'scanning' ? 50 : 0);
    } elseif ($state->mode === 'upload_only') {
        $done = ($state->synced_files ?? 0) + ($state->failed_files ?? 0);
        $progress = $state->total_files > 0 ? min(100, round(($done / $state->total_files) * 100)) : 0;
    } else {
        $progress = $state->status === 'completed' ? 100 : ($state->status === 'replacing' ? 50 : 0);
    }
@endphp

<div class="fi-ta-text px-3 py-4 w-full min-w-[200px]">
    @if($state->mode === 'scan_only')
        <div class="flex items-center justify-between text-xs mb-1">
            <span class="text-gray-500 font-medium">{{ number_format($state->total_files ?? 0) }} files</span>
            <span class="font-bold {{ $state->status === 'completed' ? 'text-success-600' : 'text-primary-600' }}">{{ $progress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
            <div class="h-1.5 rounded-full {{ $state->status === 'completed' ? 'bg-success-600' : ($state->status === 'failed' ? 'bg-danger-600' : 'bg-primary-600') }}" style="width: {{ $progress }}%"></div>
        </div>
    @elseif($state->mode === 'upload_only')
        <div class="flex items-center justify-between text-xs mb-1">
            <span class="text-gray-500 font-medium">{{ number_format($state->synced_files ?? 0) }} / {{ number_format($state->total_files ?? 0) }}</span>
            <span class="font-bold {{ $progress === 100 ? 'text-success-600' : 'text-primary-600' }}">{{ $progress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
            <div class="h-1.5 rounded-full {{ $progress === 100 ? 'bg-success-600' : ($state->status === 'failed' ? 'bg-danger-600' : 'bg-primary-600') }}" style="width: {{ $progress }}%"></div>
        </div>
        @if(($state->failed_files ?? 0) > 0)
            <div class="text-xs text-danger-600 mt-1">{{ $state->failed_files }} failed</div>
        @endif
    @elseif($state->mode === 'replace_urls_only')
        <div class="text-sm">
            <span class="font-semibold">{{ number_format($state->replaced_occurrences ?? 0) }}</span>
            <span class="text-gray-500 text-xs ml-1">luot thay the</span>
        </div>
        @if($state->dry_run)
            <span class="text-xs text-warning-600">(Dry Run)</span>
        @endif
    @endif
</div>
