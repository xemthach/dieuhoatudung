<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Review => 'Chờ duyệt',
            self::Published => 'Đã xuất bản',
            self::Archived => 'Lưu trữ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Review => 'warning',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }
}
