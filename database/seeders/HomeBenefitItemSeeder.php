<?php

namespace Database\Seeders;

use App\Models\HomeBenefitItem;
use Illuminate\Database\Seeder;

class HomeBenefitItemSeeder extends Seeder
{
    public function run(): void
    {
        if (HomeBenefitItem::exists()) {
            $this->command?->info('Home benefit items already exist, skipping.');
            return;
        }

        $items = [
            [
                'title'      => 'Chính hãng 100%',
                'subtitle'   => 'Nhập khẩu trực tiếp',
                'icon_type'  => 'heroicon',
                'icon_name'  => 'shield-check',
                'icon_color' => 'text-primary-600',
                'bg_color'   => 'bg-primary-100',
                'sort_order' => 0,
            ],
            [
                'title'      => 'Lắp đặt miễn phí',
                'subtitle'   => 'Kỹ thuật chuyên nghiệp',
                'icon_type'  => 'heroicon',
                'icon_name'  => 'zap',
                'icon_color' => 'text-accent-600',
                'bg_color'   => 'bg-accent-100',
                'sort_order' => 1,
            ],
            [
                'title'      => 'Bảo hành 3-5 năm',
                'subtitle'   => 'Theo chính sách hãng',
                'icon_type'  => 'heroicon',
                'icon_name'  => 'clock',
                'icon_color' => 'text-success-600',
                'bg_color'   => 'bg-success-500/10',
                'sort_order' => 2,
            ],
            [
                'title'      => 'Giá tốt nhất',
                'subtitle'   => 'Cam kết cạnh tranh',
                'icon_type'  => 'heroicon',
                'icon_name'  => 'badge-dollar-sign',
                'icon_color' => 'text-warning-600',
                'bg_color'   => 'bg-warning-500/10',
                'sort_order' => 3,
            ],
        ];

        foreach ($items as $item) {
            HomeBenefitItem::create(array_merge($item, ['is_active' => true]));
        }

        $this->command?->info('Created 4 default home benefit items.');
    }
}
