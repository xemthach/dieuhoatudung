<?php

namespace App\Enums;

enum QuoteRequestStatus: string
{
    case New      = 'new';
    case Contacted = 'contacted';
    case Quoted   = 'quoted';
    case Won      = 'won';
    case Lost     = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New       => 'Mới',
            self::Contacted => 'Đã liên hệ',
            self::Quoted    => 'Đã báo giá',
            self::Won       => 'Chốt deal',
            self::Lost      => 'Không thành',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New       => 'info',
            self::Contacted => 'warning',
            self::Quoted    => 'primary',
            self::Won       => 'success',
            self::Lost      => 'danger',
        };
    }
}
