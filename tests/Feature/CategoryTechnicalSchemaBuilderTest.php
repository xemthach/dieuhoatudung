<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AI\AIContentGovernance;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use App\Services\DataTransfer\Modules\ProductImportHandler;
use App\Services\Product\ProductComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTechnicalSchemaBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_schema_from_cassette_preset_and_save_category(): void
    {
        $schema = app(CategoryTechnicalSchemaService::class)->presetFor('Điều hòa âm trần Cassette', 'cassette');

        $category = ProductCategory::factory()->create([
            'name' => 'Điều hòa âm trần Cassette',
            'technical_schema_status' => 'active',
            'technical_schema_version' => 'v1',
            'technical_schema_json' => array_merge($schema, ['status' => 'active']),
        ]);

        $this->assertTrue($category->hasTechnicalSchema());
        $this->assertContains('capacity_btu', $category->technicalSchemaPermittedFields());
        $this->assertContains('pipe_size_liquid', $category->technicalSchemaPermittedFields());
    }

    public function test_edit_reload_keeps_schema_and_json_output_is_valid(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
        ]);

        $fresh = ProductCategory::findOrFail($category->id);
        $json = $fresh->technicalSchema();

        $this->assertSame('v1', $json['version']);
        $this->assertSame('active', $json['status']);
        $this->assertSame('capacity_btu', $json['fields'][0]['key']);
        $this->assertSame([], app(CategoryTechnicalSchemaService::class)->validate($json));
    }

    public function test_required_field_and_alias_mapping_work_for_import(): void
    {
        $category = $this->schemaCategory([
            [
                'key' => 'capacity_btu',
                'label' => 'Công suất lạnh',
                'type' => 'measurement',
                'unit' => 'BTU',
                'required' => true,
                'aliases' => ['cooling capacity', 'công suất'],
            ],
        ]);

        $handler = app(ProductImportHandler::class);
        $missing = $handler->validateRow([
            'name' => 'Cassette test',
            'product_category_id' => $category->id,
            'specs_json' => json_encode([['key' => 'noise_level', 'value' => '45 dB']], JSON_UNESCAPED_UNICODE),
        ], 'create', 'id');
        $valid = $handler->validateRow([
            'name' => 'Cassette test',
            'product_category_id' => $category->id,
            'specs_json' => json_encode([['key' => 'cooling capacity', 'value' => '24000']], JSON_UNESCAPED_UNICODE),
        ], 'create', 'id');

        $this->assertNotEmpty(array_filter($missing, fn (string $error): bool => str_contains($error, "Required spec 'capacity_btu'")));
        $this->assertSame([], $valid);
        $this->assertSame('capacity_btu', $category->normalizeTechnicalSchemaKey('cooling capacity'));
    }

    public function test_import_unknown_field_is_rejected(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU'],
        ]);

        $errors = app(ProductImportHandler::class)->validateRow([
            'name' => 'Cassette test',
            'product_category_id' => $category->id,
            'specs_json' => json_encode([['key' => 'unknown_field', 'value' => 'abc']], JSON_UNESCAPED_UNICODE),
        ], 'create', 'id');

        $this->assertNotEmpty(array_filter($errors, fn (string $error): bool => str_contains($error, 'outside the category schema')));
    }

    public function test_ai_only_reads_fields_marked_use_for_ai(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU', 'use_for_ai' => true],
            ['key' => 'noise_level', 'label' => 'Độ ồn', 'type' => 'noise', 'unit' => 'dB', 'use_for_ai' => false],
        ]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'btu' => 24000,
            'technical_capacity_btu' => 24000,
            'technical_capacity_status' => 'verified_candidate',
            'noise_level' => '45 dB',
        ]);

        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $this->assertArrayHasKey('product.rated_cooling_capacity_btu', $context['allowed_facts']);
        $this->assertArrayNotHasKey('product.noise_level', $context['allowed_facts']);
    }

    public function test_frontend_and_compare_render_follow_schema_sort_order(): void
    {
        $category = $this->schemaCategory([
            ['key' => 'noise_level', 'label' => 'Độ ồn', 'type' => 'noise', 'unit' => 'dB', 'sort_order' => 20],
            ['key' => 'capacity_btu', 'label' => 'Công suất lạnh', 'type' => 'measurement', 'unit' => 'BTU', 'sort_order' => 10],
        ]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'btu' => 24000,
            'technical_capacity_btu' => 24000,
            'technical_capacity_status' => 'verified_candidate',
            'noise_level' => '45',
        ]);

        $frontendRows = app(CategoryTechnicalSchemaService::class)->productSpecsFor($product, 'frontend');
        $compare = app(ProductComparisonService::class)->buildGroupedSpecs(collect([$product]));

        $this->assertSame(['capacity_btu', 'noise_level'], array_column($frontendRows, 'key'));
        $this->assertSame(['capacity_btu', 'noise_level'], array_column($compare['Thông số kỹ thuật'], 'key'));
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
