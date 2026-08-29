<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\Modules\ProductImportHandler;
use App\Support\Catalog\SkyAirTechnicalSchema;
use Database\Seeders\SkyAirProductCategorySchemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DaikinSkyAirImportReadinessTest extends TestCase
{
    use RefreshDatabase;

    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            Storage::disk('local')->delete($path);
        }
        parent::tearDown();
    }

    public function test_category_specific_schemas_are_safe_complete_and_idempotent(): void
    {
        foreach (SkyAirTechnicalSchema::CATEGORIES as $definition) {
            ProductCategory::factory()->create([
                'id' => $definition['id'], 'name' => $definition['name'],
                'technical_schema_status' => 'active', 'technical_schema_version' => 'v1',
            ]);
        }

        $this->seed(SkyAirProductCategorySchemaSeeder::class);
        $this->seed(SkyAirProductCategorySchemaSeeder::class);

        foreach (SkyAirTechnicalSchema::CATEGORIES as $type => $definition) {
            $category = ProductCategory::findOrFail($definition['id']);
            $this->assertSame(SkyAirTechnicalSchema::version($type), $category->technical_schema_version);
            $this->assertTrue($category->hasTechnicalSchema());
            $this->assertContains('phase', $category->technicalSchemaPermittedFields());
            $this->assertContains('refrigerant_gas', $category->technicalSchemaPermittedFields());
        }

        $cassette = ProductCategory::findOrFail(24)->technicalSchemaPermittedFields();
        $ducted = ProductCategory::findOrFail(27)->technicalSchemaPermittedFields();
        $this->assertContains('panel_model', $cassette);
        $this->assertNotContains('panel_model', $ducted);
        $this->assertContains('external_static_pressure_pa', $ducted);
        $this->assertNotContains('external_static_pressure_pa', $cassette);
    }

    public function test_all_verified_combinations_validate_import_and_round_trip_in_isolation(): void
    {
        Brand::create(['id' => 1, 'name' => 'Daikin', 'slug' => 'daikin', 'is_active' => true]);
        foreach (SkyAirTechnicalSchema::CATEGORIES as $definition) {
            ProductCategory::factory()->create([
                'id' => $definition['id'], 'name' => $definition['name'],
                'technical_schema_status' => 'active', 'technical_schema_version' => 'v1',
            ]);
        }
        $this->seed(SkyAirProductCategorySchemaSeeder::class);

        $rows = $this->loadRows('IMPORT_READY');
        $reviewRows = $this->loadRows('REVIEW_REQUIRED');
        $handler = app(ProductImportHandler::class);
        $this->assertCount(225, $rows);
        $this->assertCount(0, $reviewRows);
        $this->assertCount(225, array_unique(array_merge(array_column($rows, 'sku'), array_column($reviewRows, 'sku'))));

        foreach ($rows as $row) {
            $this->assertSame([], $handler->validateRow($row, 'create', 'sku'), $row['sku']);
            $this->assertSame('created', $handler->importRow($row, 'create', 'sku'), $row['sku']);
        }

        $this->assertSame(225, Product::count());
        $this->assertSame(0, Product::where('is_active', true)->count());
        $this->assertSame(225, Product::whereNotNull('technical_capacity_btu')->count());
        $this->assertSame(0, Product::whereHas('category', fn ($query) => $query->where('name', 'like', '%VRF%'))->count());

        $representatives = Product::query()
            ->whereIn('series', [
                'SkyAir RZF - Round Flow', 'SkyAir RZA - Áp trần',
                'SkyAir RZFC - Áp suất tĩnh trung bình', 'SkyAir RNQ - 4 hướng thổi',
                'SkyAir RC - Tủ đứng đặt sàn', 'SkyAir RCN - Tủ đứng package',
            ])->get()->unique('series');
        $this->assertGreaterThanOrEqual(5, $representatives->count());

        $export = app(DataExportService::class)->export(
            module: 'product', fileType: 'json', fieldGroups: ['basic', 'specs'],
            selectedIds: $representatives->pluck('id')->all(), scope: 'selected'
        );
        $this->generatedFiles[] = $export->file_path;
        $exportRows = json_decode(file_get_contents(storage_path('app/private/'.$export->file_path)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount($representatives->count(), $exportRows);
        foreach ($exportRows as $row) {
            $specs = json_decode((string) $row['specs_json'], true, flags: JSON_THROW_ON_ERROR);
            $keys = array_column($specs, 'key');
            $this->assertNotEmpty($row['sku']);
            $this->assertNotEmpty($row['technical_capacity_btu']);
            $this->assertContains('phase', $keys);
            $this->assertContains('refrigerant_gas', $keys);
        }
    }

    public function test_evidence_artifacts_preserve_pairing_and_non_boolean_feature_states(): void
    {
        $combinations = $this->csv('docs/reports/final/artifacts/skyair_combination_matrix.csv');
        $features = $this->csv('docs/reports/final/artifacts/skyair_feature_matrix.csv');
        $accessories = $this->csv('docs/reports/final/artifacts/skyair_accessory_matrix.csv');

        $this->assertCount(225, $combinations);
        $this->assertCount(225, array_unique(array_column($combinations, 'sku')));
        $this->assertSame(['R32', 'R410A'], array_values(array_unique(array_column($combinations, 'refrigerant'))));
        $this->assertContains('CONTROLLER_REQUIRED', array_column($features, 'availability'));
        $this->assertContains('OPTIONAL', array_column($features, 'availability'));
        $this->assertGreaterThan(100, count($accessories));
    }

    public function test_six_visual_capacity_rechecks_are_exact_and_source_ready(): void
    {
        $rows = collect($this->csv('docs/reports/final/artifacts/skyair_combination_matrix.csv'))->keyBy('sku');
        $expected = [
            'FCFG140AV1V-RZFC140AY19' => ['capacity_kw' => '14.07', 'technical_capacity_btu' => '48000', 'phase' => '3', 'refrigerant' => 'R32', 'equipment_type' => 'cassette'],
            'FHNQ36MV1V-RNQ36MV1V' => ['capacity_kw' => '10.1', 'technical_capacity_btu' => '34500', 'phase' => '1', 'refrigerant' => 'R410A', 'equipment_type' => 'ceiling_suspended'],
            'FVGR8PV1-RN80H(E)Y18' => ['capacity_kw' => '23.5', 'technical_capacity_btu' => '80000', 'phase' => '3', 'refrigerant' => 'R410A', 'equipment_type' => 'floor_standing'],
            'FVGR10PV1-RCN100H(E)Y18' => ['capacity_kw' => '29.3', 'technical_capacity_btu' => '100000', 'phase' => '3', 'refrigerant' => 'R410A', 'equipment_type' => 'floor_standing'],
            'FVGR13PV1-RCN125H(E)Y18' => ['capacity_kw' => '35.5', 'technical_capacity_btu' => '121000', 'phase' => '3', 'refrigerant' => 'R410A', 'equipment_type' => 'floor_standing'],
            'FVGR15PV1-RCN150H(E)Y18' => ['capacity_kw' => '44.8', 'technical_capacity_btu' => '153000', 'phase' => '3', 'refrigerant' => 'R410A', 'equipment_type' => 'floor_standing'],
        ];

        foreach ($expected as $sku => $values) {
            $this->assertArrayHasKey($sku, $rows->all());
            foreach ($values as $field => $value) {
                $this->assertSame($value, $rows[$sku][$field], $sku.' '.$field);
            }
            $this->assertSame('IMPORT_READY', $rows[$sku]['readiness'], $sku);
        }
    }

    private function loadRows(string $sheetName): array
    {
        $sheet = IOFactory::load(base_path('DAIKIN_SKYAIR_2026_IMPORT.xlsx'))->getSheetByName($sheetName);
        $values = $sheet->toArray(null, true, true, false);
        $headers = array_map('strval', array_shift($values));
        return array_map(fn (array $row): array => array_combine($headers, $row), $values);
    }

    private function csv(string $path): array
    {
        $handle = fopen(base_path($path), 'r');
        $headers = fgetcsv($handle);
        $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($handle);
        return $rows;
    }
}
