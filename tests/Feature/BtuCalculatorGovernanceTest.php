<?php

namespace Tests\Feature;

use App\Models\BtuCalculation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Calculator\BtuCalculatorService;
use App\Services\Product\ProductMarketingCapacityQueryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BtuCalculatorGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_never_include_under_sized_unknown_or_vrf_products(): void
    {
        $racCategory = ProductCategory::factory()->create(['name' => 'Điều hòa tủ đứng']);
        $vrfCategory = ProductCategory::factory()->create(['name' => 'VRF Outdoor']);
        $unknownCategory = ProductCategory::factory()->create(['name' => 'Thiết bị khác']);

        $underSized = Product::factory()->create([
            'product_category_id' => $racCategory->id,
            'marketing_capacity_btu' => 24000,
        ]);
        $eligible = Product::factory()->create([
            'product_category_id' => $racCategory->id,
            'marketing_capacity_btu' => 36000,
        ]);
        $vrf = Product::factory()->create([
            'product_category_id' => $vrfCategory->id,
            'marketing_capacity_btu' => 36000,
        ]);
        $unknown = Product::factory()->create([
            'product_category_id' => $unknownCategory->id,
            'marketing_capacity_btu' => 36000,
        ]);

        $products = app(BtuCalculatorService::class)->matchProducts(28000);

        $this->assertSame([$eligible->id], $products->pluck('id')->all());
        $this->assertNotContains($underSized->id, $products->pluck('id')->all());
        $this->assertNotContains($vrf->id, $products->pluck('id')->all());
        $this->assertNotContains($unknown->id, $products->pluck('id')->all());
        $this->assertTrue($products->every(
            fn (Product $product): bool => app(ProductMarketingCapacityQueryAdapter::class)->value($product) >= 28000,
        ));
    }

    public function test_anonymous_calculation_succeeds_without_persisting_contact_or_sending_a_lead(): void
    {
        $response = $this->post(route('btu-calculator.calculate'), $this->validPayload());

        $response->assertRedirect(route('btu-calculator.index'));
        $response->assertSessionHas('btu_result.recommended_btu', 18000);
        $this->assertDatabaseCount('btu_calculations', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_contact_request_persists_the_formula_version(): void
    {
        $response = $this->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), [
            'full_name' => 'Khách thử nghiệm',
            'phone' => '0901234567',
        ]));

        $response->assertRedirect(route('btu-calculator.index'));
        $this->assertDatabaseHas('btu_calculations', [
            'phone' => '0901234567',
            'rule_version' => 'consumer-estimate-v2',
            'ip_address' => null,
            'user_agent' => null,
        ]);
        $this->assertDatabaseHas('leads', [
            'phone' => '0901234567',
            'need_type' => 'btu_calculator',
        ]);
    }

    public function test_nested_and_unsupported_inputs_are_rejected_server_side(): void
    {
        $this->from(route('btu-calculator.index'))
            ->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), [
                'area_m2' => ['30'],
                'priority' => 'van_hanh_ben_bi',
            ]))
            ->assertRedirect(route('btu-calculator.index'))
            ->assertSessionHasErrors(['area_m2', 'priority']);

        $this->assertDatabaseCount('btu_calculations', 0);
    }

    public function test_page_uses_reference_language_and_visible_faq_matches_schema(): void
    {
        $response = $this->get(route('btu-calculator.index'));

        $response->assertOk()
            ->assertSee('ước tính nhóm công suất BTU tham khảo')
            ->assertSee('name="method"', false)
            ->assertSee('value="area"', false)
            ->assertSee('value="volume"', false)
            ->assertSee('Kết quả mang tính tham khảo')
            ->assertDontSee('hệ thống tính chính xác');

        foreach (config('hvac.btu.faq') as $faq) {
            $response->assertSee($faq['question']);
            $response->assertSee($faq['answer']);
        }

        $response->assertSee('FAQPage');
    }

    public function test_method_is_required_allowlisted_and_volume_requires_height(): void
    {
        $payload = $this->validPayload();
        unset($payload['method']);
        $this->post(route('btu-calculator.calculate'), $payload)
            ->assertSessionHasErrors('method');

        $this->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), ['method' => 'Some\\Class']))
            ->assertSessionHasErrors('method');

        $volume = array_merge($this->validPayload(), ['method' => 'volume']);
        unset($volume['ceiling_height']);
        $this->post(route('btu-calculator.calculate'), $volume)
            ->assertSessionHasErrors('ceiling_height');
    }

    public function test_volume_contact_calculation_persists_method_and_rule_version(): void
    {
        $response = $this->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), [
            'method' => 'volume',
            'ceiling_height' => 4,
            'phone' => '0901234567',
        ]));

        $response->assertRedirect(route('btu-calculator.index'));
        $response->assertSessionHas('btu_result.method', 'volume');
        $response->assertSessionHas('btu_products', []);
        $this->assertDatabaseHas('btu_calculations', [
            'phone' => '0901234567',
            'calculation_method' => 'volume',
            'rule_version' => 'volume-consumer-estimate-v2',
        ]);
    }

    public function test_volume_height_abnormal_inputs_are_bounded_server_side(): void
    {
        foreach ([null, 0, -1, 15.01, ['3'], 'not-a-number'] as $height) {
            $this->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), [
                'method' => 'volume',
                'ceiling_height' => $height,
            ]))->assertSessionHasErrors('ceiling_height');
        }

        // Laravel numeric input intentionally accepts a bounded scientific
        // notation value; it resolves deterministically to 3.0 metres.
        $this->post(route('btu-calculator.calculate'), array_merge($this->validPayload(), [
            'method' => 'volume',
            'ceiling_height' => '3e0',
            ]))->assertSessionDoesntHaveErrors('ceiling_height');
    }

    public function test_result_separates_calculated_tier_from_nearest_available_product(): void
    {
        $racCategory = ProductCategory::factory()->create(['name' => 'Điều hòa tủ đứng']);
        Product::factory()->create([
            'product_category_id' => $racCategory->id,
            'marketing_capacity_btu' => 24000,
        ]);

        $response = $this->post(route('btu-calculator.calculate'), $this->validPayload());

        $response->assertSessionHas('btu_result.calculated_btu', 17999);
        $response->assertSessionHas('btu_result.recommended_btu', 18000);
        $response->assertSessionHas('btu_result.nearest_available_product_btu', 24000);
        $response->assertSessionHas('btu_result.catalog_gap_btu', 6000);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'method' => 'area',
            'area_m2' => 30,
            'ceiling_height' => 3,
            'space_type' => 'nha_o',
            'people_count' => 2,
            'direct_sunlight' => false,
            'heat_equipment' => false,
            'priority' => '',
        ];
    }
}
