<x-filament-panels::page>
    <style>
        .dt-grid-4 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
        @media (min-width: 640px)  { .dt-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

        .dt-card { border-radius: 0.75rem; background: white; box-shadow: 0 1px 2px 0 rgba(0,0,0,.05); border: 1px solid rgba(0,0,0,.05); overflow: hidden; }
        .dark .dt-card { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .dt-card-head { padding: 0.875rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); display: flex; align-items: center; justify-content: space-between; }
        .dark .dt-card-head { border-color: rgba(255,255,255,.08); }

        .dt-stat { position: relative; overflow: hidden; padding: 1rem 1.25rem; }
        .dt-stat::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .dt-stat.ac-export::before  { background: linear-gradient(90deg, #22c55e, #16a34a); }
        .dt-stat.ac-import::before  { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .dt-stat.ac-success::before { background: linear-gradient(90deg, #10b981, #059669); }
        .dt-stat.ac-danger::before  { background: linear-gradient(90deg, #ef4444, #dc2626); }

        .dt-tw { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dt-tw::-webkit-scrollbar { height: 6px; }
        .dt-tw::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 3px; }
        .dt-tbl { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        .dt-tbl th { position: sticky; top: 0; z-index: 10; white-space: nowrap; padding: 0.5rem 0.875rem; font-weight: 600; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; background: #f9fafb; color: #6b7280; border-bottom: 2px solid #e5e7eb; text-align: left; }
        .dark .dt-tbl th { background: rgba(255,255,255,.03); color: #9ca3af; border-color: rgba(255,255,255,.1); }
        .dt-tbl td { padding: 0.5rem 0.875rem; white-space: nowrap; color: #374151; vertical-align: middle; }
        .dark .dt-tbl td { color: #d1d5db; }
        .dt-tbl tbody tr { transition: background-color 0.1s; border-bottom: 1px solid rgba(0,0,0,.03); }
        .dark .dt-tbl tbody tr { border-color: rgba(255,255,255,.04); }
        .dt-tbl tbody tr:nth-child(even) { background: rgba(0,0,0,.012); }
        .dark .dt-tbl tbody tr:nth-child(even) { background: rgba(255,255,255,.015); }
        .dt-tbl tbody tr:hover { background: rgba(59,130,246,.04); }
        .dark .dt-tbl tbody tr:hover { background: rgba(59,130,246,.06); }
        .dt-tbl .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.6875rem; }
        .dt-tbl .fname { max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
        .dt-tbl .num-ok { color: #059669; font-weight: 600; } .dark .dt-tbl .num-ok { color: #34d399; }
        .dt-tbl .num-err { color: #dc2626; font-weight: 600; } .dark .dt-tbl .num-err { color: #f87171; }
        .dt-tbl .num-err.zero { color: #d1d5db; font-weight: 400; } .dark .dt-tbl .num-err.zero { color: #4b5563; }

        .dt-act { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 0.375rem; transition: all 0.15s; color: #6b7280; }
        .dt-act:hover { background: rgba(59,130,246,.1); color: #3b82f6; }
        .dark .dt-act:hover { background: rgba(59,130,246,.15); color: #60a5fa; }

        .dt-module-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 1024px) { .dt-module-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>

    <div style="display:flex; flex-direction:column; gap:1.5rem;">

        {{-- ═══ 1. Summary Stats ═══════════════════════════════════ --}}
        <div class="dt-grid-4">
            @php
                $stats = [
                    [
                        'icon'  => 'heroicon-o-arrow-down-tray',
                        'label' => 'Exports gần đây',
                        'value' => \App\Models\DataExportJob::where('created_at', '>=', now()->subDays(7))->count(),
                        'sub'   => '7 ngày qua',
                        'accent'=> 'ac-export',
                        'cls'   => 'text-success-500 dark:text-success-400',
                    ],
                    [
                        'icon'  => 'heroicon-o-arrow-up-tray',
                        'label' => 'Imports gần đây',
                        'value' => \App\Models\DataImportJob::where('created_at', '>=', now()->subDays(7))->count(),
                        'sub'   => '7 ngày qua',
                        'accent'=> 'ac-import',
                        'cls'   => 'text-primary-500 dark:text-primary-400',
                    ],
                    [
                        'icon'  => 'heroicon-o-check-circle',
                        'label' => 'Thành công',
                        'value' => \App\Models\DataImportJob::where('status','completed')->count() + \App\Models\DataExportJob::where('status','completed')->count(),
                        'sub'   => 'Tổng jobs hoàn thành',
                        'accent'=> 'ac-success',
                        'cls'   => 'text-emerald-500 dark:text-emerald-400',
                    ],
                    [
                        'icon'  => 'heroicon-o-x-circle',
                        'label' => 'Thất bại',
                        'value' => \App\Models\DataImportJob::where('status','failed')->count() + \App\Models\DataExportJob::where('status','failed')->count(),
                        'sub'   => 'Tổng jobs lỗi',
                        'accent'=> 'ac-danger',
                        'cls'   => 'text-red-500 dark:text-red-400',
                    ],
                ];
            @endphp

            @foreach($stats as $s)
            <div class="dt-stat {{ $s['accent'] }} dt-card">
                <div class="flex items-center gap-2">
                    <x-filament::icon :icon="$s['icon']" @class(['fi-wi-stats-overview-stat-icon shrink-0', $s['cls']]) />
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $s['label'] }}</span>
                </div>
                <div class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ number_format($s['value']) }}</div>
                <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $s['sub'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- ═══ 2. Export Jobs ══════════════════════════════════════ --}}
        <div class="dt-card">
            <div class="dt-card-head">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="fi-wi-stats-overview-stat-icon text-success-500" />
                    Export Jobs gần đây
                </h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ \App\Models\DataExportJob::count() }} tổng</span>
            </div>
            <div class="dt-tw">
                <table class="dt-tbl" style="min-width:760px;">
                    <thead><tr>
                        <th style="width:52px;">ID</th>
                        <th>Module</th>
                        <th style="width:72px;">Format</th>
                        <th style="width:80px;">Số dòng</th>
                        <th style="width:100px;">Trạng thái</th>
                        <th>Người tạo</th>
                        <th style="width:100px;">Thời gian</th>
                        <th style="width:48px;"></th>
                    </tr></thead>
                    <tbody>
                        @forelse(\App\Models\DataExportJob::latest()->take(10)->get() as $ej)
                        <tr>
                            <td class="mono text-gray-400">#{{ $ej->id }}</td>
                            <td><x-filament::badge color="info" size="sm">{{ \App\Services\DataTransfer\ModuleRegistry::modules()[$ej->module] ?? $ej->module }}</x-filament::badge></td>
                            <td><x-filament::badge color="gray" size="sm">{{ strtoupper($ej->file_type) }}</x-filament::badge></td>
                            <td class="font-medium text-gray-950 dark:text-white">{{ number_format($ej->total_rows) }}</td>
                            <td>
                                <x-filament::badge :color="match($ej->status) { 'completed' => 'success', 'failed' => 'danger', 'processing' => 'warning', default => 'gray' }" size="sm">
                                    {{ $ej->status_label }}
                                </x-filament::badge>
                            </td>
                            <td class="text-xs text-gray-500 dark:text-gray-400">{{ $ej->creator?->name ?? '—' }}</td>
                            <td class="text-xs text-gray-500 dark:text-gray-400">{{ $ej->created_at?->format('d/m H:i') }}</td>
                            <td>
                                @if($ej->isDownloadable())
                                <a href="{{ route('admin.export.download', $ej) }}" class="dt-act" title="Tải file export">
                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" />
                                </a>
                                @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="padding:2.5rem 1rem; text-align:center;">
                                <div class="mx-auto flex items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800" style="width:40px;height:40px;">
                                    <x-filament::icon icon="heroicon-o-inbox" class="text-gray-300 dark:text-gray-600" />
                                </div>
                                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Chưa có export nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ 3. Import Jobs ══════════════════════════════════════ --}}
        <div class="dt-card">
            <div class="dt-card-head">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-arrow-up-tray" class="fi-wi-stats-overview-stat-icon text-primary-500" />
                    Import Jobs gần đây
                </h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ \App\Models\DataImportJob::count() }} tổng</span>
            </div>
            <div class="dt-tw">
                <table class="dt-tbl" style="min-width:960px;">
                    <thead><tr>
                        <th style="width:52px;">ID</th>
                        <th>Module</th>
                        <th>Tên file</th>
                        <th style="width:80px;">Mode</th>
                        <th style="width:64px;">Tổng</th>
                        <th style="width:64px;">OK</th>
                        <th style="width:64px;">Lỗi</th>
                        <th style="width:100px;">Trạng thái</th>
                        <th style="width:100px;">Thời gian</th>
                        <th style="width:80px;text-align:center;">Thao tác</th>
                    </tr></thead>
                    <tbody>
                        @forelse(\App\Models\DataImportJob::latest()->take(15)->get() as $ij)
                        @php
                            $stColor = match($ij->status) {
                                'completed'  => 'success',
                                'failed'     => 'danger',
                                'previewing' => 'warning',
                                'importing'  => 'info',
                                default      => 'gray',
                            };
                            $stLabel = match($ij->status) {
                                'completed'  => 'Hoàn thành',
                                'failed'     => 'Lỗi',
                                'previewing' => 'Preview',
                                'importing'  => 'Đang import',
                                'pending'    => 'Chờ xử lý',
                                default      => $ij->status,
                            };
                            $modeColor = match($ij->mode) {
                                'create' => 'success',
                                'update' => 'warning',
                                'upsert' => 'primary',
                                default  => 'gray',
                            };
                        @endphp
                        <tr>
                            <td class="mono text-gray-400">#{{ $ij->id }}</td>
                            <td><x-filament::badge color="info" size="sm">{{ \App\Services\DataTransfer\ModuleRegistry::modules()[$ij->module] ?? $ij->module }}</x-filament::badge></td>
                            <td class="fname mono" title="{{ $ij->file_name }}">{{ \Illuminate\Support\Str::limit($ij->file_name, 30) }}</td>
                            <td><x-filament::badge :color="$modeColor" size="sm">{{ strtoupper($ij->mode) }}</x-filament::badge></td>
                            <td class="font-medium text-gray-950 dark:text-white">{{ number_format($ij->total_rows) }}</td>
                            <td class="num-ok">{{ number_format($ij->success_rows) }}</td>
                            <td class="{{ $ij->failed_rows > 0 ? 'num-err' : 'num-err zero' }}">{{ number_format($ij->failed_rows) }}</td>
                            <td><x-filament::badge :color="$stColor" size="sm">{{ $stLabel }}</x-filament::badge></td>
                            <td class="text-xs text-gray-500 dark:text-gray-400">{{ $ij->created_at?->format('d/m H:i') }}</td>
                            <td style="text-align:center;">
                                <div class="flex items-center justify-center gap-0.5">
                                    @if(in_array($ij->status, ['previewing']))
                                    <a href="{{ \App\Filament\Pages\ImportPreviewPage::getUrl(['job' => $ij->id]) }}" class="dt-act" title="Xem preview">
                                        <x-filament::icon icon="heroicon-o-eye" />
                                    </a>
                                    @endif

                                    @if(in_array($ij->status, ['completed', 'failed']))
                                    <a href="{{ \App\Filament\Pages\ImportResultPage::getUrl(['job' => $ij->id]) }}" class="dt-act" title="Xem kết quả">
                                        <x-filament::icon icon="heroicon-o-document-magnifying-glass" />
                                    </a>
                                    @endif

                                    @if($ij->status === 'failed' && $ij->failed_rows > 0)
                                    <a href="{{ \App\Filament\Pages\ImportResultPage::getUrl(['job' => $ij->id]) }}" class="dt-act" title="Xem lỗi" style="color:#ef4444;">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="padding:2.5rem 1rem; text-align:center;">
                                <div class="mx-auto flex items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800" style="width:40px;height:40px;">
                                    <x-filament::icon icon="heroicon-o-inbox" class="text-gray-300 dark:text-gray-600" />
                                </div>
                                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Chưa có import nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ 4. Module Reference (collapsible) ══════════════════ --}}
        <details class="dt-card group">
            <summary class="dt-card-head cursor-pointer select-none list-none">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-information-circle" class="fi-wi-stats-overview-stat-icon text-gray-400" />
                    Nhóm dữ liệu theo Module
                    <svg class="shrink-0 transition-transform group-open:rotate-90" style="width:14px;height:14px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                </h3>
            </summary>
            <div style="padding:1.5rem;">
                <div class="dt-module-grid">
                    @foreach(\App\Services\DataTransfer\ModuleRegistry::modules() as $mk => $mn)
                    <div class="space-y-2">
                        <h4 class="font-semibold text-gray-950 dark:text-white text-sm">{{ $mn }}</h4>
                        @foreach(\App\Services\DataTransfer\ModuleRegistry::fieldGroups($mk) as $gk => $group)
                        <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-3">
                            <div class="font-medium text-xs text-gray-700 dark:text-gray-300 mb-1">{{ $group['label'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ implode(', ', $group['fields']) }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
        </details>
    </div>
</x-filament-panels::page>
