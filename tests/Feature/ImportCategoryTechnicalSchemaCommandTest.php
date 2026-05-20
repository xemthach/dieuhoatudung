<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCategoryTechnicalSchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_category_schema_command_applies_local_json_payload(): void
    {
        Storage::fake('local');

        $category = ProductCategory::factory()->create([
            'name' => 'Cassette',
            'slug' => 'cassette',
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);

        $path = storage_path('app/testing-category-schema-import.json');
        file_put_contents($path, json_encode([
            'categories' => [
                [
                    'slug' => 'cassette',
                    'technical_schema_status' => 'active',
                    'technical_schema_version' => 'v1',
                    'technical_schema_notes' => 'manual internal source',
                    'technical_schema_json' => [
                        'allowed_fields' => ['btu'],
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
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $this->artisan('products:import-category-technical-schema', [
            'path' => $path,
            '--report' => true,
        ])
            ->expectsOutputToContain('Category Schema Import')
            ->assertSuccessful();

        $category->refresh();
        $this->assertSame('active', $category->technical_schema_status);
        $this->assertSame('v1', $category->technical_schema_version);
        $this->assertTrue($category->hasTechnicalSchema());
        $this->assertNotEmpty(Storage::disk('local')->allFiles('reports'));
    }
}
