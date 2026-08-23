<?php

namespace App\Enums;

enum BtuCalculationMethod: string
{
    case AREA = 'area';
    case VOLUME = 'volume';

    public function label(): string
    {
        return match ($this) {
            self::AREA => 'Theo diện tích',
            self::VOLUME => 'Theo thể tích',
        };
    }
}
