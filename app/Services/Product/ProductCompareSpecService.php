<?php

namespace App\Services\Product;

use App\Models\Product;

class ProductCompareSpecService
{
    private array $aliasMap = [
        'btu' => ['Công suất', 'Công suất lạnh', 'Capacity', 'Cooling Capacity', 'BTU'],
        'cooling_capacity' => ['Công suất lạnh (kW)', 'Cooling Capacity (kW)', 'Cooling Capacity'],
        'heating_capacity' => ['Công suất sưởi', 'Heating Capacity'],
        'voltage' => ['Điện áp', 'Nguồn điện', 'Power Supply', 'Voltage'],
        'refrigerant_gas' => ['Gas', 'Môi chất lạnh', 'Refrigerant', 'Loại Gas', 'Loại gas'],
        'power_consumption' => ['Công suất điện', 'Điện năng tiêu thụ', 'Power Input'],
        'airflow' => ['Lưu lượng gió', 'Air Flow', 'Airflow', 'Lưu lượng gió (m3/h)'],
        'noise_level' => ['Độ ồn', 'Noise Level', 'Độ ồn (dB)', 'Độ ồn dàn lạnh', 'Độ ồn dàn nóng'],
        'indoor_dimensions' => ['Kích thước dàn lạnh', 'Indoor Dimension', 'Indoor Unit Dimension'],
        'outdoor_dimensions' => ['Kích thước dàn nóng', 'Outdoor Dimension', 'Outdoor Unit Dimension'],
        'weight' => ['Trọng lượng', 'Khối lượng', 'Weight'],
        'pipe_size' => ['Ống đồng', 'Kích thước ống', 'Pipe Size', 'Liquid/Gas Pipe', 'Ống đồng (Lỏng/Hơi)']
    ];

    public function buildRows($products): array
    {
        $rows = [];
        foreach ($products as $product) {
            $rows[$product->id] = $this->build($product);
        }
        return $rows;
    }

    public function build(Product $product): array
    {
        $specs = $this->normalizeSpecs($product->specs_json);

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'image' => $product->main_image,
            'sale_price' => $product->sale_price,
            'regular_price' => $product->regular_price,
            'basic' => [
                'brand' => $product->brand?->name ?? '—',
                'model_code' => $product->model_code ?? '—',
                'sku' => $product->sku ?? '—',
                'category' => $product->category?->name ?? '—',
                'stock_status' => $product->stock_status ? '<span class="inline-flex rounded px-2 py-1 text-xs font-medium ' . $this->getStockStatusColor($product->stock_status->value) . '">' . $product->stock_status->label() . '</span>' : '—',
                'warranty' => strip_tags($product->warranty_info ?? '—'),
            ],
            'technical' => [
                'btu' => $this->getValue($product, 'btu', $specs) ? number_format((float)$this->getValue($product, 'btu', $specs)) . ' BTU' : '—',
                'inverter' => $this->getInverter($product, $specs),
                'cooling_type' => $this->getCoolingType($product, $specs),
                'voltage' => $this->getValue($product, 'voltage', $specs) ?? '—',
                'refrigerant_gas' => $this->getValue($product, 'refrigerant_gas', $specs) ?? '—',
                'power_consumption' => $this->getValue($product, 'power_consumption', $specs) ?? '—',
                'airflow' => $this->getValue($product, 'airflow', $specs) ?? '—',
                'noise_level' => $this->getValue($product, 'noise_level', $specs) ?? '—',
            ],
            'physical' => [
                'indoor_dimensions' => $this->getValue($product, 'indoor_dimensions', $specs) ?? '—',
                'outdoor_dimensions' => $this->getValue($product, 'outdoor_dimensions', $specs) ?? '—',
                'weight' => $this->getValue($product, 'weight', $specs) ?? '—',
                'pipe_size' => $this->getValue($product, 'pipe_size', $specs) ?? '—',
            ],
        ];
    }

    private function getValue(Product $product, string $key, array $specs): ?string
    {
        // 1. Column
        if (!empty($product->{$key})) {
            return (string)$product->{$key};
        }

        // 2. specs_json (alias matched in normalizeSpecs)
        if (!empty($specs[$key])) {
            return (string)$specs[$key];
        }

        return null;
    }

    private function getInverter(Product $product, array $specs): string
    {
        if ($product->inverter !== null) {
            return $product->inverter ? '<span class="text-green-600 font-medium">Có</span>' : 'Không';
        }

        $inv = $specs['inverter'] ?? $specs['Công nghệ Inverter'] ?? null;
        if ($inv) {
            if (mb_stripos($inv, 'có') !== false || mb_stripos($inv, 'yes') !== false) {
                return '<span class="text-green-600 font-medium">Có</span>';
            }
            return 'Không';
        }
        return '—';
    }

    private function getCoolingType(Product $product, array $specs): string
    {
        if ($product->cooling_type) {
            return $product->cooling_type === '2_chieu' ? '2 chiều (lạnh/sưởi)' : '1 chiều (chỉ làm lạnh)';
        }
        $type = $specs['cooling_type'] ?? $specs['Kiểu làm lạnh'] ?? $specs['Loại máy'] ?? null;
        if ($type) {
            return $type;
        }
        return '—';
    }

    private function getStockStatusColor(?string $status): string
    {
        return match($status) {
            'in_stock' => 'text-green-600 bg-green-50',
            'out_of_stock' => 'text-red-600 bg-red-50',
            'pre_order' => 'text-blue-600 bg-blue-50',
            'contact' => 'text-surface-600 bg-surface-50',
            default => 'text-surface-600 bg-surface-50'
        };
    }

    public function normalizeSpecs(array|string|null $raw): array
    {
        if (empty($raw)) return [];
        
        $specs = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($specs)) return [];

        $normalized = [];
        
        // Sometimes specs are array of key-value pairs: [['key' => '...', 'value' => '...']]
        if (isset($specs[0]) && is_array($specs[0])) {
            foreach ($specs as $item) {
                if (isset($item['key']) && isset($item['value'])) {
                    $normalized[trim($item['key'])] = trim($item['value']);
                }
            }
        } else {
            foreach ($specs as $k => $v) {
                $normalized[trim($k)] = is_string($v) ? trim($v) : $v;
            }
        }

        // Map alias to primary keys
        $mapped = [];
        foreach ($normalized as $k => $v) {
            $mapped[$k] = $v; // Keep original just in case

            // Clean key
            $cleanKey = trim(preg_replace('/:\s*$/', '', $k));

            foreach ($this->aliasMap as $primary => $aliases) {
                foreach ($aliases as $alias) {
                    if (mb_strtolower($cleanKey) === mb_strtolower($alias)) {
                        $mapped[$primary] = $v;
                        break 2;
                    }
                }
            }
        }

        return $mapped;
    }
}
