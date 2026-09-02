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
        $this->assertSame('KEEP', $rows[$marketing->id]['action']);
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
        $this->assertNull($product->fresh()->marketing_capacity_btu);
        $this->assertSame(0, Artisan::call('catalog:backfill-marketing-capacity', ['--product' => $product->id, '--apply' => true, '--approved' => true, '--batch' => 'test-marketing-capacity']));

        $after = $product->fresh();
        $this->assertSame(18000, $after->marketing_capacity_btu);
        $this->assertSame([$before[1], $before[2], $before[3]], [$after->technical_capacity_btu, $after->btu, $after->specs_json]);
        $ledger = storage_path('app/private/reports/marketing_capacity_backfill_test-marketing-capacity.json');
        $this->assertFileExists($ledger);
        @unlink($ledger);
    }

    private function catalogProduct(int $capacity): Product
    {
        $source = CatalogSource::create(['source_name' => 'Verified product list', 'source_type' => 'pdf', 'section_type' => 'PRODUCT_LIST', 'source_status' => 'verified']);
        $model = CatalogModel::create(['catalog_source_id' => $source->id, 'model' => 'MODEL-'.$capacity, 'verification_status' => 'verified']);
        CatalogModelField::create([
            'catalog_model_id' => $model->id, 'field_key' => 'marketing_capacity_btu', 'field_value' => (string) $capacity,
            'normalized_value' => (string) $capacity, 'source_section' => 'PRODUCT_LIST', 'verification_status' => 'verified',
            'source_page' => 1, 'source_text' => 'Commercial tier '.$capacity, 'confidence_score' => 100,
        ]);

        return Product::factory()->create(['catalog_model_id' => $model->id, 'marketing_capacity_btu' => null, 'technical_capacity_btu' => 16400, 'btu' => null]);
    }
}
