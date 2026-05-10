<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if slides already exist
        if (HeroSlide::withTrashed()->exists()) {
            $this->command?->info('Hero slides already exist, skipping.');
            return;
        }

        HeroSlide::create([
            'title'              => setting('general.site_name', 'Điều Hòa Tủ Đứng'),
            'highlight_text'     => 'Chính Hãng',
            'subtitle'           => 'Giải pháp làm mát chuyên nghiệp cho không gian lớn. Đa dạng thương hiệu, công suất phù hợp mọi nhu cầu. Miễn phí lắp đặt, bảo hành chính hãng toàn quốc.',
            'description'        => null,
            'text_color'         => '#ffffff',
            'text_align'         => 'center',
            'content_position'   => 'center',
            'background_type'    => 'gradient',
            'gradient_from'      => '#1e3a5f',
            'gradient_to'        => '#0f172a',
            'overlay_enabled'    => false,
            'overlay_color'      => '#000000',
            'overlay_opacity'    => 0,
            'cta_primary_text'   => 'Nhận báo giá miễn phí',
            'cta_primary_url'    => '/bao-gia',
            'cta_primary_style'  => 'accent',
            'cta_secondary_text' => 'Xem sản phẩm',
            'cta_secondary_url'  => '/san-pham',
            'cta_secondary_style'=> 'outline',
            'open_in_new_tab'    => false,
            'animation_type'     => 'fade',
            'duration_ms'        => 6000,
            'sort_order'         => 0,
            'is_active'          => true,
        ]);

        $this->command?->info('Default hero slide created.');
    }
}
