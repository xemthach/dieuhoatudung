<?php

namespace Tests\Feature;

use App\Enums\EquipmentSuitabilityStatus;
use App\Models\Product;
use App\Services\Calculator\EquipmentTypeRecommendationService;
use App\Services\Product\ProductEquipmentTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculatorEquipmentTypeRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_options_are_allowlisted_and_brand_neutral(): void
    {
        $options = app(EquipmentTypeRecommendationService::class)->options();

        $this->assertSame([
            'unsure', 'wall_mounted', 'cassette', 'ducted', 'ceiling_exposed', 'floor_standing',
        ], array_keys($options));
        $this->assertStringNotContainsString('Daikin', implode(' ', $options));
        $this->assertStringNotContainsString('Gree', implode(' ', $options));
    }

    public function test_wall_mounted_with_valid_target_returns_matching_wall_model(): void
    {
        $wall = $this->product('Điều hòa treo tường 18.000 BTU', 'Điều hòa > Điều hòa treo tường', 18000);
        $this->product('Điều hòa âm trần Cassette 18.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 18000);

        $result = $this->recommend(18000, 'wall_mounted');

        $this->assertSame(EquipmentSuitabilityStatus::SUITABLE_FOR_CONSIDERATION->value, $result['summary']['status']);
        $this->assertSame([$wall->id], $result['products']->pluck('id')->all());
    }

    public function test_wall_mounted_above_verified_range_is_not_recommended_and_never_auto_splits_units(): void
    {
        $this->product('Điều hòa treo tường 24.000 BTU', 'Điều hòa > Điều hòa treo tường', 24000);
        $this->product('Điều hòa âm trần Cassette 42.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 42000);

        $result = $this->recommend(42000, 'wall_mounted');

        $this->assertSame(EquipmentSuitabilityStatus::NOT_RECOMMENDED_FOR_THIS_LOAD->value, $result['summary']['status']);
        $this->assertSame([], $result['products']->pluck('id')->all());
        $this->assertNull($result['summary']['multi_unit_quantity']);
        $this->assertSame('cassette', $result['summary']['alternatives'][0]['type']);
    }

    public function test_cassette_matching_capacity_requires_installation_review_when_ceiling_is_unknown(): void
    {
        $cassette = $this->product('Điều hòa âm trần Cassette 24.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 24000);

        $result = $this->recommend(24000, 'cassette', ['cassette_ceiling_clearance' => 'unknown']);

        $this->assertSame(EquipmentSuitabilityStatus::POSSIBLE_BUT_REVIEW_REQUIRED->value, $result['summary']['status']);
        $this->assertSame([$cassette->id], $result['products']->pluck('id')->all());
    }

    public function test_cassette_with_confirmed_ceiling_can_be_considered_and_negative_answer_blocks_it(): void
    {
        $this->product('Điều hòa âm trần Cassette 24.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 24000);

        $yes = $this->recommend(24000, 'cassette', ['cassette_ceiling_clearance' => 'yes']);
        $no = $this->recommend(24000, 'cassette', ['cassette_ceiling_clearance' => 'no']);

        $this->assertSame(EquipmentSuitabilityStatus::SUITABLE_FOR_CONSIDERATION->value, $yes['summary']['status']);
        $this->assertSame(EquipmentSuitabilityStatus::NOT_RECOMMENDED_FOR_THIS_LOAD->value, $no['summary']['status']);
        $this->assertCount(0, $no['products']);
    }

    public function test_ducted_capacity_match_still_requires_design_review(): void
    {
        $ducted = $this->product('Điều hòa giấu trần Duct 48.000 BTU', 'Điều hòa > Điều hòa giấu trần nối ống gió', 48000);

        $result = $this->recommend(48000, 'ducted', ['duct_space' => 'yes']);

        $this->assertSame(EquipmentSuitabilityStatus::POSSIBLE_BUT_REVIEW_REQUIRED->value, $result['summary']['status']);
        $this->assertSame([$ducted->id], $result['products']->pluck('id')->all());
        $this->assertStringContainsString('áp suất tĩnh', implode(' ', $result['summary']['installation_notes']));
    }

    public function test_no_selected_type_returns_capacity_fit_without_claiming_final_suitability(): void
    {
        $this->product('Điều hòa âm trần Cassette 24.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 24000);

        $result = $this->recommend(24000, 'unsure');

        $this->assertSame(EquipmentSuitabilityStatus::INSUFFICIENT_DATA->value, $result['summary']['status']);
        $this->assertNotEmpty($result['products']);
        $this->assertTrue($result['summary']['brand_neutral']);
        $this->assertFalse($result['summary']['ai_required']);
    }

    public function test_type_catalog_gap_is_distinct_from_market_impossibility(): void
    {
        $result = $this->recommend(18000, 'wall_mounted');

        $this->assertSame(EquipmentSuitabilityStatus::NO_MATCHING_PRODUCT->value, $result['summary']['status']);
        $this->assertSame(0, $result['summary']['site_catalog_envelope']['count']);
        $this->assertSame(24000, $result['summary']['market_reference_envelope']['verified_max_btu']);
    }

    public function test_large_load_escalates_without_auto_recommending_vrf(): void
    {
        $this->product('Dàn nóng VRF 100.000 BTU', 'Điều hòa > Điều hòa trung tâm VRF/GMV', 100000);

        $result = $this->recommend(100000, 'unsure');

        $this->assertSame(EquipmentSuitabilityStatus::TECHNICAL_CONSULTATION_REQUIRED->value, $result['summary']['status']);
        $this->assertSame([], $result['products']->pluck('id')->all());
        $this->assertSame([], $result['summary']['alternatives']);
        $this->assertNull($result['summary']['multi_unit_quantity']);
    }

    public function test_conflicting_product_label_and_taxonomy_is_fail_closed(): void
    {
        $product = $this->product('Điều hòa giấu trần Duct 42.000 BTU', 'Điều hòa > Điều hòa tủ đứng', 42000);
        $resolved = app(ProductEquipmentTypeResolver::class)->resolve($product);

        $this->assertFalse($resolved['verified']);
        $this->assertNull($resolved['type']);
        $this->assertSame('CONFLICTING_PRODUCT_LABEL_AND_TAXONOMY', $resolved['reason']);
        $this->assertCount(0, $this->recommend(42000, 'floor_standing')['products']);
    }

    public function test_models_are_ranked_by_capacity_delta_before_price_or_brand(): void
    {
        $closer = $this->product('Cassette A 24.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 24000, 30000000);
        $this->product('Cassette B 30.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 30000, 10000000);

        $result = $this->recommend(24000, 'cassette', ['cassette_ceiling_clearance' => 'yes'], 'gia_tot');

        $this->assertSame($closer->id, $result['products']->first()->id);
        $this->assertTrue($result['products']->every(fn (Product $product): bool => $product->marketing_capacity_btu >= 24000));
    }

    public function test_http_result_and_quote_handoff_preserve_requested_type_and_status(): void
    {
        $this->product('Điều hòa âm trần Cassette 24.000 BTU', 'Điều hòa > Điều hòa âm trần Cassette', 24000);

        $response = $this->post(route('btu-calculator.calculate'), [
            'method' => 'area',
            'area_m2' => 30,
            'ceiling_height' => 3,
            'space_type' => 'nha_o',
            'people_count' => 2,
            'direct_sunlight' => false,
            'heat_equipment' => false,
            'priority' => '',
            'equipment_type' => 'cassette',
            'cassette_ceiling_clearance' => 'yes',
        ]);

        $response->assertRedirect(route('btu-calculator.index'))
            ->assertSessionHas('btu_result.equipment_recommendation.requested_type', 'cassette')
            ->assertSessionHas('quote_calculator_context.requested_equipment_type', 'cassette')
            ->assertSessionHas('quote_calculator_context.recommendation_status', EquipmentSuitabilityStatus::SUITABLE_FOR_CONSIDERATION->value);

        $this->get(route('quote.index', ['source' => 'calculator']))
            ->assertOk()
            ->assertSee('Loại máy mong muốn: Điều hòa âm trần cassette');
    }

    public function test_equipment_type_and_installation_answers_are_allowlisted(): void
    {
        $base = [
            'method' => 'area', 'area_m2' => 30, 'ceiling_height' => 3,
            'space_type' => 'nha_o', 'people_count' => 2,
            'direct_sunlight' => false, 'heat_equipment' => false,
        ];

        $this->post(route('btu-calculator.calculate'), $base + ['equipment_type' => 'App\\Jobs\\Dangerous'])
            ->assertSessionHasErrors('equipment_type');
        $this->post(route('btu-calculator.calculate'), $base + [
            'equipment_type' => 'cassette',
            'cassette_ceiling_clearance' => 'maybe',
        ])->assertSessionHasErrors('cassette_ceiling_clearance');
    }

    /** @param array<string, string|null> $answers */
    private function recommend(int $target, string $type, array $answers = [], string $priority = ''): array
    {
        return app(EquipmentTypeRecommendationService::class)->recommend($target, $type, $answers, $priority);
    }

    private function product(string $name, string $productType, int $capacity, int $price = 20000000): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'product_type' => $productType,
            'marketing_capacity_btu' => $capacity,
            'regular_price' => $price,
            'sale_price' => null,
            'is_active' => true,
            'stock_status' => 'in_stock',
        ]);
    }
}
