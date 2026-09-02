<?php

namespace Tests\Feature;

use App\Filament\Pages\DataTransferPage;
use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\DataImportService;
use App\Services\DataTransfer\ModuleRegistry;
use App\Services\DataTransfer\Modules\ProductImportHandler;
use App\Services\DataTransfer\ProductSystemRestoreContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductSystemRestoreRoundTripTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            Storage::disk('local')->delete($path);
        }

        parent::tearDown();
    }

    public function test_application_product_export_round_trips_to_an_empty_product_table_with_id_and_field_parity(): void
    {
        [$first, $second] = $this->createSourceProducts();
        $expected = collect([$first, $second])->mapWithKeys(
            fn (Product $product) => [$product->id => $this->productSnapshot($product)]
        );

        $export = app(DataExportService::class)->export(
            module: 'product',
            fileType: 'xlsx',
            // This mirrors the Product Export UI's "all fields" submission.
            fieldGroups: array_keys(ModuleRegistry::fieldGroups('product')),
            scope: 'all',
        );
        $this->generatedFiles[] = $export->file_path;
        $exportPath = storage_path('app/private/'.$export->file_path);

        $workbook = IOFactory::load($exportPath);
        $this->assertNotNull($workbook->getSheetByName(ProductSystemRestoreContract::METADATA_SHEET));
        $this->assertNotNull($workbook->getSheetByName(ProductSystemRestoreContract::PAYLOAD_SHEET));
        $this->assertSame(ProductSystemRestoreContract::DATA_SHEET, $workbook->getActiveSheet()->getTitle());
        $this->assertSame(ProductSystemRestoreContract::fields(), array_map(
            'strval',
            $workbook->getActiveSheet()->toArray(null, true, true, false)[0],
        ));
        $workbook->disconnectWorksheets();

        Product::query()->forceDelete();
        $this->assertSame(0, Product::count());

        $job = app(DataImportService::class)->uploadAndPreview(
            module: 'product',
            filePath: $exportPath,
            originalName: 'products-system-restore.xlsx',
            fileType: 'xlsx',
        );
        $this->generatedFiles[] = $job->file_path;

        $this->assertSame('system_restore', $job->mode);
        $this->assertSame('SYSTEM_PRODUCT_RESTORE', data_get($job->format_context_json, 'contract'));
        $this->assertSame(2, $job->total_rows);
        $this->assertSame(2, $job->success_rows);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame(2, $job->created_rows);
        $this->assertSame(0, $job->updated_rows);

        $completed = app(DataImportService::class)->confirmImport($job->fresh());
        $this->assertSame('completed', $completed->status);
        $this->assertSame(2, $completed->created_rows);
        $this->assertSame(0, $completed->failed_rows);
        $this->assertSame(2, Product::count());

        foreach ($expected as $id => $snapshot) {
            $restored = Product::findOrFail($id);
            $this->assertSame($snapshot, $this->productSnapshot($restored), "Product #{$id} must restore without unexplained field differences.");
        }
    }

    public function test_catalog_import_still_blocks_technical_fields_without_catalog_provenance(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => [
                'allowed_fields' => ['technical_capacity_btu'],
                'required_fields' => [],
            ],
        ]);

        $errors = app(ProductImportHandler::class)->validateRow([
            'name' => 'External catalog product',
            'slug' => 'external-catalog-product',
            'product_category_id' => $category->id,
            'technical_capacity_btu' => 18000,
        ], 'create', 'id');

        $this->assertContains(
            'Technical catalog fields require complete appendix provenance; direct product-column import is blocked.',
            $errors,
        );
    }

    public function test_catalog_schema_validation_does_not_misclassify_product_metadata_as_technical_specs(): void
    {
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'active',
            'technical_schema_json' => [
                'allowed_fields' => ['btu'],
                'required_fields' => ['btu'],
            ],
        ]);

        $errors = app(ProductImportHandler::class)->validateRow([
            'id' => 9001,
            'name' => 'Catalog product with normal metadata',
            'slug' => 'catalog-product-normal-metadata',
            'product_category_id' => $category->id,
            'btu' => 18000,
            'sort_order' => 5,
            'main_image' => '/storage/products/example.jpg',
            'gallery_json' => '[]',
            'documents_json' => '[]',
            'condition' => 'new',
            'identifier_exists' => 1,
            'product_type' => 'Air conditioner',
            'source_section' => 'TECHNICAL_APPENDIX',
            'source_pdf' => 'catalog.pdf',
            'source_sha256' => str_repeat('a', 64),
            'source_page' => 4,
            'source_row' => 7,
            'source_column' => 'capacity',
            'extraction_method' => 'table',
        ], 'create', 'id');

        $this->assertSame([], $errors);
    }

    public function test_modified_system_export_manifest_is_rejected_before_preview(): void
    {
        $this->createSourceProducts();
        $export = app(DataExportService::class)->export('product', 'xlsx', [], [], [], 'all');
        $this->generatedFiles[] = $export->file_path;
        $path = storage_path('app/private/'.$export->file_path);
        $workbook = IOFactory::load($path);
        $workbook->getActiveSheet()->setCellValue('B2', 'Modified after export');
        IOFactory::createWriter($workbook, 'Xlsx')->save($path);
        $workbook->disconnectWorksheets();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or modified PRODUCT_SYSTEM_RESTORE manifest.');

        app(DataImportService::class)->uploadAndPreview('product', $path, 'tampered.xlsx', 'xlsx');
    }

    public function test_shuffled_complete_product_group_selection_is_a_system_restore_export(): void
    {
        $this->createSourceProducts();
        $groups = array_keys(ModuleRegistry::fieldGroups('product'));
        $shuffled = array_reverse($groups);

        $export = app(DataExportService::class)->export('product', 'xlsx', $shuffled, [], [], 'all');
        $this->generatedFiles[] = $export->file_path;

        $this->assertSystemRestoreWorkbook(storage_path('app/private/'.$export->file_path));
    }

    public function test_empty_product_group_selection_is_also_a_system_restore_export(): void
    {
        $this->createSourceProducts();

        $export = app(DataExportService::class)->export('product', 'xlsx', [], [], [], 'all');
        $this->generatedFiles[] = $export->file_path;

        $this->assertSystemRestoreWorkbook(storage_path('app/private/'.$export->file_path));
    }

    public function test_data_transfer_page_all_groups_uses_the_system_restore_export_contract(): void
    {
        $this->createSourceProducts();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $groups = array_keys(ModuleRegistry::fieldGroups('product'));

        Livewire::actingAs($user)
            ->test(DataTransferPage::class)
            ->callAction('export', [
                'module' => 'product',
                'file_type' => 'xlsx',
                'field_groups' => $groups,
            ]);

        $export = \App\Models\DataExportJob::query()->latest('id')->firstOrFail();
        $this->generatedFiles[] = $export->file_path;
        $this->assertSame($groups, $export->field_groups_json);
        $this->assertSame(2, $export->total_rows);
        $this->assertSystemRestoreWorkbook(storage_path('app/private/'.$export->file_path));
    }

    public function test_partial_selection_or_narrowed_scope_never_becomes_a_system_restore_export(): void
    {
        [$first] = $this->createSourceProducts();
        $groups = array_keys(ModuleRegistry::fieldGroups('product'));
        $cases = [
            ['groups' => array_slice($groups, 0, -1), 'filters' => [], 'ids' => [], 'scope' => 'all'],
            ['groups' => [...$groups, 'unknown_group'], 'filters' => [], 'ids' => [], 'scope' => 'all'],
            ['groups' => $groups, 'filters' => [], 'ids' => [$first->id], 'scope' => 'selected'],
            ['groups' => $groups, 'filters' => [], 'ids' => [$first->id], 'scope' => 'current_page'],
            ['groups' => $groups, 'filters' => [], 'ids' => [$first->id], 'scope' => 'filter'],
            ['groups' => $groups, 'filters' => ['is_active' => true], 'ids' => [], 'scope' => 'all'],
            ['groups' => $groups, 'filters' => ['is_active' => []], 'ids' => [], 'scope' => 'all'],
        ];

        foreach ($cases as $case) {
            $export = app(DataExportService::class)->export(
                'product',
                'xlsx',
                $case['groups'],
                $case['filters'],
                $case['ids'],
                $case['scope'],
            );
            $this->generatedFiles[] = $export->file_path;

            $this->assertNormalWorkbook(storage_path('app/private/'.$export->file_path));
        }
    }

    public function test_non_xlsx_product_exports_never_use_the_system_restore_contract(): void
    {
        $this->createSourceProducts();
        $groups = array_keys(ModuleRegistry::fieldGroups('product'));

        foreach (['csv', 'xml', 'json'] as $fileType) {
            $export = app(DataExportService::class)->export('product', $fileType, $groups, [], [], 'all');
            $this->generatedFiles[] = $export->file_path;

            $contents = (string) Storage::disk('local')->get($export->file_path);
            $this->assertStringNotContainsString(ProductSystemRestoreContract::FORMAT, $contents);
        }
    }

    /** @return array{0: Product, 1: Product} */
    private function createSourceProducts(): array
    {
        $brand = Brand::factory()->create();
        $category = ProductCategory::factory()->create([
            'technical_schema_status' => 'missing',
            'technical_schema_json' => null,
        ]);
        $source = CatalogSource::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'source_name' => 'Round-trip source',
            'source_type' => 'pdf',
        ]);
        $model = CatalogModel::create([
            'catalog_source_id' => $source->id,
            'model' => 'RT-18000',
            'normalized_model' => 'rt-18000',
        ]);

        $first = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'catalog_source_id' => $source->id,
            'catalog_model_id' => $model->id,
            'catalog_match_status' => 'verified',
            'technical_specs_source' => 'catalog',
            'technical_specs_override_reason' => 'Restored evidence only',
            'technical_specs_overridden_at' => now()->subDay()->startOfSecond(),
            'marketing_capacity_btu' => 18000,
            'technical_capacity_btu' => 16400,
            'technical_capacity_status' => 'verified',
            'capacity_kw' => '4.80',
            'hp' => '2.0',
            'power_consumption' => '1.25 kW',
            'refrigerant_gas' => 'R32',
            'specs_json' => [
                ['key' => 'capacity_btu', 'value' => '16400', 'source_pdf' => 'source.pdf'],
                ['key' => 'verbatim_source_evidence', 'value' => str_repeat('x', 32000)],
            ],
            'gallery_json' => ['/storage/products/first-1.jpg'],
            'documents_json' => [['name' => 'Catalog', 'url' => '/storage/catalog.pdf']],
            'merchant_title' => 'Merchant 18K',
            'merchant_description' => 'Round trip merchant description.',
            'price_includes_vat' => true,
            'condition' => 'new',
        ]);
        $second = Product::factory()->create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'sku' => 'ROUND-TRIP-48000',
            'slug' => 'round-trip-48000',
            'marketing_capacity_btu' => 48000,
            'technical_capacity_btu' => 48000,
            'specs_json' => [],
            'gallery_json' => [],
            'documents_json' => [],
        ]);

        return [$first->fresh(), $second->fresh()];
    }

    /** @return array<string, mixed> */
    private function productSnapshot(Product $product): array
    {
        $snapshot = [];
        foreach (ProductSystemRestoreContract::fields() as $field) {
            $value = $product->getAttribute($field);
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            $snapshot[$field] = $value === '' ? null : $value;
        }

        return $snapshot;
    }

    private function assertSystemRestoreWorkbook(string $path): void
    {
        $workbook = IOFactory::load($path);
        $this->assertNotNull($workbook->getSheetByName(ProductSystemRestoreContract::METADATA_SHEET));
        $this->assertSame(ProductSystemRestoreContract::fields(), array_map(
            'strval',
            $workbook->getActiveSheet()->toArray(null, true, true, false)[0],
        ));
        $workbook->disconnectWorksheets();
    }

    private function assertNormalWorkbook(string $path): void
    {
        $workbook = IOFactory::load($path);
        $this->assertNull($workbook->getSheetByName(ProductSystemRestoreContract::METADATA_SHEET));
        $this->assertNull($workbook->getSheetByName(ProductSystemRestoreContract::PAYLOAD_SHEET));
        $workbook->disconnectWorksheets();
    }
}
