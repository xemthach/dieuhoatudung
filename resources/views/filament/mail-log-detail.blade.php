{{-- filament/mail-log-detail.blade.php --}}
<div class="space-y-5">
    {{-- Status banner --}}
    @php
        $statusColors = [
            'sent'    => 'bg-green-50 border-green-300 text-green-800',
            'failed'  => 'bg-red-50 border-red-300 text-red-800',
            'skipped' => 'bg-amber-50 border-amber-300 text-amber-800',
        ];
        $statusBannerClass = $statusColors[$log->status] ?? 'bg-gray-50 border-gray-300 text-gray-800';
    @endphp

    <div class="rounded-lg border px-4 py-3 font-semibold {{ $statusBannerClass }}">
        Trạng thái:
        <span class="uppercase tracking-wide">{{ $log->status }}</span>
        @if($log->sent_at)
            &mdash; Gửi lúc {{ $log->sent_at->format('d/m/Y H:i:s') }}
        @endif
    </div>

    {{-- Core fields --}}
    <table class="w-full border-collapse text-sm">
        <tbody>
            @foreach ([
                'Sự kiện'     => \App\Models\MailLog::eventLabels()[$log->event_key] ?? ($log->event_key ?: '—'),
                'Template'    => $log->template_key ?: '—',
                'Provider'    => $log->provider,
                'Gửi tới'     => $log->to_email,
                'Tiêu đề'     => $log->subject,
                'HTTP Status' => $log->status_code ?: '—',
                'Liên kết'    => $log->related_type ? class_basename($log->related_type) . ' #' . $log->related_id : '—',
                'Tạo lúc'     => $log->created_at?->format('d/m/Y H:i:s'),
            ] as $label => $value)
            <tr class="border-b border-gray-100">
                <td class="w-36 py-2 pr-3 font-semibold text-gray-500">{{ $label }}</td>
                <td class="py-2 text-gray-800 break-all">{{ $value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Error message --}}
    @if($log->error_message)
    <div class="rounded-lg bg-red-50 border border-red-200 p-4">
        <p class="text-xs font-semibold uppercase text-red-500 mb-1">Lỗi / Lý do bị bỏ qua</p>
        <p class="text-sm text-red-700 font-mono break-all">{{ $log->error_message }}</p>
    </div>
    @endif

    {{-- Response excerpt --}}
    @if($log->response_excerpt)
    <div>
        <p class="text-xs font-semibold uppercase text-gray-400 mb-1">Response từ Provider</p>
        <pre class="rounded bg-gray-50 border border-gray-200 p-3 text-xs overflow-x-auto whitespace-pre-wrap">{{ $log->response_excerpt }}</pre>
    </div>
    @endif
</div>
