<div wire:poll.10s="refreshStatus">
    <div class="cd-row"><span>Worker</span><span class="cd-badge badge-{{ !empty($status['worker_disabled']) ? 'disabled' : 'active' }}">{{ $status['worker_label'] ?? 'Không rõ' }}</span></div>
    <div class="cd-row c-info"><span>Đang chạy</span><span class="cd-val c-info">{{ $status['running_jobs'] ?? 0 }}</span></div>
    <div class="cd-row"><span>Đang chờ</span><span class="cd-val">{{ $status['pending_jobs'] ?? 0 }}</span></div>
    <div class="cd-row" style="{{ ($status['waiting_review'] ?? 0) > 0 ? 'color:#d97706' : '' }}"><span>Chờ duyệt</span><span class="cd-val">{{ $status['waiting_review'] ?? 0 }}</span></div>
    <div class="cd-row" style="{{ ($status['blocked_jobs'] ?? 0) > 0 ? 'color:#d97706' : '' }}"><span>Bị chặn</span><span class="cd-val">{{ $status['blocked_jobs'] ?? 0 }}</span></div>
</div>
