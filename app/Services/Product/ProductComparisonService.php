<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Support\ProductSpecLabel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

/**
 * Central service for the product comparison module.
 *
 * Builds a unified comparison matrix from:
 *   1. Standard DB columns (brand, btu, voltage, etc.)
 *   2. All extra_specs from specs_json
 *
 * Groups specs by HVAC domain with Vietnamese labels.
 * Provides export to PDF, Excel, and CSV.
 */
class ProductComparisonService
{
    /** Maximum products allowed in a comparison */
    public const MAX_PRODUCTS = 4;

    /**
     * Comparison groups definition.
     * Key = Vietnamese group label
     * Value = array of spec keys (from DB columns or specs_json)
     *
     * The order here determines display order.
     */
    public const COMPARE_GROUPS = [
        'Thông tin chung' => [
            'brand', 'model_code', 'sku', 'category', 'stock_status', 'warranty',
        ],
        'Công suất & Hiệu suất' => [
            'btu', 'capacity_kw', 'hp', 'eer', 'cop', 'eer_cop', 'power_factor',
            'inverter', 'cooling_type',
            'cooling35_1_capacity_kw', 'cooling35_1_buth', 'cooling35_1_eer_buthw',
            'cooling46_2_capacity_kw', 'cooling46_2_buth', 'cooling46_2_eer_buthw',
            'cooling_capacity_kw', 'heating_capacity_kw',
            'heating_3_capacity_kw', 'heating_3_buth', 'heating_3_cop_ww',
        ],
        'Điện & Môi chất lạnh' => [
            'voltage', 'power_consumption', 'rated_current_a',
            'cooling_power_input_kw', 'cooling_current_input_a',
            'heating_power_input_kw', 'heating_current_input_a',
            'cooling35_1_power_input_kw', 'cooling35_1_current_input_a',
            'cooling46_2_power_input_kw', 'cooling46_2_current_input_a',
            'heating_3_power_input_kw', 'heating_3_current_input_a',
            'refrigerant_gas', 'refrigerant_charge_kg', 'refrigerant_type',
            'refrigerant_factory_charge_kg',
        ],
        'Dàn lạnh' => [
            'indoor_model', 'indoor_airflow_cfm', 'airflow',
            'noise_level', 'noise_db', 'indoor_esp_nominal_pa', 'esp_pa',
            'indoor_dimensions', 'indoor_package_dim_mm',
            'weight', 'indoor_package_weight_kg',
        ],
        'Mặt nạ (Panel)' => [
            'panel_dimensions_mm', 'panel_package_dim_mm',
            'panel_weight_kg', 'panel_package_weight_kg',
        ],
        'Dàn nóng' => [
            'outdoor_model', 'outdoor_noise_db',
            'outdoor_dimensions', 'outdoor_package_dim_mm',
            'outdoor_weight_kg', 'outdoor_package_weight_kg',
        ],
        'Lắp đặt' => [
            'pipe_liquid', 'pipe_gas',
            'pipe_connections_liquid_pipe_mm', 'pipe_connections_gas_pipe_mm',
            'pipe_max_height', 'pipe_max_length',
            'recommended_area',
        ],
        'Nguồn dữ liệu' => [
            'source_catalogue', 'source_page', 'source_table',
        ],
    ];

    /**
     * Vietnamese labels for standard DB column fields (not in ProductSpecLabel::MAP).
     */
    private const DB_FIELD_LABELS = [
        'brand'              => 'Thương hiệu',
        'model_code'         => 'Model',
        'sku'                => 'Mã SKU',
        'category'           => 'Danh mục',
        'stock_status'       => 'Tình trạng kho',
        'warranty'           => 'Bảo hành',
        'btu'                => 'Công suất (BTU)',
        'capacity_kw'        => 'Công suất (kW)',
        'hp'                 => 'Mã lực (HP)',
        'inverter'           => 'Công nghệ Inverter',
        'cooling_type'       => 'Loại máy',
        'voltage'            => 'Điện áp',
        'power_consumption'  => 'Công suất điện tiêu thụ',
        'refrigerant_gas'    => 'Loại Gas',
        'airflow'            => 'Lưu lượng gió',
        'noise_level'        => 'Độ ồn dàn lạnh',
        'indoor_dimensions'  => 'Kích thước dàn lạnh',
        'outdoor_dimensions' => 'Kích thước dàn nóng',
        'weight'             => 'Trọng lượng',
        'recommended_area'   => 'Diện tích đề nghị',
    ];

