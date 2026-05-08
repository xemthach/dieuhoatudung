<?php

namespace App\Enums;

enum AIContentJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Processing => 'Đang xử lý',
            self::Completed => 'Hoàn thành',
            self::Failed => 'Thất bại',
            self::Reviewed => 'Đã duyệt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Reviewed => 'primary',
        };
    }
}
