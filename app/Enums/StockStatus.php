<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case PreOrder = 'pre_order';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Còn hàng',
            self::OutOfStock => 'Hết hàng',
            self::PreOrder => 'Đặt trước',
            self::Contact => 'Liên hệ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::OutOfStock => 'danger',
            self::PreOrder => 'warning',
            self::Contact => 'info',
        };
    }
}