    /**
     * Fetch active products by their IDs or slugs.
     */
    public function getProducts(array $slugs): Collection
    {
        if (empty($slugs)) {
            return collect();
        }

        $slugs = array_slice($slugs, 0, self::MAX_PRODUCTS);

        $dbProducts = Product::whereIn('slug', $slugs)
            ->where('is_active', true)
            ->with(['brand', 'category'])
            ->get()
            ->keyBy('slug');

        // Maintain original order
        $ordered = collect();
        foreach ($slugs as $slug) {
            if (isset($dbProducts[$slug])) {
                $ordered->push($dbProducts[$slug]);
            }
        }

        return $ordered;
    }

    /**
     * Build the full grouped comparison matrix.
     *
     * Returns:
     * [
     *   'Thông tin chung' => [
     *     ['key' => 'brand', 'label' => 'Thương hiệu', 'values' => ['Gree', 'Daikin', ...], 'differs' => true],
     *     ...
     *   ],
     *   ...
     * ]
     */
    public function buildGroupedSpecs(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        // 1. Extract all spec data per product
        $productSpecs = [];
        foreach ($products as $product) {
            $productSpecs[$product->id] = $this->extractAllSpecs($product);
        }

        // 2. Build grouped rows
        $grouped = [];
        $usedKeys = [];

        foreach (self::COMPARE_GROUPS as $groupLabel => $keys) {
            $rows = [];
            foreach ($keys as $key) {
                $values = [];
                $hasAnyValue = false;

                foreach ($products as $product) {
                    $val = $productSpecs[$product->id][$key] ?? null;
                    $formatted = $this->formatDisplayValue($key, $val, $product);
                    $values[] = $formatted;
                    if ($formatted !== '—') {
                        $hasAnyValue = true;
                    }
                }

                // Only include row if at least one product has a value
                if ($hasAnyValue) {
                    $rows[] = [
                        'key'     => $key,
                        'label'   => $this->getLabel($key),
                        'values'  => $values,
                        'differs' => $this->valuesDiffer($values),
                    ];
                    $usedKeys[] = $key;
                }
            }

            if (!empty($rows)) {
                $grouped[$groupLabel] = $rows;
            }
        }

        // 3. Collect ungrouped extra specs (keys not in any COMPARE_GROUPS)
        $ungroupedRows = [];
        $allExtraKeys = [];
        foreach ($products as $product) {
            $allExtraKeys = array_merge($allExtraKeys, array_keys($productSpecs[$product->id]));
        }
        $allExtraKeys = array_unique($allExtraKeys);

        foreach ($allExtraKeys as $key) {
            if (in_array($key, $usedKeys, true)) {
                continue;
            }
            // Skip internal/meta keys
            if (in_array($key, ['id', 'slug', 'name', 'image', 'sale_price', 'regular_price'], true)) {
                continue;
            }

            $values = [];
            $hasAnyValue = false;
            foreach ($products as $product) {
                $val = $productSpecs[$product->id][$key] ?? null;
                $formatted = $this->formatDisplayValue($key, $val, $product);
                $values[] = $formatted;
                if ($formatted !== '—') {
                    $hasAnyValue = true;
                }
            }

            if ($hasAnyValue) {
                $ungroupedRows[] = [
                    'key'     => $key,
                    'label'   => $this->getLabel($key),
                    'values'  => $values,
                    'differs' => $this->valuesDiffer($values),
                ];
            }
        }

        if (!empty($ungroupedRows)) {
            $grouped['Thông số khác'] = $ungroupedRows;
        }

        return $grouped;
    }

    /**
     * Extract ALL specs from a product: DB columns + flattened specs_json.
     */
    private function extractAllSpecs(Product $product): array
    {
        $specs = [];

        // Standard DB columns
        $specs['brand']              = $product->brand?->name;
        $specs['model_code']         = $product->model_code;
        $specs['sku']                = $product->sku;
        $specs['category']           = $product->category?->name;
        $specs['stock_status']       = $product->stock_status?->label() ?? null;
        $specs['warranty']           = $product->warranty_info ? strip_tags($product->warranty_info) : null;
        $specs['btu']                = $product->btu;
        $specs['capacity_kw']        = $product->capacity_kw;
        $specs['hp']                 = $product->hp;
        $specs['inverter']           = $product->inverter;
        $specs['cooling_type']       = $product->cooling_type;
        $specs['voltage']            = $product->voltage;
        $specs['power_consumption']  = $product->power_consumption;
        $specs['refrigerant_gas']    = $product->refrigerant_gas;
        $specs['airflow']            = $product->airflow;
        $specs['noise_level']        = $product->noise_level;
        $specs['indoor_dimensions']  = $product->indoor_dimensions;
        $specs['outdoor_dimensions'] = $product->outdoor_dimensions;
        $specs['weight']             = $product->weight;
        $specs['recommended_area']   = $product->recommended_area;

        // Flatten specs_json (extra_specs)
        $extraSpecs = $this->flattenSpecsJson($product->specs_json);
        foreach ($extraSpecs as $key => $value) {
            // Don't overwrite DB column values with empty extra_specs
            if (!isset($specs[$key]) || empty($specs[$key])) {
                $specs[$key] = $value;
            }
        }

        return $specs;
    }

