<?php

namespace Database\Seeders;

use App\Models\QuoteCommitmentBlock;
use App\Models\QuoteCommitmentItem;
use Illuminate\Database\Seeder;

class QuoteCommitmentSeeder extends Seeder
{
    public function run(): void
    {
        if (QuoteCommitmentBlock::exists()) {
            $this->command?->info('Quote commitment blocks already exist, skipping.');
            return;
        }

        $block = QuoteCommitmentBlock::create([
            'title'       => 'Cam kết kỹ thuật & triển khai',
            'description' => null,
            'is_active'   => true,
        ]);

        $items = [
            ['title' => 'Tư vấn công suất & phương án kỹ thuật theo thực tế công trình', 'icon_name' => 'settings'],
            ['title' => 'Báo giá chi tiết, minh bạch theo từng hạng mục',                'icon_name' => 'file-text'],
            ['title' => 'Khảo sát & đề xuất giải pháp tối ưu vận hành dài hạn',          'icon_name' => 'map-pin'],
            ['title' => 'Thi công đúng tiêu chuẩn kỹ thuật HVAC',                        'icon_name' => 'wrench'],
            ['title' => 'Bảo hành chính hãng, hỗ trợ kỹ thuật sau lắp đặt',              'icon_name' => 'shield-check'],
        ];

        foreach ($items as $i => $item) {
            QuoteCommitmentItem::create([
                'quote_commitment_block_id' => $block->id,
                'title'      => $item['title'],
                'icon_type'  => 'heroicon',
                'icon_name'  => $item['icon_name'],
                'icon_color' => 'text-green-500',
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }

        $this->command?->info('Created default quote commitment block with 5 items.');
    }
}
