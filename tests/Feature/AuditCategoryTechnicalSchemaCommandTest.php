<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditCategoryTechnicalSchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_schema_audit_reports_missing_and_inactive_payloads(): void
    {
        Storage::fake('local');

        ProductCategory::factory()->create([
            'name' => 'Missing schema category',
            'slug' => 'missing-schema-category',
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);

        ProductCategory::factory()->create([
            'name' => 'Active but empty',
            'slug' => 'active-but-empty',
            'technical_schema_status' => 'active',
            'technical_schema_json' => [],
        ]);

        $this->artisan('products:audit-category-technical-schema --report')
            ->expectsOutputToContain('Category Technical Schema Audit')
            ->expectsOutputToContain('Categories needing cleanup')
            ->assertSuccessful();

        $this->assertNotEmpty(Storage::disk('local')->allFiles('reports'));
    }
}
