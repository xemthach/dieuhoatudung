<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MailLog extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'template_key',
        'to_email',
        'subject',
        'status',
        'status_code',
        'response_excerpt',
        'error_message',
        'related_type',
        'related_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // ── Status constants ───────────────────────────────────────────────
    const STATUS_SENT    = 'sent';
    const STATUS_FAILED  = 'failed';
    const STATUS_SKIPPED = 'skipped';

    /** Human-readable event key labels */
    public static function eventLabels(): array
    {
        return [
            'lead_admin'        => 'Lead — Admin',
            'lead_customer'     => 'Lead — Khách hàng',
            'quote_admin'       => 'Báo giá — Admin',
            'quote_customer'    => 'Báo giá — Khách hàng',
            'review_admin'      => 'Đánh giá — Admin',
            'review_customer'   => 'Đánh giá — Khách hàng',
            'question_admin'    => 'Câu hỏi — Admin',
            'question_customer' => 'Câu hỏi — Khách hàng',
            'system_alert'      => 'Cảnh báo hệ thống',
            'test'              => 'Test email',
        ];
    }

    /** Filament badge colors for status column */
    public static function statusColors(): array
    {
        return [
            'sent'    => 'success',
            'failed'  => 'danger',
            'skipped' => 'warning',
            'pending' => 'info',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeByEvent(Builder $query, string $event): Builder
    {
        return $query->where('event_key', $event);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SKIPPED);
    }

    /** Stats array used by the dashboard widget */
    public static function stats(?int $days = 7): array
    {
        $since = now()->subDays($days);
        $total   = static::where('created_at', '>=', $since)->count();
        $sent    = static::sent()->where('created_at', '>=', $since)->count();
        $failed  = static::failed()->where('created_at', '>=', $since)->count();
        $skipped = static::skipped()->where('created_at', '>=', $since)->count();

        return compact('total', 'sent', 'failed', 'skipped');
    }
}
