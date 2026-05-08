<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportGreeCatalogue extends Command
{
    protected $signature = 'app:import-gree-catalogue {file : Path to the extracted JSON file}';
    protected $description = 'Import and normalize Gree products from extracted JSON catalogue';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!File::exists($filePath)) {
            $this->error("File không tồn tại: {$filePath}");
            return 1;
        }

        $json = File::get($filePath);
        $products = json_decode($json, true);

        if (!is_array($products)) {
            $this->error("JSON không hợp lệ.");
            return 1;
        }

        $brand = Brand::firstOrCreate(
            ['slug' => 'gree'],
            ['name' => 'Gree']
        );

        $this->info("Bắt đầu import " . count($products) . " sản phẩm...");
        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($products as $item) {
            // Validate required
            if (empty($item['model_code']) || empty($item['name'])) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // 1. Phân loại Category
            $categorySlug = $this->mapCategorySlug($item['category'] ?? '');
            $categoryName = $this->getCategoryName($categorySlug);
            
            $category = ProductCategory::firstOrCreate(
                ['slug' => $categorySlug],
                ['name' => $categoryName, 'is_active' => true]
            );

            // 2. Chuẩn hóa BTU
            $btu = (int) preg_replace('/[^0-9]/', '', (string) ($item['btu'] ?? '0'));
            
            // 3. Tính toán diện tích (Area)
            $recommendedArea = $this->calculateArea($btu);

            // 4. Chuẩn hóa Inverter
            $inverter = false;
            $invStr = strtolower((string) ($item['inverter'] ?? ''));
            if ($invStr === 'true' || str_contains($invStr, 'inverter') || $item['inverter'] === true) {
                $inverter = true;
            }

            // 5. Chuẩn hóa Cooling Type (1 chiều / 2 chiều)
            $coolingTypeStr = strtolower((string) ($item['cooling_type'] ?? ''));
            $coolingType = null;
            if (str_contains($coolingTypeStr, 'cooling only') || str_contains($coolingTypeStr, '1 chiều')) {
                $coolingType = '1 chiều';
            } elseif (str_contains($coolingTypeStr, 'heat pump') || str_contains($coolingTypeStr, '2 chiều')) {
                $coolingType = '2 chiều';
            } else {
                $coolingType = $item['cooling_type'] ?? '1 chiều'; // Mặc định nếu không rõ
            }

            // 6. Chuẩn hóa Voltage
            $voltageStr = strtolower((string) ($item['voltage'] ?? ''));
            $voltage = null;
            if (str_contains($voltageStr, '380') || str_contains($voltageStr, '3 pha') || str_contains($voltageStr, '3 phase')) {
                $voltage = '3 pha';
            } elseif (str_contains($voltageStr, '220') || str_contains($voltageStr, '1 pha') || str_contains($voltageStr, '1 phase')) {
                $voltage = '1 pha';
            } else {
                $voltage = $item['voltage'] ?? null;
            }

            // 7. Tạo specs_json
            $specs = [];
            if ($btu > 0) $specs['Công suất'] = "{$btu} BTU";
            if ($voltage) $specs['Điện áp'] = $voltage;
            if (!empty($item['refrigerant_gas'])) $specs['Gas'] = $item['refrigerant_gas'];
            if (!empty($item['power_consumption'])) $specs['Điện năng tiêu thụ'] = $item['power_consumption'];
            if (!empty($item['noise_level'])) $specs['Độ ồn'] = $item['noise_level'];
            if (!empty($item['airflow'])) $specs['Lưu lượng gió'] = $item['airflow'];

            // 8. Import vào bảng products
            $slug = Str::slug($item['name'] . '-' . $item['model_code']);
            
            Product::updateOrCreate(
                ['model_code' => $item['model_code']],
                [
                    'name' => $item['name'],
                    'slug' => $slug,
                    'sku' => $item['model_code'],
                    'brand_id' => $brand->id,
                    'product_category_id' => $category->id,
                    'btu' => $btu > 0 ? $btu : null,
                    'inverter' => $inverter,
                    'cooling_type' => $coolingType,
                    'voltage' => $voltage,
                    'refrigerant_gas' => $item['refrigerant_gas'] ?? null,
                    'power_consumption' => $item['power_consumption'] ?? null,
                    'airflow' => $item['airflow'] ?? null,
                    'noise_level' => $item['noise_level'] ?? null,
                    'indoor_dimensions' => $item['indoor_dimensions'] ?? null,
                    'outdoor_dimensions' => $item['outdoor_dimensions'] ?? null,
                    'weight' => $item['weight'] ?? null,
                    'recommended_area' => $recommendedArea,
                    'specs_json' => $specs,
                    'is_active' => true,
                    'stock_status' => 'contact', // App\Enums\StockStatus::Contact có thể tương ứng với string 'contact' hoặc enum trong DB
                ]
            );

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Hoàn tất! Đã import/cập nhật {$imported} sản phẩm. Bỏ qua {$skipped} sản phẩm lỗi.");
        
        return 0;
    }

    protected function mapCategorySlug(string $type): string
    {
        $t = strtolower(trim($type));
        if (str_contains($t, 'tủ đứng') || str_contains($t, 'floor standing') || str_contains($t, 'cabinet') || str_contains($t, 'tower')) {
            return 'dieu-hoa-tu-dung';
        }
        if (str_contains($t, 'treo tường') || str_contains($t, 'wall mounted') || str_contains($t, 'split')) {
            return 'dieu-hoa-treo-tuong';
        }
        if (str_contains($t, 'cassette') || str_contains($t, '4-way')) {
            return 'dieu-hoa-cassette';
        }
        if (str_contains($t, 'duct') || str_contains($t, 'âm trần') || str_contains($t, 'concealed')) {
            return 'dieu-hoa-am-tran';
        }
        if (str_contains($t, 'vrf') || str_contains($t, 'vrv') || str_contains($t, 'gmv')) {
            return 'dieu-hoa-vrf';
        }
        if (str_contains($t, 'multi') || str_contains($t, 'free match')) {
            return 'dieu-hoa-multi';
        }
        
        // Mặc định
        return 'dieu-hoa-gree-khac';
    }

    protected function getCategoryName(string $slug): string
    {
        return match($slug) {
            'dieu-hoa-tu-dung' => 'Điều hòa tủ đứng',
            'dieu-hoa-treo-tuong' => 'Điều hòa treo tường',
            'dieu-hoa-cassette' => 'Điều hòa Cassette',
            'dieu-hoa-am-tran' => 'Điều hòa âm trần nối ống gió',
            'dieu-hoa-vrf' => 'Điều hòa trung tâm VRF',
            'dieu-hoa-multi' => 'Điều hòa Multi',
            default => 'Điều hòa Gree khác'
        };
    }

    protected function calculateArea(int $btu): ?string
    {
        if ($btu <= 0) return null;
        
        if ($btu <= 9500) return '10–15m²';
        if ($btu <= 13000) return '15–20m²';
        if ($btu <= 19000) return '20–30m²';
        if ($btu <= 25000) return '30–40m²';
        if ($btu <= 38000) return '50–65m²';
        if ($btu <= 55000) return '70–90m²';
        if ($btu <= 100000) return '100–150m²';
        
        return null;
    }
}
