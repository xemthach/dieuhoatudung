<?php

namespace App\Enums;

enum PolicyType: string
{
    case Warranty = 'warranty';
    case Installation = 'installation';
    case Shipping = 'shipping';
    case Return = 'return';
    case Privacy = 'privacy';
    case Terms = 'terms';

    public function label(): string
    {
        return match ($this) {
            self::Warranty => 'Bảo hành',
            self::Installation => 'Lắp đặt',
            self::Shipping => 'Vận chuyển',
            self::Return => 'Đổi trả',
            self::Privacy => 'Bảo mật',
            self::Terms => 'Điều khoản',
        };
    }
}
