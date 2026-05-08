<x-filament-panels::page>
    <style>
        .ip-stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
        @media (min-width: 640px)  { .ip-stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .ip-stats-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }

        .ip-card { border-radius: 0.75rem; background: white; box-shadow: 0 1px 2px 0 rgba(0,0,0,.05); border: 1px solid rgba(0,0,0,.05); }
        .dark .ip-card { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .ip-card-header { padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); display: flex; align-items: center; justify-content: space-between; }
        .dark .ip-card-header { border-color: rgba(255,255,255,.1); }
        .ip-card-body { padding: 1.5rem; }

        .stat-card { position: relative; overflow: hidden; padding: 1rem; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 0.75rem 0.75rem 0 0; }
        .stat-card.accent-gray::before    { background: #9ca3af; }
        .stat-card.accent-success::before { background: #22c55e; }
        .stat-card.accent-danger::before  { background: #ef4444; }
        .stat-card.accent-primary::before { background: #3b82f6; }
        .stat-card.accent-warning::before { background: #f59e0b; }
        .stat-card.accent-info::before    { background: #6366f1; }

        .ip-info-table { width: 100%; border-collapse: collapse; }
        .ip-info-table td { padding: 0.625rem 0; font-size: 0.875rem; border-bottom: 1px solid rgba(0,0,0,.04); }
        .dark .ip-info-table td { border-color: rgba(255,255,255,.05); }
        .ip-info-table tr:last-child td { border-bottom: none; }
        .ip-info-table td.lbl { width: 140px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; vertical-align: middle; }
        .dark .ip-info-table td.lbl { color: #6b7280; }
        .ip-info-table td.val { color: #111827; font-weight: 500; }
        .dark .ip-info-table td.val { color: #f3f4f6; }

        .ip-tw { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .ip-tw::-webkit-scrollbar { height: 6px; }
        .ip-tw::-webkit-scrollbar-thumb { background: rgba(0,0,0,.12); border-radius: 3px; }
        .ip-dt { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        .ip-dt th { position: sticky; top: 0; z-index: 10; white-space: nowrap; padding: 0.625rem 0.75rem; font-weight: 600; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; background: #f9fafb; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        .dark .ip-dt th { background: rgba(255,255,255,.03); color: #9ca3af; border-color: rgba(255,255,255,.1); }
        .ip-dt td { padding: 0.5rem 0.75rem; white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis; color: #374151; }
        .dark .ip-dt td { color: #d1d5db; }
        .ip-dt tbody tr { transition: background-color 0.1s; border-bottom: 1px solid rgba(0,0,0,.03); }
        .ip-dt tbody tr:nth-child(even) { background: rgba(0,0,0,.015); }
        .dark .ip-dt tbody tr:nth-child(even) { background: rgba(255,255,255,.015); }
        .ip-dt tbody tr:hover { background: rgba(59,130,246,.04); }
        .dark .ip-dt tbody tr:hover { background: rgba(59,130,246,.06); }
        .ip-dt tbody tr.row-err { background: rgba(239,68,68,.03); }
        .ip-dt tbody tr.row-err:hover { background: rgba(239,68,68,.07); }
        .ip-dt .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; }

        .cell-empty { color: #d1d5db; } .dark .cell-empty { color: #4b5563; }
        .cell-idfb { display:inline-flex; align-items:center; gap:0.25rem; padding:0.125rem 0.5rem; font-size:0.6875rem; font-weight:500; font-family:ui-monospace,monospace; background:#fef3c7; color:#92400e; border-radius:0.375rem; }
        .dark .cell-idfb { background:rgba(245,158,11,.15); color:#fbbf24; }
        .cell-bt { display:inline-flex; align-items:center; gap:0.25rem; padding:0.125rem 0.5rem; font-size:0.6875rem; font-weight:500; background:#dcfce7; color:#166534; border-radius:9999px; }
        .dark .cell-bt { background:rgba(34,197,94,.15); color:#4ade80; }
        .cell-bf { display:inline-flex; align-items:center; gap:0.25rem; padding:0.125rem 0.5rem; font-size:0.6875rem; font-weight:500; background:#f3f4f6; color:#6b7280; border-radius:9999px; }
        .dark .cell-bf { background:rgba(255,255,255,.08); color:#9ca3af; }
        .cell-price { font-weight:600; color:#059669; } .dark .cell-price { color:#34d399; }
        .cell-sok { display:inline-flex; padding:0.125rem 0.5rem; font-size:0.6875rem; font-weight:500; background:#dcfce7; color:#166534; border-radius:9999px; }
        .dark .cell-sok { background:rgba(34,197,94,.15); color:#4ade80; }
        .cell-sout { display:inline-flex; padding:0.125rem 0.5rem; font-size:0.6875rem; font-weight:500; background:#fee2e2; color:#991b1b; border-radius:9999px; }
        .dark .cell-sout { background:rgba(239,68,68,.15); color:#f87171; }

        .ip-footer-box { display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap; padding:0.875rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); background:rgba(59,130,246,.02); }
        .dark .ip-footer-box { border-color:rgba(255,255,255,.08); background:rgba(59,130,246,.04); }
        .ip-footer-stat { display:flex; flex-direction:column; }
        .ip-footer-stat .num { font-size:0.875rem; font-weight:700; color:#1e40af; }
        .dark .ip-footer-stat .num { color:#93c5fd; }
        .ip-footer-stat .lbl { font-size:0.625rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; }
    </style>

    @if($job)
    <div style="display:flex; flex-direction:column; gap:1.5rem;">

        {{-- ═══ 1. Summary Stats ═══════════════════════════════════ --}}
        <div class="ip-stats-grid">
            @php
                $cards = [
                    ['icon' => 'heroicon-o-document-text',  'label' => 'Tổng dòng',  'value' => number_format($job->total_rows),   'color' => 'gray'],
                    ['icon' => 'heroicon-o-check-circle',   'label' => 'Hợp lệ',     'value' => number_format($job->success_rows),  'color' => 'success'],
                    ['icon' => 'heroicon-o-x-circle',       'label' => 'Lỗi',        'value' => number_format($job->failed_rows),   'color' => 'danger'],
                    ['icon' => 'heroicon-o-plus-circle',    'label' => 'Tạo mới',    'value' => number_format($job->created_rows),  'color' => 'primary'],
                    ['icon' => 'heroicon-o-pencil-square',  'label' => 'Cập nhật',   'value' => number_format($job->updated_rows),  'color' => 'warning'],
                    ['icon' => 'heroicon-o-cog-6-tooth',    'label' => 'Chế độ',     'value' => strtoupper($job->mode),              'color' => 'info', 'small' => true],
                ];
            @endphp
            @foreach($cards as $card)
            <div class="stat-card accent-{{ $card['color'] }} ip-card">
                <div class="flex items-center gap-x-2">
                    <x-filament::icon :icon="$card['icon']" @class([
                        'fi-wi-stats-overview-stat-icon',
                        match($card['color']) {
                            'success' => 'text-success-500 dark:text-success-400',
                            'danger'  => 'text-danger-500 dark:text-danger-400',
                            'primary' => 'text-primary-500 dark:text-primary-400',
                            'warning' => 'text-warning-500 dark:text-warning-400',
                            'info'    => 'text-purple-500 dark:text-purple-400',
                            default   => 'text-gray-400 dark:text-gray-500',
                        },
                    ]) />
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
                </div>
                <div @class([
                    'mt-2 font-bold tracking-tight',
                    isset($card['small']) ? 'text-lg' : 'text-3xl',
                    match($card['color']) {
                        'success' => 'text-success-600 dark:text-success-400',
                        'danger'  => 'text-danger-600 dark:text-danger-400',
                        'primary' => 'text-primary-600 dark:text-primary-400',
                        'warning' => 'text-warning-600 dark:text-warning-400',
                        'info'    => 'text-purple-600 dark:text-purple-400',
                        default   => 'text-gray-950 dark:text-white',
                    },
                ])>{{ $card['value'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- ═══ 2. File Information (table layout) ═════════════════ --}}
        <div class="ip-card">
            <div class="ip-card-header">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-document-arrow-up" class="fi-wi-stats-overview-stat-icon text-primary-500" />
                    Thông tin file
                </h3>
            </div>
            <div class="ip-card-body">
                <table class="ip-info-table">
                    <tr>
                        <td class="lbl">Module</td>
                        <td class="val">{{ \App\Services\DataTransfer\ModuleRegistry::modules()[$job->module] ?? $job->module }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Tên file</td>
                        <td class="val" style="font-family:ui-monospace,monospace; font-size:0.8125rem;" title="{{ $job->file_name }}">{{ \Illuminate\Support\Str::limit($job->file_name, 60) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Định dạng</td>
                        <td class="val">
                            <x-filament::badge color="info">{{ strtoupper($job->file_type) }}</x-filament::badge>
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Matching Key</td>
                        <td class="val">
                            <x-filament::badge color="gray">{{ strtoupper($job->matching_key ?? 'id') }}</x-filament::badge>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ═══ 3. Error Details ═══════════════════════════════════ --}}
        <div class="ip-card">
            <div class="ip-card-header">
                <h3 class="text-sm font-semibold flex items-center gap-2 {{ ($job->error_report_json && count($job->error_report_json) > 0) ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fi-wi-stats-overview-stat-icon shrink-0" />
                    Dòng lỗi
                    @if($job->error_report_json && count($job->error_report_json) > 0)
                        <x-filament::badge color="danger">{{ count($job->error_report_json) }}</x-filament::badge>
                    @endif
                </h3>
            </div>
            @if($job->error_report_json && count($job->error_report_json) > 0)
                <div class="overflow-x-auto overflow-y-auto" style="max-height:320px;">
                    <table class="ip-dt" style="min-width:500px;">
                        <thead><tr>
                            <th class="text-left" style="width:80px;">Dòng</th>
                            <th class="text-left">Chi tiết lỗi</th>
                        </tr></thead>
                        <tbody>
                            @foreach(array_slice($job->error_report_json, 0, 50) as $error)
                            <tr>
                                <td class="align-top" style="padding:0.625rem 0.75rem;">
                                    <x-filament::badge color="danger">#{{ $error['row'] ?? '-' }}</x-filament::badge>
                                </td>
                                <td style="white-space:normal; padding:0.625rem 0.75rem;">
                                    @foreach(($error['errors'] ?? []) as $errMsg)
                                        <div class="flex items-start gap-1.5 {{ !$loop->first ? 'mt-1' : '' }} text-danger-600 dark:text-danger-400" style="font-size:0.75rem;">
                                            <span class="shrink-0 mt-1 block" style="width:5px;height:5px;border-radius:50%;background:currentColor;"></span>
                                            <span>{{ $errMsg }}</span>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($job->error_report_json) > 50)
                    <div style="padding:0.75rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); font-size:0.75rem; color:#6b7280;">
                        Hiển thị 50 / {{ count($job->error_report_json) }} lỗi
                    </div>
                @endif
            @else
                <div style="padding:2.5rem 1.5rem; text-align:center;">
                    <div class="mx-auto flex items-center justify-center rounded-full bg-success-50 dark:bg-success-400/10" style="width:48px;height:48px;">
                        <x-filament::icon icon="heroicon-o-check-badge" class="text-success-500 dark:text-success-400" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-success-700 dark:text-success-400">Không có lỗi dữ liệu</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Tất cả {{ number_format($job->total_rows) }} dòng đều hợp lệ</p>
                </div>
            @endif
        </div>

        {{-- ═══ 4. Preview Data Table ══════════════════════════════ --}}
        @if($job->preview_data_json && count($job->preview_data_json) > 0)
        <div class="ip-card">
            <div class="ip-card-header">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-table-cells" class="fi-wi-stats-overview-stat-icon text-primary-500" />
                    Preview dữ liệu
                </h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ count($this->displayColumns) }} cột · {{ min(20, $job->total_rows) }} dòng</span>
            </div>

            <div class="ip-tw">
                @php
                    $monoFields = ['model_code', 'sku', 'slug', 'id'];
                @endphp
                <table class="ip-dt" style="min-width:{{ max(900, count($this->displayColumns) * 140) }}px;">
                    <thead><tr>
                        <th class="text-left" style="width:48px;">#</th>
                        <th class="text-left" style="width:88px;">Trạng thái</th>
                        <th class="text-center" style="width:52px;">Valid</th>
                        @foreach($this->displayColumns as $col)
                        <th class="text-left" style="min-width:110px;">
                            {{ $this->columnLabels[$col] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $col)) }}
                        </th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach($job->preview_data_json as $previewRow)
                        @php
                            $hasErrors = !empty($previewRow['errors']);
                            $actionColor = match($previewRow['action'] ?? 'create') {
                                'create' => 'success', 'update' => 'warning', 'skip' => 'gray', default => 'gray',
                            };
                            $actionLabel = match($previewRow['action'] ?? 'create') {
                                'create' => 'Tạo mới', 'update' => 'Cập nhật', 'skip' => 'Bỏ qua', default => $previewRow['action'] ?? '-',
                            };
                        @endphp
                        <tr class="{{ $hasErrors ? 'row-err' : '' }}">
                            <td class="mono text-gray-400 dark:text-gray-500">{{ $previewRow['row_number'] }}</td>
                            <td><x-filament::badge :color="$actionColor">{{ $actionLabel }}</x-filament::badge></td>
                            <td class="text-center">
                                @if(!$hasErrors)
                                    <div class="mx-auto flex items-center justify-center" style="width:18px;height:18px;">
                                        <x-filament::icon icon="heroicon-o-check-circle" class="text-success-500" />
                                    </div>
                                @else
                                    <div class="mx-auto flex items-center justify-center" style="width:18px;height:18px;" title="{{ implode('; ', $previewRow['errors']) }}">
                                        <x-filament::icon icon="heroicon-o-x-circle" class="text-danger-500" />
                                    </div>
                                @endif
                            </td>
                            @foreach($this->displayColumns as $col)
                                @php $resolved = $this->resolveDisplayValue($col, $previewRow['data'][$col] ?? null); @endphp
                                <td title="{{ $resolved['full'] ?? $resolved['value'] }}" @if(in_array($col, $monoFields)) class="mono" @endif>
                                    @switch($resolved['type'])
                                        @case('empty')
                                            <span class="cell-empty">—</span>
                                            @break
                                        @case('id_fallback')
                                            <span class="cell-idfb">{{ $resolved['value'] }}</span>
                                            @break
                                        @case('bool_true')
                                            <span class="cell-bt">
                                                <svg style="width:10px;height:10px;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                {{ $resolved['value'] }}
                                            </span>
                                            @break
                                        @case('bool_false')
                                            <span class="cell-bf">{{ $resolved['value'] }}</span>
                                            @break
                                        @case('price')
                                            <span class="cell-price">{{ $resolved['value'] }}</span>
                                            @break
                                        @case('stock_ok')
                                            <span class="cell-sok">{{ $resolved['value'] }}</span>
                                            @break
                                        @case('stock_out')
                                            <span class="cell-sout">{{ $resolved['value'] }}</span>
                                            @break
                                        @default
                                            <span>{{ $resolved['value'] }}</span>
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Footer info box --}}
            @if($job->total_rows > 20)
            <div class="ip-footer-box">
                <div class="ip-footer-stat">
                    <span class="num">{{ number_format($job->total_rows) }}</span>
                    <span class="lbl">Tổng dòng</span>
                </div>
                <div class="ip-footer-stat">
                    <span class="num">20</span>
                    <span class="lbl">Đang preview</span>
                </div>
                <div class="ip-footer-stat">
                    <span class="num">{{ number_format($job->total_rows - 20) }}</span>
                    <span class="lbl">Còn lại</span>
                </div>
                <div style="flex:1; min-width:200px;">
                    <div class="flex items-center gap-1.5" style="font-size:0.75rem; color:#6b7280;">
                        <x-filament::icon icon="heroicon-m-information-circle" class="shrink-0" />
                        <span>Dữ liệu còn lại sẽ được xử lý khi xác nhận import.</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

    </div>
    @else
    {{-- ═══ No Job State ═══════════════════════════════════════════ --}}
    <div class="ip-card" style="padding:3rem; text-align:center;">
        <div class="mx-auto flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800" style="width:56px;height:56px;">
            <x-filament::icon icon="heroicon-o-exclamation-circle" class="text-gray-400 dark:text-gray-500" />
        </div>
        <p class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-400">Không tìm thấy dữ liệu preview</p>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Vui lòng upload lại file để bắt đầu import.</p>
    </div>
    @endif
</x-filament-panels::page>
