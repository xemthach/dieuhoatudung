<?php

namespace App\Console\Commands;

use App\Models\DealerService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedDealerServices extends Command
{
    protected $signature = 'dealer:seed-services {--apply}';

    protected $description = 'Create the default service taxonomy without creating Dealers.';

    public function handle(): int
    {
        foreach (['Tư vấn', 'Khảo sát', 'Lắp đặt', 'Bảo trì', 'Vệ sinh', 'Sửa chữa', 'Bảo hành', 'Giao hàng', 'Thi công ống đồng', 'Thi công VRV/VRF', 'Dự án'] as $name) {
            if ($this->option('apply')) {
                DealerService::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
            }
            $this->line($name);
        }

        return self::SUCCESS;
    }
}
