<?php

namespace App\Enums;

enum AIContentJobStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedVerified = 'completed_verified';
    case CompletedWithWarnings = 'completed_with_warnings';
    case NeedsReview = 'needs_review';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Stuck = 'stuck';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Pending => 'Đang chờ',
            self::Queued => 'Đã vào queue',
            self::Processing => 'Đang xử lý',
            self::Completed => 'Hoàn thành',
            self::CompletedVerified => 'Hoàn thành đã xác minh',
            self::CompletedWithWarnings => 'Hoàn thành có cảnh báo',
            self::NeedsReview => 'Cần duyệt',
            self::Blocked => 'Bị chặn',
            self::Failed => 'Thất bại',
            self::Cancelled => 'Đã hủy',
            self::Stuck => 'Bị kẹt',
            self::Reviewed => 'Đã duyệt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'gray',
            self::Queued => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::CompletedVerified => 'success',
            self::CompletedWithWarnings => 'warning',
            self::NeedsReview => 'warning',
            self::Blocked => 'danger',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
            self::Stuck => 'danger',
            self::Reviewed => 'primary',
        };
    }
}
