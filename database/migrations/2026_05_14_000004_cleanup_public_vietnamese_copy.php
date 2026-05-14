<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landing_sections')) {
            DB::table('landing_sections')
                ->where('section_type', 'advisory_content')
                ->where(function ($query) {
                    $query->where('content', 'like', '%Dieu hoa tu dung%')
                        ->orWhere('content', 'like', '%Cong suat dieu hoa%')
                        ->orWhere('content', 'like', '%Khong gian can%');
                })
                ->update([
                    'content' => $this->advisoryContent(),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('quote_commitment_blocks')) {
            DB::table('quote_commitment_blocks')
                ->where('title', 'DYNAMIC DB BLOCK TITLE')
                ->update([
                    'title' => 'Cam kết kỹ thuật & triển khai',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('quote_commitment_items')) {
            DB::table('quote_commitment_items')
                ->where('title', 'DYNAMIC ITEM 1 CONTENT')
                ->update([
                    'title' => 'Tư vấn công suất & phương án kỹ thuật theo thực tế công trình',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('landing_sections')) {
            $replacements = [
                'du lieu khao sat thuc te' => 'dữ liệu khảo sát thực tế',
                'Cong suat' => 'Công suất',
                'cong suat' => 'công suất',
                'du lieu' => 'dữ liệu',
                'he thong' => 'hệ thống',
            ];

            foreach ($replacements as $from => $to) {
                DB::table('landing_sections')
                    ->where('content', 'like', '%'.$from.'%')
                    ->update([
                        'content' => DB::raw("REPLACE(content, ".DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')'),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Data cleanup migration; no destructive rollback.
    }

    private function advisoryContent(): string
    {
        return '<h3>Điều hòa tủ đứng là gì?</h3>
<p>Điều hòa tủ đứng là nhóm điều hòa lắp sàn, thường được dùng cho không gian rộng như nhà hàng, hội trường, văn phòng, showroom hoặc nhà xưởng. Công suất, điện áp, gas lạnh và các thông số kỹ thuật cần được đối chiếu theo dữ liệu sản phẩm đã lưu trong hệ thống.</p>

<h3>Khi nào nên chọn điều hòa tủ đứng?</h3>
<ul>
<li>Không gian cần lắp đặt nhanh và không phù hợp giấu trần</li>
<li>Trần nhà cao, không gian mở</li>
<li>Cần công suất lạnh lớn, phân phối gió đều</li>
<li>Không muốn khoan tường hoặc lắp đặt phức tạp</li>
</ul>

<h3>Cách tính công suất BTU phù hợp</h3>
<p>Công suất điều hòa cần được tính toán dựa trên diện tích, chiều cao trần, loại công trình, số người, nắng trực tiếp và thiết bị tỏa nhiệt. Nếu thiếu dữ liệu khảo sát, không nên đưa ra kết quả BTU cụ thể.</p>';
    }
};
