<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\CatalogModel;
use App\Models\CatalogModelField;
use App\Models\CatalogSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MarketingCapacityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_capacity_evidence_without_mutating_products(): void
    {
        $baseline = app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit()['stats'];
        $marketing = Product::factory()->create(['marketing_capacity_btu' => 18000]);
        $legacy = Product::factory()->create(['marketing_capacity_btu' => null, 'btu' => 18000]);
        $technical = Product::factory()->create(['marketing_capacity_btu' => null, 'technical_capacity_btu' => 16400, 'btu' => null]);
        $range = Product::factory()->create(['marketing_capacity_btu' => null, 'btu' => null, 'specs_json' => [['key' => 'capacity_btu', 'value' => '18000 / 19100']]]);
        $noEvidence = Product::factory()->create(['marketing_capacity_btu' => null, 'technical_capacity_btu' => null, 'btu' => null, 'specs_json' => null]);
        $catalogProduct = $this->catalogProduct(48000);

        $before = Product::query()->orderBy('id')->get()->map(fn (Product $product) => [$product->id, $product->marketing_capacity_btu, $product->technical_capacity_btu, $product->btu])->all();
        $this->assertSame(0, Artisan::call('catalog:audit-marketing-capacity', ['--json' => true]));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $after = Product::query()->orderBy('id')->get()->map(fn (Product $product) => [$product->id, $product->marketing_capacity_btu, $product->technical_capacity_btu, $product->btu])->all();

        $this->assertTrue($result['read_only']);
        $this->assertSame($baseline['marketing_present'] + 1, $result['stats']['marketing_present'], 'marketing_present');
        $this->assertSame($baseline['safe_backfill'] + 1, $result['stats']['safe_backfill'], 'safe_backfill');
        $this->assertSame($baseline['legacy_btu_present'] + 1, $result['stats']['legacy_btu_present'], 'legacy_btu_present');
        $this->assertSame($baseline['technical_only'] + 2, $result['stats']['technical_only'], 'technical_only');
        $this->assertSame($baseline['ambiguous_range'] + 1, $result['stats']['ambiguous_range'], 'ambiguous_range');
        $this->assertSame($baseline['no_reliable_capacity_evidence'] + 1, $result['stats']['no_reliable_capacity_evidence'], 'no_reliable_capacity_evidence');
        $rows = collect($result['products'])->keyBy('product_id');
        $this->assertSame('CONFLICT', $rows[$marketing->id]['action']);
        $this->assertSame('CURRENT_MARKETING_VALUE_UNVERIFIED', $rows[$marketing->id]['reason']);
        $this->assertSame('PROPOSE_UPDATE', $rows[$catalogProduct->id]['action']);
        $this->assertSame(48000, $rows[$catalogProduct->id]['proposed_marketing']);
        $this->assertSame('AMBIGUOUS', $rows[$legacy->id]['action']);
        $this->assertSame('AMBIGUOUS', $rows[$technical->id]['action']);
        $this->assertSame('AMBIGUOUS', $rows[$range->id]['action']);
        $this->assertSame('NO_EVIDENCE', $rows[$noEvidence->id]['action']);
        $this->assertSame($before, $after);
    }

    public function test_backfill_defaults_to_dry_run_and_applies_only_verified_product_list_evidence(): void
    {
        $product = $this->catalogProduct(18000);
        $before = [$product->marketing_capacity_btu, $product->technical_capacity_btu, $product->btu, $product->specs_json];

        $this->assertSame(0, Artisan::call('catalog:backfill-marketing-capacity', ['--product' => $product->id]));
        $dryRun = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNull($product->fresh()->marketing_capacity_btu);
        $this->assertSame(0, Artisan::call('catalog:backfill-marketing-capacity', ['--product' => $product->id, '--apply' => true, '--approved' => true, '--batch' => $dryRun['batch_uuid']]));

        $after = $product->fresh();
        $this->assertSame(18000, $after->marketing_capacity_btu);
        $this->assertSame([$before[1], $before[2], $before[3]], [$after->technical_capacity_btu, $after->btu, $after->specs_json]);
        $ledger = storage_path('app/private/reports/marketing_capacity_backfill_'.$dryRun['batch_uuid'].'.apply.json');
        $this->assertFileExists($ledger);
        @unlink(storage_path('app/private/reports/marketing_capacity_backfill_'.$dryRun['batch_uuid'].'.dry-run.json'));
        @unlink($ledger);
    }

    public function test_conflicting_verified_product_list_values_fail_closed(): void
    {
        $product = $this->catalogProduct(18000);
        $otherSource = CatalogSource::create(['brand_id' => $product->brand_id, 'source_name' => 'Other list', 'source_type' => 'xlsx', 'section_type' => 'PRODUCT_LIST', 'source_status' => 'verified']);
        $otherModel = CatalogModel::create([
            'catalog_source_id' => $otherSource->id,
            'model' => $product->model_code,
            'normalized_model' => app(\App\Services\HVAC\HVACTechnicalNormalizer::class)->normalizeModel($product->model_code),
            'verification_status' => 'verified',
        ]);
        CatalogModelField::create([
            'catalog_model_id' => $otherModel->id, 'field_key' => 'marketing_capacity_btu',
            'field_value' => '24000', 'normalized_value' => '24000', 'source_section' => 'PRODUCT_LIST',
            'verification_status' => 'verified', 'source_text' => 'Conflicting tier 24000',
        ]);

        $row = collect(app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit($product->id)['products'])->first();

        $this->assertSame('CONFLICT', $row['action']);
        $this->assertSame('CONFLICTING_VERIFIED_PRODUCT_LIST_VALUES', $row['reason']);
        $this->assertNull($product->fresh()->marketing_capacity_btu);
    }

    public function test_apply_is_blocked_without_the_exact_reviewed_dry_run_ledger(): void
    {
        $product = $this->catalogProduct(36000);

        $this->assertSame(1, Artisan::call('catalog:backfill-marketing-capacity', [
            '--product' => $product->id,
            '--apply' => true,
            '--approved' => true,
            '--batch' => 'missing-reviewed-ledger',
        ]));
        $this->assertStringContainsString('reviewed dry-run ledger not found', Artisan::output());
        $this->assertNull($product->fresh()->marketing_capacity_btu);
    }

    public function test_existing_canonical_value_that_disagrees_with_verified_evidence_is_a_conflict(): void
    {
        $product = $this->catalogProduct(24000);
        $product->forceFill(['marketing_capacity_btu' => 18000])->save();

        $row = app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit($product->id)['products'][0];

        $this->assertSame('CONFLICT', $row['action']);
        $this->assertSame('CANONICAL_VALUE_DIFFERS_FROM_VERIFIED_PRODUCT_LIST', $row['reason']);
        $this->assertSame(18000, $product->fresh()->marketing_capacity_btu);
    }

    public function test_existing_marketing_value_without_product_list_authority_is_not_automatically_kept(): void
    {
        $product = Product::factory()->create(['marketing_capacity_btu' => 18000]);

        $row = app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit($product->id)['products'][0];

        $this->assertSame('CONFLICT', $row['action']);
        $this->assertSame('CURRENT_MARKETING_VALUE_UNVERIFIED', $row['reason']);
        $this->assertSame(18000, $product->fresh()->marketing_capacity_btu);
    }

    public function test_product_list_evidence_without_exact_brand_lineage_fails_closed(): void
    {
        $brand = \App\Models\Brand::factory()->create();
        $source = CatalogSource::create([
            'brand_id' => null,
            'source_name' => 'Unscoped product list',
            'source_type' => 'pdf',
            'section_type' => 'PRODUCT_LIST',
            'source_status' => 'verified',
        ]);
        $model = CatalogModel::create([
            'catalog_source_id' => $source->id,
            'model' => 'SHARED-MODEL',
            'normalized_model' => app(\App\Services\HVAC\HVACTechnicalNormalizer::class)->normalizeModel('SHARED-MODEL'),
            'verification_status' => 'verified',
        ]);
        CatalogModelField::create([
            'catalog_model_id' => $model->id,
            'field_key' => 'marketing_capacity_btu',
            'field_value' => '18000',
            'normalized_value' => '18000',
            'source_section' => 'PRODUCT_LIST',
            'verification_status' => 'verified',
        ]);
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'model_code' => 'SHARED-MODEL',
            'marketing_capacity_btu' => null,
            'technical_capacity_btu' => null,
            'btu' => null,
            'specs_json' => null,
        ]);

        $row = app(\App\Services\Product\ProductMarketingCapacityAuditService::class)->audit($product->id)['products'][0];

        $this->assertSame('NO_EVIDENCE', $row['action']);
        $this->assertSame('NO_RELIABLE_COMMERCIAL_CAPACITY_EVIDENCE', $row['reason']);
        $this->assertNull($product->fresh()->marketing_capacity_btu);
    }

    private function catalogProduct(int $capacity): Product
    {
        $brand = \App\Models\Brand::factory()->create();
        $source = CatalogSource::create(['brand_id' => $brand->id, 'source_name' => 'Verified product list', 'source_type' => 'pdf', 'section_type' => 'PRODUCT_LIST', 'source_status' => 'verified']);
        $model = CatalogModel::create(['catalog_source_id' => $source->id, 'model' => 'MODEL-'.$capacity, 'verification_status' => 'verified']);
        CatalogModelField::create([
            'catalog_model_id' => $model->id, 'field_key' => 'marketing_capacity_btu', 'field_value' => (string) $capacity,
            'normalized_value' => (string) $capacity, 'source_section' => 'PRODUCT_LIST', 'verification_status' => 'verified',
            'source_page' => 1, 'source_text' => 'Commercial tier '.$capacity, 'confidence_score' => 100,
        ]);

        return Product::factory()->create(['brand_id' => $brand->id, 'catalog_model_id' => $model->id, 'marketing_capacity_btu' => null, 'technical_capacity_btu' => 16400, 'btu' => null]);
    }
}
