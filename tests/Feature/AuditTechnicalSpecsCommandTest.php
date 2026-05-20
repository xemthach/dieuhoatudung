<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditTechnicalSpecsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_reports_schema_missing_and_correct_products(): void
    {
        Storage::fake('local');

        $brand = Brand::factory()->create();

        $schemaCategory = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_version' => 'v1',
            'technical_schema_json' => [
                'allowed_fields' => ['btu'],
                'required_fields' => ['btu'],
                'allowed_units' => ['btu'],
            ],
        ]);

        $missingSchemaCategory = ProductCategory::factory()->create([
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);

        Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $schemaCategory->id,
            'btu' => 24000,
            'specs_json' => null,
        ]);

        Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $missingSchemaCategory->id,
        ]);

        $this->artisan('products:audit-technical-specs --report')
            ->expectsOutputToContain('Technical Specs Audit')
            ->expectsOutputToContain('Category schema missing')
            ->expectsOutputToContain('Correct')
            ->assertSuccessful();

        $this->assertNotEmpty(Storage::disk('local')->allFiles('reports'));
    }
}
