<?php

namespace App\Services\Product;

use App\Exports\ProductComparisonExport;
use App\Models\Product;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use App\Support\ProductSpecLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductComparisonService
{
    public function __construct(private readonly ProductTechnicalFactResolver $technicalFacts) {}

    public const MAX_PRODUCTS = 4;

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
        'Điện năng' => [
            'voltage', 'power_consumption', 'rated_current_a',
            'cooling_power_input_kw', 'cooling_current_input_a',
            'heating_power_input_kw', 'heating_current_input_a',
            'cooling35_1_power_input_kw', 'cooling35_1_current_input_a',
            'cooling46_2_power_input_kw', 'cooling46_2_current_input_a',
            'heating_3_power_input_kw', 'heating_3_current_input_a',
        ],
        'Môi chất lạnh' => [
            'refrigerant_gas', 'refrigerant_charge_kg', 'refrigerant_type', 'refrigerant_factory_charge_kg',
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
            'pipe_max_height', 'pipe_max_length', 'recommended_area',
        ],
        'Nguồn dữ liệu' => [
            'source_catalogue', 'source_page', 'source_table',
        ],
    ];

    private const DB_FIELD_LABELS = [
        'brand' => 'Thương hiệu',
        'model_code' => 'Model',
        'sku' => 'Mã SKU',
        'category' => 'Danh mục',
        'stock_status' => 'Tình trạng kho',
        'warranty' => 'Bảo hành',
        'btu' => 'Công suất lạnh',
        'capacity_kw' => 'Công suất lạnh (kW)',
        'hp' => 'Mã lực (HP)',
        'inverter' => 'Công nghệ Inverter',
        'cooling_type' => 'Loại máy',
        'voltage' => 'Nguồn điện',
        'power_consumption' => 'Công suất điện tiêu thụ',
        'refrigerant_gas' => 'Môi chất lạnh',
        'airflow' => 'Lưu lượng gió',
        'noise_level' => 'Độ ồn dàn lạnh',
        'indoor_dimensions' => 'Kích thước dàn lạnh',
        'outdoor_dimensions' => 'Kích thước dàn nóng',
        'weight' => 'Trọng lượng',
        'recommended_area' => 'Diện tích đề nghị',
    ];

    public function getProducts(array $slugs): Collection
    {
        if ($slugs === []) {
            return collect();
        }

        $slugs = array_slice($slugs, 0, self::MAX_PRODUCTS);

        $products = Product::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->with(['brand', 'category'])
            ->get()
            ->keyBy('slug');

        return collect($slugs)
            ->map(fn (string $slug) => $products[$slug] ?? null)
            ->filter()
            ->values();
    }

    public function buildGroupedSpecs(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $schemaGrouped = $this->buildSchemaGroupedSpecs($products);
        if ($schemaGrouped !== []) {
            return $schemaGrouped;
        }

        $productSpecs = [];
        foreach ($products as $product) {
            $productSpecs[$product->id] = $this->extractAllSpecs($product);
        }

        $grouped = [];
        $usedKeys = [];

        foreach (self::COMPARE_GROUPS as $groupLabel => $keys) {
            $rows = [];

            foreach ($keys as $key) {
                $values = [];
                $hasAnyValue = false;

                foreach ($products as $product) {
                    $formatted = $this->formatDisplayValue($key, $productSpecs[$product->id][$key] ?? null, $product);
                    $values[] = $formatted;
                    $hasAnyValue = $hasAnyValue || $formatted !== '—';
                }

                if ($hasAnyValue) {
                    $rows[] = [
                        'key' => $key,
                        'label' => $this->getLabel($key),
                        'values' => $values,
                        'differs' => $this->valuesDiffer($values),
                    ];
                    $usedKeys[] = $key;
                }
            }

            if ($rows !== []) {
                $grouped[$groupLabel] = $rows;
            }
        }

        $ungroupedKeys = [];
        foreach ($productSpecs as $specs) {
            $ungroupedKeys = array_merge($ungroupedKeys, array_keys($specs));
        }

        $ungroupedRows = [];
        foreach (array_unique($ungroupedKeys) as $key) {
            if (in_array($key, $usedKeys, true) || in_array($key, ['id', 'slug', 'name', 'image', 'sale_price', 'regular_price'], true)) {
                continue;
            }

            $values = [];
            $hasAnyValue = false;

            foreach ($products as $product) {
                $formatted = $this->formatDisplayValue($key, $productSpecs[$product->id][$key] ?? null, $product);
                $values[] = $formatted;
                $hasAnyValue = $hasAnyValue || $formatted !== '—';
            }

            if ($hasAnyValue) {
                $ungroupedRows[] = [
                    'key' => $key,
                    'label' => $this->getLabel($key),
                    'values' => $values,
                    'differs' => $this->valuesDiffer($values),
                ];
            }
        }

        if ($ungroupedRows !== []) {
            $grouped['Thông số khác'] = $ungroupedRows;
        }

        return $grouped;
    }

    private function buildSchemaGroupedSpecs(Collection $products): array
    {
        $category = $products->first()?->category;
        if (! $category?->hasTechnicalSchema()) {
            return [];
        }

        $schema = app(CategoryTechnicalSchemaService::class);
        $fields = $schema->fieldsFor($category, 'compare');
        if ($fields === []) {
            return [];
        }

        $flatByProduct = [];
        foreach ($products as $product) {
            $flatByProduct[$product->id] = $schema->flatProductSpecs($product);
        }

        $rows = [];
        foreach ($fields as $field) {
            $values = [];
            $hasAnyValue = false;

            foreach ($products as $product) {
                $formatted = $this->formatSchemaDisplayValue($field, $flatByProduct[$product->id][$field['key']] ?? null);
                $values[] = $formatted;
                $hasAnyValue = $hasAnyValue || $formatted !== '—';
            }

            if ($hasAnyValue) {
                $rows[] = [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'values' => $values,
                    'differs' => $this->valuesDiffer($values),
                ];
            }
        }

        return $rows === [] ? [] : ['Thông số kỹ thuật' => $rows];
    }

    private function formatSchemaDisplayValue(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $this->formatNormalizedValue(
            (string) ($field['key'] ?? ''),
            $value,
            [
                'type' => (string) ($field['type'] ?? 'text'),
                'unit' => (string) ($field['unit'] ?? 'none'),
            ]
        ) ?? (string) $value;
    }

    private function extractAllSpecs(Product $product): array
    {
        $specs = [
            'brand' => $product->brand?->name,
            'model_code' => $product->model_code,
            'sku' => $product->sku,
            'category' => $product->category?->name,
            'stock_status' => $product->stock_status?->label() ?? null,
            'warranty' => $product->warranty_info ? strip_tags($product->warranty_info) : null,
            // Technical compare never reads legacy products.btu or a stale
            // dedicated mirror before the canonical resolver.
            'btu' => $this->technicalFacts->value($product, 'technical_capacity_btu'),
            'marketing_capacity_btu' => $this->technicalFacts->value($product, 'marketing_capacity_btu'),
            'technical_capacity_btu' => $this->technicalFacts->value($product, 'technical_capacity_btu'),
            'capacity_kw' => $this->technicalFacts->value($product, 'capacity_kw'),
            'hp' => $product->hp,
            'inverter' => $product->inverter,
            'cooling_type' => $product->cooling_type,
            'voltage' => $product->voltage,
            'power_consumption' => $this->technicalFacts->value($product, 'power_input_kw'),
            'refrigerant_gas' => $product->refrigerant_gas,
            'airflow' => $product->airflow,
            'noise_level' => $product->noise_level,
            'indoor_dimensions' => $product->indoor_dimensions,
            'outdoor_dimensions' => $product->outdoor_dimensions,
            'weight' => $product->weight,
            'recommended_area' => $product->recommended_area,
        ];

        foreach ($this->technicalFacts->allForDisplay($product) as $key => $value) $specs[$key] = $value;

        return $specs;
    }

    private function flattenSpecsJson(array|string|null $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $specs = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($specs)) {
            return [];
        }

        if (isset($specs[0]) && is_array($specs[0]) && array_key_exists('key', $specs[0])) {
            $flat = [];
            foreach ($specs as $item) {
                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;
                if ($key !== null && $value !== null && $value !== '') {
                    $flat[(string) $key] = (string) $value;
                }
            }

            return $flat;
        }

        $flat = [];
        foreach ($specs as $key => $value) {
            if ($value !== null && $value !== '') {
                $flat[(string) $key] = is_string($value) ? $value : (string) $value;
            }
        }

        return $flat;
    }

    public function getLabel(string $key): string
    {
        if (isset(ProductSpecLabel::MAP[$key])) {
            return ProductSpecLabel::MAP[$key];
        }

        if (isset(self::DB_FIELD_LABELS[$key])) {
            return self::DB_FIELD_LABELS[$key];
        }

        return ProductSpecLabel::label($key);
    }

    private function formatDisplayValue(string $key, mixed $value, Product $product): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $normalized = $this->formatNormalizedValue($key, $value);
        if ($normalized !== null) {
            return $normalized;
        }

        return match ($key) {
            'inverter' => $value ? 'Có' : 'Không',
            'cooling_type' => match ($value) {
                '2_chieu' => '2 chiều (lạnh/sưởi)',
                '1_chieu' => '1 chiều (chỉ làm lạnh)',
                default => (string) $value,
            },
            default => ProductSpecLabel::formatValue($key, (string) $value),
        };
    }

    private function formatNormalizedValue(string $key, mixed $value, array $field = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $type = Str::lower((string) ($field['type'] ?? ''));
        $unit = Str::lower((string) ($field['unit'] ?? ''));

        if (in_array($key, ['btu', 'capacity_btu', 'cooling35_1_buth', 'cooling46_2_buth', 'heating_3_buth'], true) && is_numeric($raw)) {
            return number_format((float) $raw).' BTU/h';
        }

        if ($key === 'marketing_capacity_btu') {
            return is_numeric($raw) ? '(Nhóm '.round(((float) $raw) / 1000).'k)' : $raw;
        }

        if (in_array($key, ['capacity_kw', 'cooling_capacity_kw', 'heating_capacity_kw', 'cooling35_1_capacity_kw', 'cooling46_2_capacity_kw', 'heating_3_capacity_kw'], true) && is_numeric($raw)) {
            return $this->formatDecimal($raw, 2).' kW';
        }

        if ($key === 'hp' && is_numeric($raw)) {
            return $this->formatDecimal($raw, 1).' HP';
        }

        if ($key === 'voltage' || $type === 'voltage') {
            return $this->formatVoltageValue($raw);
        }

        if (in_array($key, ['noise_level', 'noise_db', 'outdoor_noise_db', 'noise_indoor', 'noise_outdoor'], true) || $type === 'noise') {
            return $this->formatNoiseValue($raw);
        }

        if (
            in_array($key, ['indoor_dimensions', 'outdoor_dimensions', 'dimensions_mm', 'panel_dimensions_mm', 'panel_package_dim_mm', 'indoor_package_dim_mm', 'outdoor_package_dim_mm', 'net_dimensionswhd_mm', 'net_dimensions_whd_mm', 'packed_dimensionswhd_mm'], true)
            || $type === 'dimension'
            || Str::contains($key, ['dimension', 'dimensions'])
        ) {
            return $this->formatDimensionValue($raw);
        }

        if (in_array($key, ['pipe_liquid', 'pipe_gas', 'pipe_size_liquid', 'pipe_size_gas', 'pipe_connections_liquid_pipe_mm', 'pipe_connections_gas_pipe_mm'], true)) {
            return $this->formatPipeValue($raw);
        }

        if (in_array($key, ['power_consumption', 'power_consumption_kw', 'power_input', 'cooling_power_input_kw', 'heating_power_input_kw', 'cooling35_1_power_input_kw', 'cooling46_2_power_input_kw', 'heating_3_power_input_kw'], true) && is_numeric($raw)) {
            return ((float) $raw >= 100 ? number_format((float) $raw, 0) : $this->formatDecimal($raw, 2)).' '.($unit === 'w' ? 'W' : 'kW');
        }

        if (in_array($key, ['rated_current_a', 'cooling_current_input_a', 'heating_current_input_a', 'cooling35_1_current_input_a', 'cooling46_2_current_input_a', 'heating_3_current_input_a'], true) && is_numeric($raw)) {
            return $this->formatDecimal($raw, 2).' A';
        }

        if ($type === 'measurement' && is_numeric($raw)) {
            return $this->formatDecimal($raw, 2).($unit !== '' && $unit !== 'none' ? ' '.$this->presentUnit($unit) : '');
        }

        return $this->normalizeInlineValue($raw);
    }

    private function formatVoltageValue(string $value): string
    {
        $formatted = $this->normalizeInlineValue($value);
        $formatted = preg_replace('/\s*-\s*/u', '–', $formatted) ?? $formatted;
        $formatted = preg_replace('/\s*~\s*/u', '~', $formatted) ?? $formatted;
        $formatted = preg_replace('/(\d)\s*pha/iu', '$1 pha', $formatted) ?? $formatted;

        return $formatted;
    }

    private function formatNoiseValue(string $value): string
    {
        $formatted = $this->normalizeInlineValue($value);

        if (! Str::contains(Str::lower($formatted), 'db')) {
            return $formatted.' dB(A)';
        }

        if (! Str::contains(Str::lower($formatted), 'db(a)')) {
            return preg_replace('/db\b/i', 'dB(A)', $formatted) ?? $formatted;
        }

        return $formatted;
    }

    private function formatDimensionValue(string $value): string
    {
        $formatted = $this->normalizeInlineValue($value);
        $formatted = preg_replace('/\s*[xX*]\s*/u', ' × ', $formatted) ?? $formatted;
        $formatted = preg_replace('/\s*×\s*/u', ' × ', $formatted) ?? $formatted;

        if (! Str::contains(Str::lower($formatted), 'mm')) {
            $formatted .= ' mm';
        }

        return $formatted;
    }

    private function formatPipeValue(string $value): string
    {
        return $this->normalizeInlineValue($value);
    }

    private function normalizeInlineValue(string $value): string
    {
        $formatted = trim($value);
        $formatted = preg_replace('/\s+/u', ' ', $formatted) ?? $formatted;

        return str_replace(['—', '–'], '–', $formatted);
    }

    private function presentUnit(string $unit): string
    {
        return match (Str::lower($unit)) {
            'btu' => 'BTU/h',
            'kw' => 'kW',
            'w' => 'W',
            'db' => 'dB(A)',
            'mm' => 'mm',
            'kg' => 'kg',
            'm' => 'm',
            'a' => 'A',
            'v' => 'V',
            'hz' => 'Hz',
            default => $unit,
        };
    }

    private function formatDecimal(string|int|float $value, int $decimals): string
    {
        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
    }

    private function valuesDiffer(array $values): bool
    {
        $nonEmpty = array_values(array_filter($values, fn ($value) => $value !== '—'));

        return count($nonEmpty) > 1 && count(array_unique($nonEmpty)) > 1;
    }

    public function buildComparisonMatrix(Collection $products): array
    {
        $matrix = [];

        foreach ($this->buildGroupedSpecs($products) as $groupLabel => $rows) {
            $matrix[] = [
                'type' => 'group_header',
                'label' => $groupLabel,
                'values' => array_fill(0, $products->count(), ''),
            ];

            foreach ($rows as $row) {
                $matrix[] = [
                    'type' => 'spec',
                    'label' => $row['label'],
                    'values' => $row['values'],
                ];
            }
        }

        return $matrix;
    }

    public function exportPdf(Collection $products): Response
    {
        $grouped = $this->buildGroupedSpecs($products);
        $siteName = setting('site.name', 'Điều Hòa Tủ Đứng');
        $siteUrl = config('app.url', url('/'));
        $date = now()->format('d/m/Y H:i');
        $layout = $this->resolvePdfLayout($products);
        $html = $this->buildPdfHtml($products, $grouped, $siteName, $siteUrl, $date, $layout);

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        if (! is_dir($tempDir) || ! is_writable($tempDir)) {
            $tempDir = sys_get_temp_dir().'/mpdf-tmp';
            if (! is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $layout['page_format'],
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5,
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetTitle('Bảng so sánh sản phẩm - '.$siteName);
        $mpdf->SetAuthor($siteName);
        $mpdf->SetFooter('{DATE j/m/Y} | '.$siteUrl.' | Trang {PAGENO}/{nbpg}');
        $mpdf->WriteHTML($html);

        $filename = 'so-sanh-san-pham-'.now()->format('Ymd-His').'.pdf';

        return response($mpdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function buildPdfHtml(Collection $products, array $grouped, string $siteName, string $siteUrl, string $date, array $layout): string
    {
        $productCount = $products->count();
        $headers = $products->map(fn (Product $product) => $this->buildPdfProductHeader($product))->values();

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
        <meta charset="utf-8">
        <style>
            body { font-family: 'dejavusans', sans-serif; font-size: {$layout['body_font_size']}pt; color: #334155; }
            h1 { margin: 0 0 4px; font-size: 16pt; color: #173b63; }
            .meta { margin-bottom: 12px; font-size: 8pt; color: #64748b; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; vertical-align: top; font-size: {$layout['body_font_size']}pt; word-wrap: break-word; overflow-wrap: anywhere; }
            th { background: #f8fafc; color: #334155; font-weight: 700; }
            .label-col { width: {$layout['label_width']}%; background: #f8fafc; font-weight: 700; }
            .product-col { width: {$layout['product_width']}%; }
            .thead-anchor { background: #eef2ff; color: #1e3a8a; text-transform: uppercase; }
            .product-head { background: #ffffff; }
            .product-meta { display: block; margin-bottom: 2px; font-size: {$layout['meta_font_size']}pt; line-height: 1.2; color: #64748b; }
            .product-title { display: block; font-size: {$layout['title_font_size']}pt; line-height: 1.28; font-weight: 700; color: #173b63; }
            .product-subtitle { display: block; margin-top: 3px; font-size: {$layout['meta_font_size']}pt; line-height: 1.2; color: #64748b; text-transform: uppercase; }
            .product-model { display: block; font-size: {$layout['model_font_size']}pt; line-height: 1.25; font-weight: 700; color: #0f172a; word-break: break-word; overflow-wrap: anywhere; }
            .group-header td { background: #dbeafe; color: #1d4ed8; font-weight: 700; }
            .differs { background: #fef3c7; }
        </style>
        </head>
        <body>
        <h1>Bảng So Sánh Sản Phẩm</h1>
        <div class="meta">{$siteName} — {$siteUrl} — Ngày xuất: {$date}</div>
        <table class="compare-table compare-table-{$productCount} orientation-{$layout['orientation']}">
            <thead>
                <tr>
                    <th class="label-col thead-anchor">Thông số</th>
        HTML;

        foreach ($headers as $header) {
            $meta = $header['meta'] !== '' ? '<span class="product-meta">'.e($header['meta']).'</span>' : '';
            $subtitle = $header['subtitle'] !== '' ? '<span class="product-subtitle">'.e($header['subtitle']).'</span>' : '';
            $html .= '<th class="product-col product-head">'.$meta.'<span class="product-title">'.e($header['title']).'</span>'.$subtitle.'</th>';
        }

        $html .= '</tr><tr><th class="label-col thead-anchor">Model thực tế</th>';

        foreach ($headers as $header) {
            $html .= '<th class="product-col product-head"><span class="product-model">'.e($header['model']).'</span></th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($grouped as $groupLabel => $rows) {
            $html .= '<tr class="group-header"><td colspan="'.($productCount + 1).'">'.e($groupLabel).'</td></tr>';

            foreach ($rows as $row) {
                $diffClass = $row['differs'] ? ' class="differs"' : '';
                $html .= '<tr><td class="label-col">'.e($row['label']).'</td>';

                foreach ($row['values'] as $value) {
                    $html .= '<td'.$diffClass.'>'.e($value).'</td>';
                }

                $html .= '</tr>';
            }
        }

        return $html.'</tbody></table></body></html>';
    }

    private function resolvePdfLayout(Collection $products): array
    {
        $count = max($products->count(), 1);
        $maxTitleLength = (int) $products->map(fn (Product $product) => mb_strlen(trim((string) $product->name)))->max();
        $maxModelLength = (int) $products->map(fn (Product $product) => mb_strlen($this->resolveTechnicalModel($product)))->max();
        $orientation = $count >= 3 || $maxTitleLength > 78 || $maxModelLength > 28 ? 'L' : 'P';
        $labelWidth = match ($count) {
            1 => 24,
            2 => 23,
            3 => 21,
            default => 19,
        };

        return [
            'orientation' => $orientation,
            'page_format' => 'A4-'.$orientation,
            'label_width' => $labelWidth,
            'product_width' => round((100 - $labelWidth) / $count, 2),
            'body_font_size' => match ($count) {
                1, 2 => 8.8,
                3 => 8.3,
                default => 7.7,
            },
            'title_font_size' => match ($count) {
                1, 2 => 10.2,
                3 => 9.2,
                default => 8.2,
            },
            'model_font_size' => match ($count) {
                1, 2 => 9.3,
                3 => 8.7,
                default => 8.1,
            },
            'meta_font_size' => match ($count) {
                1, 2 => 7.4,
                3 => 7.0,
                default => 6.6,
            },
        ];
    }

    private function buildPdfProductHeader(Product $product): array
    {
        return [
            'title' => trim((string) $product->name),
            'model' => $this->resolveTechnicalModel($product),
            'meta' => implode(' | ', array_filter([
                trim((string) $product->brand?->name),
                trim((string) $product->category?->name),
            ])),
            'subtitle' => $this->resolveMarketingCapacityLabel($product),
        ];
    }

    private function resolveTechnicalModel(Product $product): string
    {
        $specs = $this->extractAllSpecs($product);
        $indoor = trim((string) ($specs['indoor_model'] ?? ''));
        $outdoor = trim((string) ($specs['outdoor_model'] ?? ''));

        if ($indoor !== '' && $outdoor !== '') {
            return $indoor === $outdoor ? $indoor : $indoor.'/'.$outdoor;
        }

        $modelCode = trim((string) ($product->model_code ?? ''));
        if ($modelCode !== '') {
            return $modelCode;
        }

        return $indoor !== '' ? $indoor : ($outdoor !== '' ? $outdoor : '—');
    }

    private function resolveMarketingCapacityLabel(Product $product): string
    {
        $marketing = $this->extractAllSpecs($product)['marketing_capacity_btu'] ?? null;

        return $marketing !== null && $marketing !== ''
            ? ($this->formatNormalizedValue('marketing_capacity_btu', $marketing) ?? '')
            : '';
    }

    public function exportExcel(Collection $products): BinaryFileResponse
    {
        $matrix = $this->buildComparisonMatrix($products);
        $productNames = $products->pluck('name')->toArray();
        $filename = 'so-sanh-san-pham-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new ProductComparisonExport($matrix, $productNames), $filename);
    }

    public function exportCsv(Collection $products): Response
    {
        $matrix = $this->buildComparisonMatrix($products);
        $productNames = $products->pluck('name')->toArray();
        $csv = "\xEF\xBB\xBF";
        $csv .= $this->csvLine(array_merge(['Thông số'], $productNames));

        foreach ($matrix as $row) {
            $line = $row['type'] === 'group_header'
                ? array_merge(['['.$row['label'].']'], array_fill(0, count($productNames), ''))
                : array_merge([$row['label']], $row['values']);
            $csv .= $this->csvLine($line);
        }

        $filename = 'so-sanh-san-pham-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function csvLine(array $fields): string
    {
        return implode(',', array_map(function ($field) {
            return '"'.str_replace('"', '""', (string) $field).'"';
        }, $fields))."\r\n";
    }
}
