<?php

namespace App\Enums;

enum TagType: string
{
    case Brand = 'brand';
    case Btu = 'btu';
    case Technology = 'technology';
    case UseCase = 'use_case';
    case Technical = 'technical';
    case Topic = 'topic';

    public function label(): string
    {
        return match ($this) {
            self::Brand => 'Thương hiệu',
            self::Btu => 'Công suất BTU',
            self::Technology => 'Công nghệ',
            self::UseCase => 'Nhu cầu sử dụng',
            self::Technical => 'Kỹ thuật',
            self::Topic => 'Chủ đề',
        };
    }
}
