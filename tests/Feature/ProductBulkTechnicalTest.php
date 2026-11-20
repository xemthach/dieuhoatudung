<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use App\Services\Product\ProductBulkTechnicalService;
use App\Services\Product\ProductTechnicalFactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductBulkTechnicalTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        return $user;
    }

    private function products(int $count = 3)
    {
        $category = ProductCategory::firstOrCreate(['slug' => 'dieu-hoa-treo-tuong'], ['name' => 'Wall', 'is_active' => true]);
        return Product::factory()->count($count)->create([
            'product_category_id' => $category->id, 'technical_capacity_btu' => 18000,
            'capacity_kw' => 5, 'hp' => 2, 'inverter' => false, 'is_active' => true,
            'refrigerant_gas' => 'R32', 'marketing_capacity_btu' => 24000,
            'specs_json' => [['key' => 'refrigerant_gas', 'value' => 'R32', 'source_section' => 'TECHNICAL_APPENDIX']],
        ]);
    }

    private function execute(array $ids, array $fields = [], array $extras = []): array
    {
        $actor = $this->actor();
        $service = app(ProductBulkTechnicalService::class);
        $plan = $service->preview($ids, ['fields' => $fields, 'extras' => $extras, 'reason' => 'Catalogue review'], $actor);
        $this->assertSame(count($ids), $service->apply($plan['id'], $ids, $actor, true));
        $event = ProductDataGovernanceEvent::latest('id')->firstOrFail();
        $this->assertSame('BULK_TECHNICAL_UPDATE', $event->action);
        $this->assertSame($actor->id, $event->operator_id);
        $this->assertContains('super_admin', $event->descriptor_json['operator_roles']);
        $this->assertSame('MANUAL_ADMIN', $event->descriptor_json['provenance']);
        $this->assertSame(0, ProductDataGovernanceEvent::whereIn('action', ['APPROVE', 'PENDING_APPROVAL', 'NEEDS_REVIEW', 'WAITING_REVIEW'])->count());
        return $plan;
    }

    public function test_super_admin_direct_single_edit_uses_gate_and_records_audit_without_approval(): void
    {
        $product = $this->products(1)->first();
        $actor = $this->actor();
        $this->actingAs($actor);
        $this->assertTrue($actor->can('product.edit'));
        $this->assertSame(0, $actor->permissions()->count());

        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['technical_capacity_btu' => 11900, 'technical_specs_override_reason' => 'Super Admin direct edit'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame(11900, $product->fresh()->technical_capacity_btu);
        $this->assertSame('manual_override', $product->fresh()->technical_capacity_status);
        $event = ProductDataGovernanceEvent::sole();
        $this->assertSame('TECHNICAL_UPDATE', $event->action);
        $this->assertSame($actor->id, $event->operator_id);
        $this->assertSame('super_admin', $event->descriptor_json['operator_role']);
        $this->assertSame(18000, $event->before_json[$product->id]['technical_capacity_btu']);
        $this->assertSame(11900, $event->after_json[$product->id]['technical_capacity_btu']);
    }

    public function test_non_technical_edit_does_not_log_unchanged_technical_timestamps(): void
    {
        $product = $this->products(1)->first();
        $product->update(['technical_specs_source'=>'manual_override',
            'technical_specs_override_reason'=>'Initial correction', 'technical_specs_overridden_at'=>now()]);
        $this->actingAs($this->actor());
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record'=>$product->getRouteKey()])
            ->fillForm(['name'=>'Changed product name'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Changed product name', $product->fresh()->name);
        $this->assertSame(0, ProductDataGovernanceEvent::count());
    }

    public function test_regular_editor_uses_canonical_permission_and_revoked_access_is_denied_at_apply(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name'=>'product.edit','guard_name'=>'web']);
        $actor->givePermissionTo($permission);
        $this->assertFalse($actor->isSuperAdmin());
        $products = $this->products(1);
        $service = app(ProductBulkTechnicalService::class);
        $data = ['fields'=>['technical_capacity_btu'=>['operation'=>'set','value'=>11900]],'reason'=>'Authorized editor'];
        $plan = $service->preview($products->modelKeys(), $data, $actor);
        $service->apply($plan['id'], $products->modelKeys(), $actor, true);
        $this->assertSame(11900, $products[0]->fresh()->technical_capacity_btu);
        $plan = $service->preview($products->modelKeys(), $data, $actor);
        $actor->revokePermissionTo($permission);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->apply($plan['id'], $products->modelKeys(), $actor, true);
    }

    public function test_single_field_patch_preserves_other_fields_and_unselected_product(): void
    {
        $products = $this->products(4);
        $this->execute($products->take(3)->modelKeys(), [
            'technical_capacity_btu' => ['operation' => 'set', 'value' => 11900],
            'capacity_kw' => ['operation' => 'keep', 'value' => null],
        ]);
        foreach ($products->take(3) as $product) {
            $product->refresh();
            $this->assertSame(11900, $product->technical_capacity_btu);
            $this->assertSame('5.00', $product->capacity_kw);
            $this->assertSame('2.0', $product->hp);
            $this->assertSame(24000, $product->marketing_capacity_btu);
            $this->get('/san-pham?btu[]=9000-12000')->assertSee('id="product-card-'.$product->id.'"', false);
            $this->get('/san-pham?btu[]=18000')->assertDontSee('id="product-card-'.$product->id.'"', false);
        }
        $this->assertSame(18000, $products->last()->fresh()->technical_capacity_btu);
        $event = ProductDataGovernanceEvent::firstOrFail();
        $this->assertCount(3, $event->product_ids_json);
        $this->assertNotEmpty($event->batch_uuid);
        $this->assertSame(18000, $event->before_json[$products[0]->id]['technical_capacity_btu']);
        $this->assertSame(11900, $event->after_json[$products[0]->id]['technical_capacity_btu']);
    }

    public function test_inverter_patch_keeps_unrelated_source_fact_precedence(): void
    {
        $p = $this->products(1)->first();
        $p->update(['capacity_kw' => 3.5, 'specs_json' => [['key'=>'capacity_kw','value'=>'3.5 / 4.0','source_section'=>'TECHNICAL_APPENDIX']]]);
        $this->execute([$p->id], ['inverter' => ['operation'=>'set','value'=>'1']]);
        $this->assertSame('3.5 / 4.0', app(ProductTechnicalFactResolver::class)->value($p->fresh(), 'capacity_kw'));
        $this->assertSame('3.50', $p->fresh()->capacity_kw);
        $this->execute([$p->id], ['capacity_kw'=>['operation'=>'set','value'=>3.5]]);
        $this->assertEquals(3.5, app(ProductTechnicalFactResolver::class)->value($p->fresh(), 'capacity_kw'));
    }

    public function test_all_standard_fields_are_explicitly_written_through_the_writer(): void
    {
        $p = $this->products(1)->first();
        $values = ['technical_capacity_btu' => 11900, 'capacity_kw' => 3.5, 'hp' => 1.5,
            'inverter' => '0', 'cooling_type' => '1_chieu', 'voltage' => '220V', 'phase' => '1',
            'frequency' => '50Hz', 'refrigerant_gas' => 'R410A', 'power_consumption' => '1 kW',
            'airflow' => '500', 'noise_level' => '20', 'recommended_area' => '20 m2',
            'indoor_dimensions' => '1x2x3', 'outdoor_dimensions' => '2x3x4', 'weight' => '10 kg'];
        $fields = [];
        foreach ($values as $key => $value) $fields[$key] = ['operation' => 'set', 'value' => $value];
        $this->execute([$p->id], $fields);
        $p->refresh();
        $this->assertSame(11900, $p->technical_capacity_btu);
        $this->assertSame('manual_override', $p->technical_capacity_status);
        $this->assertSame('3.50', $p->capacity_kw);
        $this->assertSame('1.5', $p->hp);
        $this->assertFalse($p->inverter);
        $this->assertSame('1', $p->technical_phase);
        $this->assertSame('50Hz', $p->technical_frequency);
        $this->assertSame('manual_override', $p->technical_specs_source);
        $transfer = new Product($p->getAttributes());
        $transfer->specs_json = $p->specs_json;
        $transfer->technical_specs_source = 'PRODUCT_TRANSFER';
        $this->assertEquals(3.5, app(ProductTechnicalFactResolver::class)->value($transfer, 'capacity_kw'));
        $this->assertEquals(1.5, app(ProductTechnicalFactResolver::class)->value($transfer, 'hp'));
        foreach (array_diff_key($values, array_flip(['technical_capacity_btu','capacity_kw','hp','inverter','phase','frequency'])) as $key => $value) {
            $this->assertSame($value, $p->$key);
        }
    }

    public function test_explicit_clear_masks_source_without_deleting_it(): void
    {
        $p = $this->products(1)->first();
        $this->execute([$p->id], ['refrigerant_gas' => ['operation' => 'clear']]);
        $p->refresh();
        $this->assertNull($p->refrigerant_gas);
        $this->assertNull(app(ProductTechnicalFactResolver::class)->value($p, 'refrigerant_gas'));
        $this->assertSame('R32', $p->specs_json[0]['value']);
        $this->assertSame(18000, $p->technical_capacity_btu);
    }

    public function test_features_upsert_by_normalized_name_remove_by_name_and_preserve_other_features(): void
    {
        $products = $this->products();
        $ids = $products->modelKeys();
        $first = $products[0];
        $first->update(['specs_json' => [...$first->specs_json, ['key'=>'tính năng','value'=>'Old feature']]]);
        $this->execute($ids, [], [
            ['operation' => 'ADD_OR_UPDATE', 'key' => 'Tính năng', 'value' => 'Streamer'],
            ['operation' => 'ADD_OR_UPDATE', 'key' => 'Wi-Fi', 'value' => 'Có'],
        ]);
        $this->execute($ids, [], [['operation' => 'ADD_OR_UPDATE', 'key' => 'TÍNH NĂNG', 'value' => 'Streamer']]);
        foreach ($products as $p) {
            $p->refresh();
            $this->assertCount(1, collect($p->specs_json)->where('source_section', 'MANUAL_EXTENDED')->filter(fn ($r) => $r['value'] === 'Streamer'));
            $this->get(route('product.show', $p->slug))->assertOk()->assertSee('Streamer');
        }
        $this->execute($ids, [], [['operation' => 'REMOVE', 'key' => ' tính năng ']]);
        $p = $products[0]->fresh();
        $this->assertCount(1, collect($p->specs_json)->where('source_section', 'MANUAL_EXTENDED'));
        $this->assertSame('R32', $p->specs_json[0]['value']);
    }

    public function test_invalid_empty_values_and_standard_collisions_are_rejected_without_writes(): void
    {
        $p = $this->products(1)->first();
        $actor = $this->actor();
        foreach ([
            ['fields' => ['technical_capacity_btu' => ['operation' => 'set', 'value' => '']]],
            ['fields' => ['technical_capacity_btu' => ['operation' => 'set', 'value' => 11900.5]]],
            ['fields' => ['inverter' => ['operation' => 'clear']]],
            ['fields' => ['marketing_capacity_btu' => ['operation' => 'set', 'value' => 12000]]],
            ['extras' => [['operation' => 'ADD_OR_UPDATE', 'key' => 'Công suất BTU', 'value' => '1']]],
            ['extras' => [['operation' => 'ADD_OR_UPDATE', 'key' => 'kW', 'value' => '1']]],
        ] as $bad) {
            try {
                app(ProductBulkTechnicalService::class)->preview([$p->id], $bad + ['reason' => 'Test'], $actor);
                $this->fail('Invalid patch accepted');
            } catch (ValidationException) {
                $this->assertSame(18000, $p->fresh()->technical_capacity_btu);
            }
        }
        $this->assertSame(0, ProductDataGovernanceEvent::count());
    }

    public function test_stale_second_product_rolls_back_first_product_and_audit(): void
    {
        $p = $this->products(2); $actor = $this->actor(); $service = app(ProductBulkTechnicalService::class);
        $plan = $service->preview($p->modelKeys(), ['reason' => 'Test', 'fields' => [
            'technical_capacity_btu' => ['operation' => 'set', 'value' => 11900]]], $actor);
        $p[1]->update(['name' => 'Concurrent edit']);
        try {
            $service->apply($plan['id'], $p->modelKeys(), $actor, true);
            $this->fail('Stale batch accepted');
        } catch (ValidationException) {
            $this->assertSame(18000, $p[0]->fresh()->technical_capacity_btu);
            $this->assertSame(0, ProductDataGovernanceEvent::count());
        }
    }

    public function test_unauthorized_user_cannot_prepare_a_batch(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ProductBulkTechnicalService::class)->preview($this->products(1)->modelKeys(), ['reason' => 'Test'], User::factory()->create());
    }

    public function test_server_plan_is_bound_to_selection_and_operator(): void
    {
        $p = $this->products(2); $actor = $this->actor(); $service = app(ProductBulkTechnicalService::class);
        $plan = $service->preview([$p[0]->id], ['reason' => 'Test', 'fields' => [
            'inverter' => ['operation' => 'set', 'value' => '1']]], $actor);
        $this->expectException(ValidationException::class);
        $service->apply($plan['id'], $p->modelKeys(), $actor, true);
    }

    public function test_public_search_sort_and_cards_follow_the_changed_value(): void
    {
        $p = $this->products(2);
        $this->execute([$p[0]->id], ['technical_capacity_btu'=>['operation'=>'set','value'=>11900],
            'capacity_kw'=>['operation'=>'set','value'=>3.5], 'hp'=>['operation'=>'set','value'=>1.5]]);
        $this->get('/san-pham?btu[]=9000-12000')->assertSee('11.900 BTU')->assertSee('3,5 kW')->assertSee('1.5 HP');
        $request = \Illuminate\Http\Request::create('/san-pham?sort=btu_asc');
        $ids = app(\App\Services\Product\ProductFilterService::class)->apply(Product::whereKey($p->modelKeys()), $request)->pluck('id')->all();
        $this->assertSame($p->modelKeys(), $ids);
        $this->assertSame([$p[0]->id], app(\App\Services\Search\ProductSearchService::class)->search('11900')->pluck('id')->all());
    }

    public function test_action_executes_the_reviewed_selection_through_livewire(): void
    {
        $products = $this->products(2);
        $actor = $this->actor();
        $this->actingAs($actor);
        $data = ['fields'=>['technical_capacity_btu'=>['operation'=>'set','value'=>11900]],'reason'=>'Livewire reviewed'];
        $plan = app(ProductBulkTechnicalService::class)->preview($products->modelKeys(), $data, $actor);
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\ListProducts::class)
            ->callTableBulkAction('bulk_tech_attributes', $products, $data + ['preview_token'=>$plan['id'],'confirmed'=>true])
            ->assertHasNoErrors();
        $this->assertSame(11900, $products[0]->fresh()->technical_capacity_btu);
    }

    public function test_sync_batch_of_300_is_atomic_and_records_one_event(): void
    {
        $p = $this->products(300);
        $started = microtime(true);
        $this->execute($p->modelKeys(), ['frequency' => ['operation' => 'set', 'value' => '50Hz']]);
        $this->assertSame(1, ProductDataGovernanceEvent::count());
        $this->assertCount(300, ProductDataGovernanceEvent::first()->product_ids_json);
        fwrite(STDERR, 'BULK_300_SECONDS='.round(microtime(true) - $started, 3).PHP_EOL);
    }
}
