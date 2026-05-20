<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FixTechnicalSpecsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_alias_rename_and_removed_extras(): void
    {
        Storage::fake('local');

        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => [
                'allowed_fields' => ['btu'],
                'field_aliases' => [
                    'capacity_btu' => 'btu',
                ],
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

        Product::factory()->create([
            'product_category_id' => $category->id,
            'specs_json' => [
                ['key' => 'capacity_btu', 'value' => '24000'],
                ['key' => 'marketing_text', 'value' => 'Best choice'],
            ],
        ]);

        $this->artisan('products:fix-technical-specs --dry-run --report')
            ->expectsOutputToContain('Technical Specs Fix')
            ->expectsOutputToContain('rename capacity_btu -> btu')
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('reports');
        $this->assertNotEmpty($files);

        $payload = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertNotEmpty($payload['rows'][0]['proposed_changes'] ?? []);
        $this->assertContains('remove marketing_text', $payload['rows'][0]['proposed_changes']);
    }
}
