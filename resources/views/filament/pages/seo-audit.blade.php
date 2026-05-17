<x-filament-panels::page>

    {{-- Score & Progress --}}
    <div style="margin-bottom:24px; padding:24px; border-radius:16px; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:24px">
            <div style="flex-shrink:0; width:180px">
                <div style="font-size:40px; font-weight:900; color:{{ $seoScore >= 80 ? '#16a34a' : ($seoScore >= 50 ? '#ca8a04' : '#dc2626') }}">
                    {{ $seoScore }}/100
                </div>
                <p style="margin-top:4px; font-size:14px; font-weight:500; color:#6b7280">Điểm SEO tổng quan</p>
            </div>
            <div style="flex:1; min-width:200px; border-left:1px solid #e5e7eb; padding-left:24px">
                <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; margin-bottom:8px">
                    <span style="color:#6b7280">Sức khỏe SEO</span>
                    <span style="color:{{ $seoScore >= 80 ? '#16a34a' : ($seoScore >= 50 ? '#ca8a04' : '#dc2626') }}">{{ $seoScore >= 80 ? 'Tốt' : ($seoScore >= 50 ? 'Cần cải thiện' : 'Kém') }}</span>
                </div>
                <div style="width:100%; background:#e5e7eb; border-radius:99px; height:12px">
                    <div style="height:12px; border-radius:99px; width:{{ $seoScore }}%; background:{{ $seoScore >= 80 ? '#22c55e' : ($seoScore >= 50 ? '#eab308' : '#ef4444') }}; transition:width 0.5s"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px">
        {{-- Total --}}
        <div style="border-radius:16px; background:#fff; border:1px solid #e5e7eb; padding:20px; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
            <div style="display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#f3f4f6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#4b5563" stroke-width="1.5" viewBox="0 0 24 24" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <p style="font-size:24px; font-weight:700; color:#111827">{{ $totalIssues }}</p>
                    <p style="font-size:14px; font-weight:500; color:#6b7280">Tổng lỗi</p>
                </div>
            </div>
        </div>
        {{-- Critical --}}
        <div style="border-radius:16px; background:#fef2f2; border:1px solid #fecaca; padding:20px; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
            <div style="display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#fee2e2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#dc2626" stroke-width="1.5" viewBox="0 0 24 24" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p style="font-size:24px; font-weight:700; color:#b91c1c">{{ $totalCritical }}</p>
                    <p style="font-size:14px; font-weight:500; color:#dc2626">Lỗi Critical</p>
                </div>
            </div>
        </div>
        {{-- Warning --}}
        <div style="border-radius:16px; background:#fffbeb; border:1px solid #fde68a; padding:20px; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
            <div style="display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#fef3c7">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#ca8a04" stroke-width="1.5" viewBox="0 0 24 24" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p style="font-size:24px; font-weight:700; color:#a16207">{{ $totalWarning }}</p>
                    <p style="font-size:14px; font-weight:500; color:#ca8a04">Cảnh báo</p>
                </div>
            </div>
        </div>
        {{-- Notice --}}
        <div style="border-radius:16px; background:#eff6ff; border:1px solid #bfdbfe; padding:20px; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
            <div style="display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#dbeafe">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#2563eb" stroke-width="1.5" viewBox="0 0 24 24" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p style="font-size:24px; font-weight:700; color:#1d4ed8">{{ $totalNotice }}</p>
                    <p style="font-size:14px; font-weight:500; color:#2563eb">Ghi nhận</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div style="margin-bottom:24px; display:flex; flex-wrap:wrap; align-items:center; gap:12px; background:#fff; padding:16px; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
        <div style="position:relative; width:256px">
            <div style="position:absolute; inset-block:0; left:0; display:flex; align-items:center; padding-left:12px; pointer-events:none">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Tìm tên bài, sản phẩm..." style="width:100%; border-radius:8px; border:1px solid #d1d5db; padding:8px 12px 8px 40px; font-size:14px; outline:none; color:#111827">
        </div>
        <select wire:model.live="filterEntity" style="border-radius:8px; border:1px solid #d1d5db; padding:8px 32px 8px 12px; font-size:14px; outline:none; color:#111827; background:#fff">
            @foreach ($this->getEntityTypes() as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterSeverity" style="border-radius:8px; border:1px solid #d1d5db; padding:8px 32px 8px 12px; font-size:14px; outline:none; color:#111827; background:#fff">
            @foreach ($this->getSeverityTypes() as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        <div style="margin-left:auto; font-size:14px; color:#6b7280; font-weight:500">
            Hiển thị <strong>{{ count($groupedIssues) }}</strong>/<strong>{{ $totalFilteredGroups }}</strong> mục
        </div>
    </div>

    {{-- Table --}}
    <div style="overflow:hidden; border-radius:16px; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.05); background:#fff">
        @if ($errorMessage)
            <div style="padding:24px; background:#fef2f2; display:flex; align-items:flex-start; gap:16px">
                <div style="flex-shrink:0; padding:8px; background:#fee2e2; border-radius:50%">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="#dc2626" stroke-width="1.5" viewBox="0 0 24 24" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 style="font-size:18px; font-weight:700; color:#991b1b">Lỗi khi chạy Audit</h3>
                    <p style="margin-top:4px; font-size:14px; color:#b91c1c; background:rgba(255,255,255,0.5); padding:12px; border-radius:6px; font-family:monospace">{{ $errorMessage }}</p>
                </div>
            </div>
        @elseif (empty($groupedIssues))
            <div style="padding:64px; text-align:center">
                <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:#f0fdf4; margin-bottom:16px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" stroke="#22c55e" stroke-width="1.5" viewBox="0 0 24 24" style="width:48px;height:48px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p style="font-size:20px; font-weight:700; color:#1f2937">Không phát hiện lỗi SEO</p>
                <p style="font-size:14px; color:#6b7280; margin-top:8px">Hệ thống của bạn đang được tối ưu rất tốt.</p>
            </div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%; border-collapse:collapse">
                    <thead>
                        <tr style="background:#f9fafb">
                            <th style="padding:14px 20px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; width:140px; border-bottom:1px solid #e5e7eb">Loại</th>
                            <th style="padding:14px 20px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; border-bottom:1px solid #e5e7eb">Tên</th>
                            <th style="padding:14px 20px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; width:110px; border-bottom:1px solid #e5e7eb">Mức độ</th>
                            <th style="padding:14px 20px; text-align:left; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; width:150px; border-bottom:1px solid #e5e7eb">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupedIssues as $group)
                            @php
                                $maxSev = $group['max_severity'];
                                $sevBg = match($maxSev) { 'critical' => '#fef2f2', 'warning' => '#fffbeb', default => '#eff6ff' };
                                $sevText = match($maxSev) { 'critical' => '#b91c1c', 'warning' => '#a16207', default => '#1d4ed8' };
                                $sevBorder = match($maxSev) { 'critical' => 'rgba(220,38,38,0.2)', 'warning' => 'rgba(202,138,4,0.2)', default => 'rgba(37,99,235,0.2)' };
                                $sevDot = match($maxSev) { 'critical' => '#ef4444', 'warning' => '#eab308', default => '#3b82f6' };
                            @endphp
                            
                            <tbody x-data="{ expanded: false }">
                                {{-- Main Row --}}
                                <tr style="cursor:pointer; border-bottom:1px solid #f3f4f6" @click="expanded = !expanded" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <td style="padding:16px 20px; white-space:nowrap">
                                        <span style="display:inline-flex; align-items:center; border-radius:6px; background:#f9fafb; padding:4px 10px; font-size:12px; font-weight:500; color:#4b5563; border:1px solid rgba(107,114,128,0.1)">
                                            {{ $group['entity'] }}
                                        </span>
                                    </td>
                                    <td style="padding:16px 20px">
                                        <div style="display:flex; align-items:center; gap:10px">
                                            <div style="width:8px; height:8px; flex-shrink:0; border-radius:50%; background:{{ $sevDot }}"></div>
                                            <span style="font-size:14px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:400px" title="{{ $group['name'] }}">{{ $group['name'] }}</span>
                                            <span style="flex-shrink:0; display:inline-flex; align-items:center; border-radius:99px; background:#f3f4f6; padding:2px 8px; font-size:12px; font-weight:500; color:#4b5563">
                                                {{ count($group['issues']) }} lỗi
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;flex-shrink:0;transition:transform 0.2s" :style="expanded ? 'transform:rotate(180deg)' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </div>
                                    </td>
                                    <td style="padding:16px 20px; white-space:nowrap">
                                        <span style="display:inline-flex; align-items:center; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:500; color:{{ $sevText }}; background:{{ $sevBg }}; border:1px solid {{ $sevBorder }}">
                                            {{ ucfirst($maxSev) }}
                                        </span>
                                    </td>
                                    <td style="padding:16px 20px; white-space:nowrap" @click.stop>
                                        <div style="display:flex; align-items:center; gap:8px">
                                            <a href="{{ $group['edit_url'] }}" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; font-size:12px; font-weight:600; color:#fff; background:#2563eb; border-radius:8px; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,0.1)" onmouseover="this.style.background='#3b82f6'" onmouseout="this.style.background='#2563eb'">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Sửa
                                            </a>
                                            @if(!empty($group['public_url']))
                                                <a href="{{ $group['public_url'] }}" target="_blank" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; color:#9ca3af; background:#fff; border:1px solid #e5e7eb; border-radius:8px; text-decoration:none" title="Xem trang">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expanded Details --}}
                                <tr x-show="expanded" x-collapse x-cloak>
                                    <td colspan="4" style="padding:16px 20px; background:#fafafa">
                                        <div style="padding-left:40px; padding-right:16px">
                                            <h4 style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px">Chi tiết lỗi & Hướng xử lý</h4>
                                            <div style="display:flex; flex-direction:column; gap:10px">
                                                @foreach($group['issues'] as $issue)
                                                    <div style="display:flex; align-items:flex-start; gap:12px; padding:12px; background:#fff; border-radius:8px; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.05)">
                                                        <div style="flex-shrink:0; margin-top:2px">
                                                            @if($issue['severity'] === 'critical')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            @elseif($issue['severity'] === 'warning')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#eab308" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                            @else
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            @endif
                                                        </div>
                                                        <div style="flex:1; min-width:0">
                                                            <p style="font-size:14px; font-weight:500; color:#111827">{{ $issue['message'] }}</p>
                                                            <div style="margin-top:4px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px">
                                                                <p style="font-size:12px; color:#6b7280">Gợi ý: <span style="font-weight:500; color:#374151">{{ $issue['suggestion'] ?? 'Cập nhật nội dung' }}</span></p>
                                                                @if(!empty($issue['action']))
                                                                    <button wire:click="quickFix('{{ $issue['action'] }}', '{{ addslashes($group['name']) }}')" style="display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:500; color:#2563eb; background:none; border:none; cursor:pointer">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                                                        Quick Fix
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-filament-panels::page>
