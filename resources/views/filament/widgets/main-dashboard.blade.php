<x-filament-widgets::widget>
<style>
.cd-wrap{display:flex;flex-direction:column;gap:1.5rem;width:100%}
.cd-grid{display:grid;grid-template-columns:repeat(1,1fr);gap:1.5rem}
@media(min-width:768px){.cd-grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1024px){.cd-grid{grid-template-columns:repeat(4,1fr)}}
.cd-grid2{display:grid;grid-template-columns:1fr;gap:1.5rem}
@media(min-width:1024px){.cd-grid2{grid-template-columns:1fr 1fr}}
.cd-card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid #e5e7eb;padding:20px;display:flex;flex-direction:column}
.dark .cd-card{background:#18181b;border-color:#3f3f46}
.cd-hdr{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-weight:600;font-size:1rem;color:#111827}
.dark .cd-hdr{color:#f4f4f5}
.cd-row{display:flex;justify-content:space-between;align-items:center;font-size:.875rem;padding:4px 0;color:#4b5563}
.dark .cd-row{color:#a1a1aa}
.cd-val{font-weight:600;color:#111827}.dark .cd-val{color:#f4f4f5}
.cd-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#f3f4f6;border-radius:8px;font-size:.875rem;font-weight:500;color:#374151;text-decoration:none;border:1px solid #e5e7eb;transition:background .2s}
.cd-btn:hover{background:#e5e7eb}
.dark .cd-btn{background:#27272a;border-color:#3f3f46;color:#e4e4e7}
.dark .cd-btn:hover{background:#3f3f46}
.cd-alert{display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:8px;background:#f9fafb;border:1px solid #e5e7eb;text-decoration:none;transition:background .2s}
.cd-alert:hover{background:#f3f4f6}
.dark .cd-alert{background:rgba(24,24,27,.5);border-color:#3f3f46}
.dark .cd-alert:hover{background:#27272a}
.cd-badge{display:inline-flex;align-items:center;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:500}
.badge-active{background:#dcfce7;color:#166534}.badge-disabled{background:#f3f4f6;color:#6b7280}
.badge-misconfigured{background:#fef3c7;color:#92400e}.badge-failed{background:#fee2e2;color:#991b1b}
.badge-rate_limited{background:#fef3c7;color:#92400e}
.dark .badge-active{background:rgba(22,101,52,.3);color:#86efac}
.dark .badge-disabled{background:rgba(63,63,70,.5);color:#a1a1aa}
.dark .badge-misconfigured{background:rgba(120,53,15,.3);color:#fcd34d}
.dark .badge-failed{background:rgba(127,29,29,.3);color:#fca5a5}
.c-danger{color:#dc2626}.c-warning{color:#d97706}.c-success{color:#16a34a}.c-info{color:#2563eb}
.dark .c-danger{color:#f87171}.dark .c-warning{color:#fbbf24}.dark .c-success{color:#4ade80}.dark .c-info{color:#60a5fa}
.cd-span2{grid-column:span 1}@media(min-width:768px){.cd-span2{grid-column:span 2}}
</style>

<div class="cd-wrap">
<div class="cd-grid">

{{-- LEADS --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg> Leads</div>
    <div class="cd-row"><span>Hôm nay</span><span class="cd-val">{{ $leads['today'] }}</span></div>
    <div class="cd-row"><span>Tuần này</span><span class="cd-val">{{ $leads['this_week'] }}</span></div>
    <div class="cd-row" style="{{ $leads['pending'] > 0 ? 'color:#d97706' : '' }}"><span>Chờ xử lý</span><span class="cd-val" style="{{ $leads['pending'] > 0 ? 'color:#d97706' : '' }}">{{ $leads['pending'] }}</span></div>
</div>

{{-- PRODUCTS --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg> Sản Phẩm</div>
    <div class="cd-row"><span>Tổng số</span><span class="cd-val">{{ $products['total'] }}</span></div>
    <div class="cd-row"><span>Thiếu SEO</span><span class="cd-val" style="{{ $products['missing_seo'] > 0 ? 'color:#dc2626' : '' }}">{{ $products['missing_seo'] }}</span></div>
    <div class="cd-row"><span>Thiếu Ảnh</span><span class="cd-val" style="{{ $products['missing_image'] > 0 ? 'color:#dc2626' : '' }}">{{ $products['missing_image'] }}</span></div>
    <div class="cd-row"><span>Đang sale</span><span class="cd-val">{{ $products['on_sale'] }}</span></div>
</div>

{{-- POSTS --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg> Bài Viết</div>
    <div class="cd-row"><span>Tổng bài</span><span class="cd-val">{{ $posts['total'] }}</span></div>
    <div class="cd-row"><span>Thiếu SEO</span><span class="cd-val" style="{{ $posts['missing_seo'] > 0 ? 'color:#d97706' : '' }}">{{ $posts['missing_seo'] }}</span></div>
    <div class="cd-row"><span>Bản nháp</span><span class="cd-val">{{ $posts['draft'] }}</span></div>
</div>

{{-- SEO HEALTH --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg> SEO Health</div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <span style="font-size:14px;color:#6b7280">Điểm SEO</span>
        <span style="font-size:24px;font-weight:700;color:{{ $seoHealth['score'] >= 80 ? '#16a34a' : ($seoHealth['score'] >= 50 ? '#d97706' : '#dc2626') }}">{{ $seoHealth['score'] }}</span>
    </div>
    <div class="cd-row c-danger"><span>Critical</span><span class="cd-val c-danger">{{ $seoHealth['critical'] }}</span></div>
    <div class="cd-row c-warning"><span>Warning</span><span class="cd-val c-warning">{{ $seoHealth['warning'] }}</span></div>
    <div class="cd-row c-info"><span>Notice</span><span class="cd-val c-info">{{ $seoHealth['notice'] }}</span></div>
</div>

{{-- R2 STORAGE --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/></svg> R2 Storage</div>
    <div class="cd-row"><span>Trạng thái</span><span class="cd-badge badge-{{ $r2Status['status'] }}">{{ $r2Status['label'] }}</span></div>
    <div class="cd-row"><span>Mode</span><span class="cd-val">{{ $r2Status['mode'] }}</span></div>
    <div class="cd-row" style="{{ $r2Status['failed_jobs'] > 0 ? 'color:#dc2626' : '' }}"><span>Lỗi đồng bộ</span><span class="cd-val" style="{{ $r2Status['failed_jobs'] > 0 ? 'color:#dc2626' : '' }}">{{ $r2Status['failed_jobs'] }}</span></div>
    <div class="cd-row" style="font-size:12px"><span>Last sync:</span><span>{{ $r2Status['last_sync'] ? \Carbon\Carbon::parse($r2Status['last_sync'])->diffForHumans() : 'N/A' }}</span></div>
</div>

{{-- AI STATUS --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg> AI Status</div>
    <div class="cd-row"><span>Trạng thái</span><span class="cd-badge badge-{{ $aiStatus['status'] }}">{{ $aiStatus['label'] }}</span></div>
    <div class="cd-row"><span>Providers</span><span class="cd-val">{{ $aiStatus['active_providers'] }}</span></div>
    <div class="cd-row c-info"><span>Pending Jobs</span><span class="cd-val c-info">{{ $aiStatus['pending_jobs'] }}</span></div>
    <div class="cd-row" style="{{ $aiStatus['failed_jobs'] > 0 ? 'color:#dc2626' : '' }}"><span>Failed Jobs</span><span class="cd-val" style="{{ $aiStatus['failed_jobs'] > 0 ? 'color:#dc2626' : '' }}">{{ $aiStatus['failed_jobs'] }}</span></div>
</div>

{{-- MAIL STATUS --}}
<div class="cd-card">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg> Mail</div>
    <div class="cd-row"><span>Trạng thái</span><span class="cd-badge badge-{{ $mailStatus['status'] }}">{{ $mailStatus['label'] }}</span></div>
    <div class="cd-row"><span>Provider</span><span class="cd-val">{{ ucfirst($mailStatus['provider']) }}</span></div>
    @if($mailStatus['status'] === 'active' && !empty($mailStatus['last_sent_at']))
    <div class="cd-row" style="font-size:12px"><span>Gửi gần nhất:</span><span>{{ \Carbon\Carbon::parse($mailStatus['last_sent_at'])->diffForHumans() }}</span></div>
    @endif
</div>

{{-- QUICK ACTIONS --}}
<div class="cd-card cd-span2" style="grid-column:span 1">
    <div class="cd-hdr"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg> Quick Actions</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        @foreach($quickActions as $action)
        <a href="{{ $action['url'] }}" class="cd-btn">{{ $action['label'] }}</a>
        @endforeach
    </div>
</div>

</div>

<div class="cd-grid2">

{{-- ALERTS --}}
<div class="cd-card">
    <div class="cd-hdr c-danger"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg> Cảnh Báo Cần Xử Lý</div>
    @if($alerts->count() > 0)
    <div style="display:flex;flex-direction:column;gap:12px;max-height:400px;overflow-y:auto">
        @foreach($alerts as $alert)
        <a href="{{ $alert['url'] }}" class="cd-alert">
            <div style="flex-shrink:0;margin-top:2px">
                @if($alert['severity'] === 'critical')
                <svg width="20" height="20" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                @else
                <svg width="20" height="20" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                @endif
            </div>
            <div style="display:flex;flex-direction:column;gap:2px">
                <span style="font-size:14px;font-weight:600;color:#111827">{{ $alert['title'] }}</span>
                <span style="font-size:12px;color:#6b7280">{{ $alert['description'] }}</span>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <div style="padding:32px 16px;text-align:center;color:#6b7280;font-size:14px;display:flex;flex-direction:column;align-items:center">
        <svg width="48" height="48" fill="none" stroke="#16a34a" stroke-width="1.5" viewBox="0 0 24 24" style="width:48px;height:48px;margin-bottom:12px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Hệ thống hoạt động tốt, không có cảnh báo nào!
    </div>
    @endif
</div>

{{-- LEADS LIST --}}
<div class="cd-card">
    <div class="cd-hdr" style="justify-content:space-between;width:100%">
        <div style="display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:20px;height:20px;color:#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3"/></svg>
            <span>Leads Mới Nhất</span>
        </div>
        <a href="{{ route('filament.admin.resources.leads.index') }}" style="font-size:12px;text-decoration:none;font-weight:600;color:#d97706">Xem tất cả &rarr;</a>
    </div>
    @if($leads['latest']->count() > 0)
    <div style="display:flex;flex-direction:column">
        @foreach($leads['latest'] as $index => $lead)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;{{ $index !== $leads['latest']->count() - 1 ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
            <div style="display:flex;flex-direction:column;max-width:60%">
                <span style="font-size:14px;font-weight:600" class="cd-val">{{ $lead->full_name }}</span>
                <span style="font-size:12px;color:#6b7280">{{ $lead->phone }} &bull; {{ $lead->created_at->diffForHumans() }}</span>
                @if($lead->product)
                <span style="font-size:12px;color:#d97706;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $lead->product->name }}</span>
                @endif
            </div>
            <div style="flex-shrink:0">
                @php $badgeClass = 'badge-' . $lead->status->value; @endphp
                <span class="cd-badge {{ $badgeClass }}">{{ $lead->status->label() }}</span>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div style="padding:32px 16px;text-align:center;color:#6b7280;font-size:14px">Chưa có lead nào trong hệ thống.</div>
    @endif
</div>

</div>
</div>
</x-filament-widgets::widget>
