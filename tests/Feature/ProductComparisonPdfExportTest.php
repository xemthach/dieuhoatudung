<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use App\Services\Product\ProductComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ProductComparisonPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_header_shows_full_title_and_real_model_without_truncation(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
            ['key' => 'voltage', 'label' => 'Nguồn điện', 'type' => 'voltage', 'unit' => 'V'],
        ]);

        $products = collect([
            Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa âm trần Gree Cassette ALL-MATCH Inverter 1 chiều 42',
                'model_code' => 'GCC42S6I/GMC42S6I',
                'btu' => 42650,
                'technical_capacity_btu' => 42650,
                'technical_capacity_status' => 'verified_candidate',
                'marketing_capacity_btu' => 42000,
                'voltage' => '1 pha, 220-240V ~50/60Hz',
                'specs_json' => [
                    ['key' => 'indoor_model', 'value' => 'GCC42S6I'],
                    ['key' => 'outdoor_model', 'value' => 'GMC42S6I'],
                    ['key' => 'capacity_btu', 'value' => '42650', 'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
                    ['key' => 'marketing_capacity_btu', 'value' => '42000'],
                ],
            ]),
            Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa âm trần Gree Cassette U-MATCH Inverter 1 chiều 48.0',
                'model_code' => 'GUD140T1/A-S/GUD140W1/NhA-S',
                'btu' => 47800,
                'technical_capacity_btu' => 47800,
                'technical_capacity_status' => 'verified_candidate',
                'marketing_capacity_btu' => 48000,
                'voltage' => '1 pha, 220-240V ~50/60Hz',
                'specs_json' => [
                    ['key' => 'indoor_model', 'value' => 'GUD140T1/A-S'],
                    ['key' => 'outdoor_model', 'value' => 'GUD140W1/NhA-S'],
                    ['key' => 'capacity_btu', 'value' => '47800', 'source_section' => 'TECHNICAL_APPENDIX', 'verification_status' => 'verified_candidate'],
                    ['key' => 'marketing_capacity_btu', 'value' => '48000'],
                ],
            ]),
        ]);

        $html = $this->buildPdfHtml($products);

        $this->assertStringContainsString('Điều hòa âm trần Gree Cassette ALL-MATCH Inverter 1 chiều 42', $html);
        $this->assertStringContainsString('Model thực tế', $html);
        $this->assertStringContainsString('GCC42S6I/GMC42S6I', $html);
        $this->assertStringContainsString('GUD140T1/A-S/GUD140W1/NhA-S', $html);
        $this->assertStringContainsString('(Nhóm 42k)', $html);
        $this->assertStringNotContainsString('...', $html);
    }

    public function test_pdf_formats_technical_values_in_hvac_catalog_style(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
            ['key' => 'voltage', 'label' => 'Nguồn điện', 'type' => 'voltage', 'unit' => 'V'],
            ['key' => 'noise_level', 'label' => 'Độ ồn', 'type' => 'noise', 'unit' => 'dB'],
            ['key' => 'indoor_dimensions', 'label' => 'Kích thước dàn lạnh', 'type' => 'dimension', 'unit' => 'mm'],
            ['key' => 'pipe_size_liquid', 'label' => 'Ống lỏng', 'type' => 'text', 'unit' => 'none'],
        ]);

        $products = collect([
            Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa âm trần cassette kiểm thử tiếng Việt',
                'model_code' => 'GULD160T1/A-S/GULD160W1/NhA-S',
                'btu' => 42650,
                'voltage' => '1 pha, 220-240V ~50/60Hz',
                'noise_level' => '52/50/45/41',
                'indoor_dimensions' => '840x840x240',
                'specs_json' => [
                    ['key' => 'pipe_size_liquid', 'value' => '1/4" (Φ6.35)'],
                ],
            ]),
        ]);

        $html = $this->buildPdfHtml($products);

        $this->assertStringContainsString('42,650 BTU/h', $html);
        $this->assertStringContainsString('1 pha, 220–240V~50/60Hz', $html);
        $this->assertStringContainsString('52/50/45/41 dB(A)', $html);
        $this->assertStringContainsString('840 × 840 × 240 mm', $html);
        $this->assertStringContainsString('1/4&quot; (Φ6.35)', $html);
        $this->assertStringContainsString('Điều hòa âm trần cassette kiểm thử tiếng Việt', $html);
    }

    public function test_pdf_layout_switches_to_landscape_for_four_products(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
        ]);

        $products = collect(range(1, 4))->map(function (int $index) use ($category) {
            return Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa tủ đứng kiểm thử layout model '.$index,
                'model_code' => 'GULD160T1/A-S/GULD160W1/NhA-S-'.$index,
                'btu' => 42000 + ($index * 1000),
                'technical_capacity_btu' => 42000 + ($index * 1000),
                'technical_capacity_status' => 'verified_candidate',
            ]);
        });

        $layout = $this->resolvePdfLayout($products);
        $html = $this->buildPdfHtml($products);

        $this->assertSame('L', $layout['orientation']);
        $this->assertSame('A4-L', $layout['page_format']);
        $this->assertStringContainsString('compare-table-4', $html);
        $this->assertStringContainsString('orientation-L', $html);
    }

    public function test_export_pdf_returns_valid_pdf_response(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
        ]);

        $products = collect([
            Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa cassette A',
                'model_code' => 'GCC42S6I/GMC42S6I',
                'btu' => 42650,
                'technical_capacity_btu' => 42650,
                'technical_capacity_status' => 'verified_candidate',
            ]),
            Product::factory()->create([
                'product_category_id' => $category->id,
                'name' => 'Điều hòa cassette B',
                'model_code' => 'GUD140T1/A-S/GUD140W1/NhA-S',
                'btu' => 47800,
                'technical_capacity_btu' => 47800,
                'technical_capacity_status' => 'verified_candidate',
            ]),
        ]);

        $response = app(ProductComparisonService::class)->exportPdf($products);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function buildPdfHtml($products): string
    {
        $service = app(ProductComparisonService::class);
        $grouped = $service->buildGroupedSpecs($products);
        $layout = $this->resolvePdfLayout($products);
        $method = new ReflectionMethod($service, 'buildPdfHtml');
        $method->setAccessible(true);

        return $method->invoke($service, $products, $grouped, 'Điều Hòa Tủ Đứng', 'https://example.com', '23/05/2026 15:27', $layout);
    }

    private function resolvePdfLayout($products): array
    {
        $service = app(ProductComparisonService::class);
        $method = new ReflectionMethod($service, 'resolvePdfLayout');
        $method->setAccessible(true);

        return $method->invoke($service, $products);
    }

    private function schemaCategory(array $fields): ProductCategory
    {
        $schema = app(CategoryTechnicalSchemaService::class)->normalize([
            'version' => 'v1',
            'status' => 'active',
            'fields' => $fields,
        ]);

        return ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_version' => 'v1',
            'technical_schema_json' => $schema,
        ]);
    }
}
