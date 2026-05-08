<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogueGree2026Seeder extends Seeder
{
    public function run(): void
    {
        // 1. Đảm bảo brand Gree tồn tại
        $brand = Brand::firstOrCreate(
            ['slug' => 'gree'],
            ['name' => 'Gree', 'is_active' => true]
        );

        // 2. Tạo/lookup product categories
        $categoryMap = $this->ensureCategories();

        // 3. Đọc file JSON catalogue
        $jsonPath = base_path('data dieu hoa/Catalogue 2026/Catalogue/out.json');

        if (!file_exists($jsonPath)) {
            $this->command->error("File không tồn tại: {$jsonPath}");
            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);

        if (empty($items)) {
            $this->command->error('Không đọc được dữ liệu từ out.json');
            return;
        }

        $this->command->info("Đang import " . count($items) . " sản phẩm từ Catalogue Gree 2026...");

        $created = 0;
        $skipped = 0;

        foreach ($items as $index => $item) {
            // Skip nếu đã tồn tại (match theo model_code)
            if (Product::where('model_code', $item['model_code'])->exists()) {
                $skipped++;
                continue;
            }

            // Tạo slug unique (thêm model_code vì nhiều sản phẩm cùng tên)
            $slug = Str::slug($item['name'] . '-' . $item['model_code']);
            $baseSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            // Build specs_json
            $specs = $this->buildSpecsJson($item);

            // Tạo product
            Product::create([
                'name'                => $item['name'],
                'slug'                => $slug,
                'model_code'          => $item['model_code'],
                'brand_id'            => $brand->id,
                'product_category_id' => $categoryMap[$item['category']] ?? null,
                'btu'                 => $item['btu'] ?: null,
                'inverter'            => $item['inverter'],
                'cooling_type'        => $item['cooling_type'],
                'voltage'             => $item['voltage'] ?: null,
                'refrigerant_gas'     => $item['refrigerant_gas'],
                'power_consumption'   => $item['power_consumption'] ?: null,
                'airflow'             => $item['airflow'] ?: null,
                'noise_level'         => $item['noise_level'] ?: null,
                'indoor_dimensions'   => $item['indoor_dimensions'] ?: null,
                'outdoor_dimensions'  => $item['outdoor_dimensions'] ?: null,
                'weight'              => $item['weight'] ?: null,
                'warranty_info'       => $item['warranty'],
                'specs_json'          => $specs,
                'is_active'           => true,
                'sort_order'          => $index + 1,
                'stock_status'        => 'in_stock',
                'condition'           => 'new',
                'schema_enabled'      => true,
            ]);

            $created++;
        }

        $this->command->info("✅ Catalogue Gree 2026: Tạo {$created} sản phẩm, bỏ qua {$skipped} (đã tồn tại).");

        // Hiển thị thống kê categories
        foreach ($categoryMap as $catName => $catId) {
            $count = Product::where('product_category_id', $catId)->count();
            $this->command->line("   - {$catName}: {$count} sản phẩm");
        }
    }

    /**
     * Tạo product categories cho các loại điều hòa trong catalogue.
     */
    private function ensureCategories(): array
    {
        $map = [];

        $categories = [
            'Cassette' => [
                'name' => 'Điều hòa âm trần Cassette',
                'slug' => 'dieu-hoa-am-tran-cassette',
            ],
            'Duct' => [
                'name' => 'Điều hòa nối ống gió',
                'slug' => 'dieu-hoa-noi-ong-gio',
            ],
            'VRF' => [
                'name' => 'Điều hòa trung tâm VRF',
                'slug' => 'dieu-hoa-trung-tam-vrf',
            ],
            'Tủ đứng' => [
                'name' => 'Điều hòa tủ đứng',
                'slug' => 'dieu-hoa-tu-dung',
            ],
        ];

        foreach ($categories as $key => $data) {
            $cat = ProductCategory::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'      => $data['name'],
                    'is_active' => true,
                    'type'      => 'main',
                ]
            );
            $map[$key] = $cat->id;
        }

        return $map;
    }

    /**
     * Build specs_json array từ catalogue data.
     * Format: [{label: "...", value: "..."}]
     */
    private function buildSpecsJson(array $item): array
    {
        $specs = [];

        if (!empty($item['cooling_capacity'])) {
            $specs[] = ['label' => 'Công suất lạnh', 'value' => $item['cooling_capacity']];
        }

        if (!empty($item['heating_capacity'])) {
            $specs[] = ['label' => 'Công suất sưởi', 'value' => $item['heating_capacity']];
        }

        if (!empty($item['btu'])) {
            $specs[] = ['label' => 'Công suất BTU', 'value' => number_format($item['btu']) . ' BTU/h'];
        }

        $specs[] = [
            'label' => 'Công nghệ',
            'value' => $item['inverter'] ? 'Inverter' : 'Non-Inverter (Mono)',
        ];

        if (!empty($item['refrigerant_gas'])) {
            $specs[] = ['label' => 'Gas lạnh', 'value' => $item['refrigerant_gas']];
        }

        if (!empty($item['voltage'])) {
            $specs[] = ['label' => 'Nguồn điện', 'value' => $item['voltage']];
        }

        if (!empty($item['power_consumption'])) {
            $specs[] = ['label' => 'Công suất tiêu thụ', 'value' => $item['power_consumption']];
        }

        if (!empty($item['airflow'])) {
            $specs[] = ['label' => 'Lưu lượng gió', 'value' => $item['airflow']];
        }

        if (!empty($item['noise_level'])) {
            $specs[] = ['label' => 'Độ ồn', 'value' => $item['noise_level']];
        }

        if (!empty($item['indoor_dimensions'])) {
            $specs[] = ['label' => 'Kích thước dàn lạnh (RxSxC)', 'value' => $item['indoor_dimensions']];
        }

        if (!empty($item['outdoor_dimensions'])) {
            $specs[] = ['label' => 'Kích thước dàn nóng (RxSxC)', 'value' => $item['outdoor_dimensions']];
        }

        if (!empty($item['weight'])) {
            $specs[] = ['label' => 'Trọng lượng (dàn lạnh/dàn nóng)', 'value' => $item['weight']];
        }

        if (!empty($item['features'])) {
            $specs[] = ['label' => 'Tính năng nổi bật', 'value' => implode(', ', $item['features'])];
        }

        return $specs;
    }
}
