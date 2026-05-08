<?php

namespace App\Enums;

enum SearchIntent: string
{
    case Informational = 'informational';
    case Commercial = 'commercial';
    case Transactional = 'transactional';
    case Navigational = 'navigational';

    public function label(): string
    {
        return match ($this) {
            self::Informational => 'Thông tin',
            self::Commercial => 'Thương mại',
            self::Transactional => 'Giao dịch',
            self::Navigational => 'Điều hướng',
        };
    }
}