    /**
     * Flatten specs_json from either repeater [{key:..., value:...}] or flat {key: value} format.
     */
    private function flattenSpecsJson(array|string|null $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $specs = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($specs)) {
            return [];
        }

        // Repeater format: [{key: ..., value: ...}]
        if (isset($specs[0]) && is_array($specs[0]) && array_key_exists('key', $specs[0])) {
            $flat = [];
            foreach ($specs as $item) {
                $k = $item['key'] ?? null;
                $v = $item['value'] ?? null;
                if ($k !== null && $v !== null && $v !== '') {
                    $flat[$k] = (string)$v;
                }
            }
            return $flat;
        }

        // Already flat
        $flat = [];
        foreach ($specs as $k => $v) {
            if ($v !== null && $v !== '') {
                $flat[$k] = is_string($v) ? $v : (string)$v;
            }
        }
        return $flat;
    }

    /**
     * Get Vietnamese label for a key.
     */
    public function getLabel(string $key): string
    {
        // Check ProductSpecLabel first (extra_specs labels)
        if (isset(ProductSpecLabel::MAP[$key])) {
            return ProductSpecLabel::MAP[$key];
        }

        // Check DB field labels
        if (isset(self::DB_FIELD_LABELS[$key])) {
            return self::DB_FIELD_LABELS[$key];
        }

        // Fallback to ProductSpecLabel::humanize via label()
        return ProductSpecLabel::label($key);
    }

    /**
     * Format a value for display.
     */
    private function formatDisplayValue(string $key, mixed $val, Product $product): string
    {
        if ($val === null || $val === '') {
            return '—';
        }

        // Special formatting
        return match ($key) {
            'btu'          => number_format((int)$val) . ' BTU',
            'capacity_kw'  => (float)$val > 0 ? $val . ' kW' : '—',
            'hp'           => (float)$val > 0 ? $val . ' HP' : '—',
            'inverter'     => $val ? 'Có' : 'Không',
            'cooling_type' => match ($val) {
                '2_chieu'   => '2 chiều (lạnh/sưởi)',
                '1_chieu'   => '1 chiều (chỉ làm lạnh)',
                default     => (string)$val,
            },
            default => ProductSpecLabel::formatValue($key, (string)$val),
        };
    }

    /**
     * Check if values differ across products (for highlighting).
     */
    private function valuesDiffer(array $values): bool
    {
        $nonEmpty = array_filter($values, fn($v) => $v !== '—');
        if (count($nonEmpty) <= 1) {
            return false;
        }
        return count(array_unique($nonEmpty)) > 1;
    }

    /**
     * Build flat comparison matrix for export (no HTML, plain text only).
     */
    public function buildComparisonMatrix(Collection $products): array
    {
        $grouped = $this->buildGroupedSpecs($products);
        $matrix = [];

        foreach ($grouped as $groupLabel => $rows) {
            // Group header row
            $matrix[] = [
                'type'   => 'group_header',
                'label'  => $groupLabel,
                'values' => array_fill(0, $products->count(), ''),
            ];

            foreach ($rows as $row) {
                $matrix[] = [
                    'type'   => 'spec',
                    'label'  => $row['label'],
                    'values' => $row['values'],
                ];
            }
        }

        return $matrix;
    }

    // ──────────────────────────────────────────────
    // EXPORT: PDF
    // ──────────────────────────────────────────────

    /**
     * Export comparison to PDF.
     */
    public function exportPdf(Collection $products): \Symfony\Component\HttpFoundation\Response
    {
        $grouped = $this->buildGroupedSpecs($products);
        $siteName = setting('site.name', 'Điều Hòa Tủ Đứng');
        $siteUrl  = config('app.url', url('/'));
        $date     = now()->format('d/m/Y H:i');

        // Build HTML
        $html = $this->buildPdfHtml($products, $grouped, $siteName, $siteUrl, $date);

        // Ensure temp directory exists and is writable
        $tempDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            // Fallback to system temp directory if storage is not writable
            $tempDir = sys_get_temp_dir() . '/mpdf-tmp';
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
        }

        $mpdf = new Mpdf([
            'mode'            => 'utf-8',
            'format'          => 'A4-L', // Landscape
            'margin_left'     => 10,
            'margin_right'    => 10,
            'margin_top'      => 15,
            'margin_bottom'   => 15,
            'margin_header'   => 5,
            'margin_footer'   => 5,
            'default_font'    => 'dejavusans',
            'tempDir'         => $tempDir,
        ]);

        $mpdf->SetTitle('Bảng so sánh sản phẩm - ' . $siteName);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetFooter('{DATE j/m/Y} | ' . $siteUrl . ' | Trang {PAGENO}/{nbpg}');

        $mpdf->WriteHTML($html);

        $filename = 'so-sanh-san-pham-' . now()->format('Ymd-His') . '.pdf';

        return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Build PDF HTML content.
     */
    private function buildPdfHtml(Collection $products, array $grouped, string $siteName, string $siteUrl, string $date): string
    {
        $productCount = $products->count();
        $colWidth = intval(70 / max($productCount, 1));

        $html = <<<HTML
        <style>
            body { font-family: 'dejavusans', sans-serif; font-size: 9pt; color: #333; }
            h1 { font-size: 16pt; color: #1e3a5f; margin-bottom: 5px; }
            .meta { font-size: 8pt; color: #666; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #d1d5db; padding: 5px 8px; text-align: left; vertical-align: top; font-size: 8.5pt; }
            th { background-color: #f3f4f6; font-weight: bold; color: #374151; }
            .group-header td { background-color: #e0e7ff; font-weight: bold; color: #3730a3; font-size: 9pt; padding: 6px 8px; }
            .label-col { width: 25%; background-color: #f9fafb; font-weight: 600; color: #4b5563; }
            .product-name { font-weight: bold; font-size: 9pt; color: #1e3a5f; }
            .differs { background-color: #fef3c7; }
        </style>
        <h1>Bảng So Sánh Sản Phẩm</h1>
        <div class="meta">{$siteName} — {$siteUrl} — Ngày xuất: {$date}</div>
        <table>
        <thead>
            <tr>
                <th class="label-col">Thông số</th>
        HTML;

        foreach ($products as $product) {
            $name = e(mb_substr($product->name, 0, 60));
            $html .= '<th class="product-name" style="width:' . $colWidth . '%">' . $name . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($grouped as $groupLabel => $rows) {
            $colspan = $productCount + 1;
            $html .= '<tr class="group-header"><td colspan="' . $colspan . '">' . e($groupLabel) . '</td></tr>';

            foreach ($rows as $row) {
                $diffClass = $row['differs'] ? ' class="differs"' : '';
                $html .= '<tr><td class="label-col">' . e($row['label']) . '</td>';
                foreach ($row['values'] as $val) {
                    $html .= '<td' . $diffClass . '>' . e($val) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        return $html;
    }

    // ──────────────────────────────────────────────
    // EXPORT: Excel
    // ──────────────────────────────────────────────

    /**
     * Export comparison to Excel XLSX.
     */
    public function exportExcel(Collection $products): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $matrix = $this->buildComparisonMatrix($products);
        $productNames = $products->pluck('name')->toArray();

        $filename = 'so-sanh-san-pham-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new \App\Exports\ProductComparisonExport($matrix, $productNames),
            $filename
        );
    }

    // ──────────────────────────────────────────────
    // EXPORT: CSV
    // ──────────────────────────────────────────────

    /**
     * Export comparison to CSV with UTF-8 BOM.
     */
    public function exportCsv(Collection $products): \Symfony\Component\HttpFoundation\Response
    {
        $matrix = $this->buildComparisonMatrix($products);
        $productNames = $products->pluck('name')->toArray();

        // UTF-8 BOM
        $csv = "\xEF\xBB\xBF";

        // Header
        $header = array_merge(['Thông số'], $productNames);
        $csv .= $this->csvLine($header);

        // Data
        foreach ($matrix as $row) {
            if ($row['type'] === 'group_header') {
                $line = array_merge(['[' . $row['label'] . ']'], array_fill(0, count($productNames), ''));
            } else {
                $line = array_merge([$row['label']], $row['values']);
            }
            $csv .= $this->csvLine($line);
        }

        $filename = 'so-sanh-san-pham-' . now()->format('Ymd-His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Format a CSV line.
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $field = str_replace('"', '""', (string)$field);
            return '"' . $field . '"';
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }
}
