@props([
    'state',
    'label' => null,
])

@php
    $normalized = strtoupper((string) $state);
    $color = match ($normalized) {
        'HEALTHY', 'READY', 'ONLINE', 'COMPLETED', 'SUCCESS' => 'success',
        'WARNING', 'NEEDS_CONFIGURATION', 'STALE', 'PROCESSING', 'RETRYING' => 'warning',
        'CRITICAL', 'ERROR', 'FAILED', 'BLOCKED' => 'danger',
        'DISABLED', 'OFFLINE', 'UNKNOWN' => 'gray',
        default => 'gray',
    };
    $text = $label ?? match ($normalized) {
        'HEALTHY' => 'Hoạt động tốt',
        'READY' => 'Sẵn sàng',
        'ONLINE' => 'Trực tuyến',
        'WARNING' => 'Cần chú ý',
        'NEEDS_CONFIGURATION' => 'Cần cấu hình',
        'CRITICAL' => 'Nghiêm trọng',
        'ERROR', 'FAILED' => 'Thất bại',
        'DISABLED' => 'Đã tắt',
        'OFFLINE' => 'Ngoại tuyến',
        'UNKNOWN' => 'Chưa xác định',
        default => str($normalized)->replace('_', ' ')->title()->toString(),
    };
@endphp

<x-filament::badge :color="$color" {{ $attributes }}>{{ $text }}</x-filament::badge>
