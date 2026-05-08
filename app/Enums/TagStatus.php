<?php

namespace App\Enums;

enum TagStatus: string
{
    case Candidate = 'candidate';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'Ứng viên',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Candidate => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
