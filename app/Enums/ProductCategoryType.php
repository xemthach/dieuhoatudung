<?php

namespace App\Enums;

enum ProductCategoryType: string
{
    case Main = 'main';
    case Brand = 'brand';
    case Btu = 'btu';
    case Technology = 'technology';
    case CoolingType = 'cooling_type';
    case Voltage = 'voltage';
    case UseCase = 'use_case';
    case PriceRange = 'price_range';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Danh mục chính',
            self::Brand => 'Theo thương hiệu',
            self::Btu => 'Theo công suất BTU',
            self::Technology => 'Theo công nghệ',
            self::CoolingType => 'Theo kiểu làm lạnh',
            self::Voltage => 'Theo điện áp',
            self::UseCase => 'Theo nhu cầu sử dụng',
            self::PriceRange => 'Theo tầm giá',
        };
    }
}
