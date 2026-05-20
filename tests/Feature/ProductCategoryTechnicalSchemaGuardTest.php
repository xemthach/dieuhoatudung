<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AI\AIContentGovernance;
use App\Services\DataTransfer\Modules\ProductImportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTechnicalSchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_without_schema_is_reported_in_ai_context(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
        ]);

        $context = app(AIContentGovernance::class)->buildProductContext($product);

        $this->assertContains('category_schema_missing', $context['missing_facts']);
    }

    public function test_import_rejects_category_without_technical_schema(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);

        $handler = app(ProductImportHandler::class);
        $errors = $handler->validateRow([
            'name' => 'Test product',
            'product_category_id' => $category->id,
            'specs_json' => json_encode([
                ['key' => 'btu', 'value' => '24000'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], 'create', 'id');

        $this->assertNotEmpty(array_filter($errors, fn (string $error): bool => str_contains($error, 'Category schema missing')));
    }

    public function test_category_with_active_schema_is_treated_as_source_of_truth(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => [
                'allowed_fields' => ['btu'],
                'required_fields' => ['btu'],
                'field_aliases' => ['capacity_btu' => 'btu'],
                'allowed_units' => ['btu'],
            ],
        ]);

        $this->assertTrue($category->hasTechnicalSchema());
        $this->assertSame(['btu'], $category->technicalSchemaAllowedFields());
        $this->assertSame('btu', $category->normalizeTechnicalSchemaKey('capacity_btu'));
    }

    public function test_import_accepts_schema_field_definitions_without_allowed_fields_blocking_it(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => [
                'fields' => [
                    [
                        'key' => 'btu',
                        'label' => 'BTU',
                        'required' => true,
                        'unit' => 'btu',
                        'type' => 'number',
                    ],
                ],
            ],
        ]);

        $handler = app(ProductImportHandler::class);
        $errors = $handler->validateRow([
            'name' => 'Test product',
            'product_category_id' => $category->id,
            'specs_json' => json_encode([
                ['key' => 'btu', 'value' => '24000'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], 'create', 'id');

        $this->assertSame([], $errors);
        $this->assertSame(['btu'], $category->technicalSchemaPermittedFields());
    }
}
