<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\Modules\ProductImportHandler;
use App\Services\Product\ProductTechnicalFactResolver;
use App\Support\Catalog\WallMountedTechnicalSchema;
use Database\Seeders\WallMountedProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DaikinWallMountedImportReadinessTest extends TestCase
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

    public function test_wall_mounted_category_schema_is_active_safe_and_idempotent(): void
    {
        $this->seed(WallMountedProductCategorySeeder::class);
        $first = ProductCategory::where('slug', WallMountedTechnicalSchema::CATEGORY_SLUG)->firstOrFail();
        $this->seed(WallMountedProductCategorySeeder::class);
        $second = ProductCategory::where('slug', WallMountedTechnicalSchema::CATEGORY_SLUG)->firstOrFail();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProductCategory::where('slug', WallMountedTechnicalSchema::CATEGORY_SLUG)->count());
        $this->assertSame(WallMountedTechnicalSchema::CATEGORY_NAME, $second->name);
        $this->assertSame(WallMountedTechnicalSchema::VERSION, $second->technical_schema_version);
        $this->assertTrue($second->hasTechnicalSchema());
        $this->assertFalse($second->is_active);
        $this->assertFalse($second->is_indexable);
        $this->assertSame('noindex,follow', $second->robots);
        $this->assertCount(75, $second->technicalSchemaFieldDefinitions());
        $this->assertContains('m³/min', $second->technicalSchemaAllowedUnits());
        $this->assertContains('dB(A)', $second->technicalSchemaAllowedUnits());
        $this->assertContains('refrigerant_charge_kg', $second->technicalSchemaPermittedFields());
        $this->assertNotContains('refrigerant_type', $second->technicalSchemaPermittedFields());
    }

    public function test_all_51_rows_validate_and_import_into_isolated_database_with_round_trip(): void
    {
        Brand::create(['name' => 'Daikin', 'slug' => 'daikin', 'is_active' => true]);
        $this->seed(WallMountedProductCategorySeeder::class);

        $rows = $this->loadImportRows();
        $handler = app(ProductImportHandler::class);

        $this->assertCount(51, $rows);
        $this->assertCount(51, array_unique(array_column($rows, 'sku')));
        foreach ($rows as $row) {
            $this->assertSame([], $handler->validateRow($row, 'create', 'sku'), $row['sku']);
        }

        $before = Product::count();
        foreach ($rows as $row) {
            $this->assertSame('created', $handler->importRow($row, 'create', 'sku'), $row['sku']);
        }
        $this->assertSame($before + 51, Product::count());

        $category = ProductCategory::where('slug', WallMountedTechnicalSchema::CATEGORY_SLUG)->firstOrFail();
        $this->assertSame(51, Product::where('product_category_id', $category->id)->count());
        $this->assertSame(51, Product::whereHas('brand', fn ($query) => $query->where('name', 'Daikin'))->count());

        $resolver = app(ProductTechnicalFactResolver::class);
        foreach (Product::all() as $product) {
            $this->assertNotNull($product->sku);
            $this->assertSame($product->model_code, str_replace('-', '/', $product->sku));
            $this->assertNull($product->refrigerant_gas);
            $this->assertNull($resolver->value($product, 'refrigerant_type'));
            $this->assertNotNull($resolver->value($product, 'refrigerant_charge_kg'));
        }

        $highCapacity = Product::where('series', 'FTKZ')->where('model_code', 'like', '%71%')->firstOrFail();
        $this->assertNull($highCapacity->hp);
        $this->assertNull($resolver->value($highCapacity, 'hp'));

        $coolingOnly = Product::where('cooling_type', '1_chieu')->firstOrFail();
        $this->assertNull($resolver->value($coolingOnly, 'heating_capacity_kw_nominal'));

        $ftf = Product::where('series', 'FTF')->firstOrFail();
        $this->assertFalse($ftf->inverter);
        $this->assertFalse(filter_var($resolver->value($ftf, 'inverter'), FILTER_VALIDATE_BOOLEAN));

        foreach (['FTKZ', 'FTKM', 'FTKY', 'FTKF', 'ATKF', 'FTKB', 'ATKB', 'FTHB', 'FTF', 'FTXM', 'FTXV', 'FTHF'] as $series) {
            $product = Product::where('series', $series)->firstOrFail();
            $facts = $resolver->allVerified($product);
            $this->assertArrayHasKey('refrigerant_charge_kg', $facts, $series);
            $this->assertArrayHasKey('indoor_airflow_cooling_high_m3_min', $facts, $series);
            $this->assertArrayHasKey('cooling_operating_min_c_db', $facts, $series);
            $this->assertSame('m³/min', $this->spec($product, 'indoor_airflow_cooling_high_m3_min')['unit']);
            $this->assertSame('°C', $this->spec($product, 'cooling_operating_min_c_db')['unit']);
        }

        $export = app(DataExportService::class)->export(
            module: 'product', fileType: 'json', fieldGroups: ['basic', 'specs'],
            selectedIds: Product::pluck('id')->all(), scope: 'selected'
        );
        $this->generatedFiles[] = $export->file_path;
        $exportRows = json_decode(file_get_contents(storage_path('app/private/'.$export->file_path)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(51, $exportRows);
        foreach ($exportRows as $exportRow) {
            $decoded = json_decode((string) $exportRow['specs_json'], true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotEmpty($exportRow['sku']);
            $this->assertNotEmpty($decoded);
            $this->assertContains('refrigerant_charge_kg', array_column($decoded, 'key'));
            $charge = collect($decoded)->firstWhere('key', 'refrigerant_charge_kg');
            $this->assertSame('kg', $charge['unit']);
        }
    }

    public function test_model_feature_artifact_preserves_non_boolean_semantics(): void
    {
        $path = base_path('docs/reports/final/artifacts/wall_mounted_model_feature_matrix.csv');
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $states = [];
        $count = 0;
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);
            $states[$row['availability']] = true;
            $count++;
        }
        fclose($handle);

        $this->assertSame(1377, $count);
        $this->assertArrayHasKey('YES', $states);
        $this->assertArrayHasKey('NO', $states);
        $this->assertTrue(isset($states['OPTIONAL']) || isset($states['OPTIONAL_ACCESSORY']));
    }

    private function loadImportRows(): array
    {
        $sheet = IOFactory::load(base_path('DAIKIN_WALL_MOUNTED_2026_IMPORT_READY.xlsx'))->getSheetByName('IMPORT_READY');
        $values = $sheet->toArray(null, true, true, false);
        $headers = array_map('strval', array_shift($values));

        return array_map(fn (array $row): array => array_combine($headers, $row), $values);
    }

    private function spec(Product $product, string $key): array
    {
        return collect($product->specs_json)->firstWhere('key', $key);
    }
}
