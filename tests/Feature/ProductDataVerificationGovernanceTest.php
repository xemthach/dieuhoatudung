<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDataGovernanceEvent;
use App\Models\User;
use App\Services\Product\ProductDataApprovalCenterService;
use App\Services\Product\ProductDataApprovalService;
use App\Services\Product\ProductMarketingCapacityAuditService;
use App\Services\Product\ProductTaxonomyAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ProductDataVerificationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_partial_taxonomy_apply_verifies_only_deterministic_rows(): void
    {
        [$product, $probe] = $this->taxonomyFixture();
        $actor = $this->superAdmin();
        $audit = app(ProductTaxonomyAuditService::class)->audit();
        app(ProductDataApprovalService::class)->approveTaxonomy($audit['ledger_digest'], $audit['rows'], 'Reviewed exact SkyAir matrix', $actor);

        $this->assertSame(0, Artisan::call('catalog:apply-product-taxonomy', [
            '--apply' => true, '--approved' => true, '--batch' => 'taxonomy-test-batch', '--digest' => $audit['ledger_digest'],
        ]));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $result['APPLIED']);
        $this->assertSame(1, $result['SKIPPED_UNRESOLVED']);
        $this->assertSame('VERIFIED', $product->fresh()->taxonomy_status);
        $this->assertSame('dieu-hoa-am-tran-cassette', $product->fresh()->category->slug);
        $this->assertNull($probe->fresh()->taxonomy_status);
        $this->assertSame('dieu-hoa-treo-tuong', $probe->fresh()->category->slug);
    }

    public function test_taxonomy_apply_requires_exact_current_approved_digest(): void
    {
        [$product] = $this->taxonomyFixture();
        $actor = $this->superAdmin();
        $audit = app(ProductTaxonomyAuditService::class)->audit();
        app(ProductDataApprovalService::class)->approveTaxonomy($audit['ledger_digest'], $audit['rows'], 'Approve current ledger', $actor);
        $product->forceFill(['model_code' => 'CHANGED-AFTER-APPROVAL'])->save();

        $this->assertSame(1, Artisan::call('catalog:apply-product-taxonomy', [
            '--apply' => true, '--approved' => true, '--batch' => 'stale', '--digest' => $audit['ledger_digest'],
        ]));
        $this->assertNull($product->fresh()->taxonomy_status);
    }

    public function test_admin_approved_marketing_group_becomes_a_verified_apply_proposal(): void
    {
        [$product] = $this->taxonomyFixture();
        $actor = $this->superAdmin();
        $review = app(ProductDataApprovalCenterService::class)->marketingReview();
        $group = collect($review['groups'])->first(fn (array $group): bool => in_array($product->id, $group['product_ids'], true));
        $event = app(ProductDataApprovalService::class)->approveMarketingGroup($group, 18000, 'Approved commercial sales tier', $actor);

        $row = app(ProductMarketingCapacityAuditService::class)->audit($product->id)['products'][0];
        $this->assertSame('PROPOSE_UPDATE', $row['action']);
        $this->assertSame(18000, $row['proposed_marketing']);
        $this->assertSame($event->mapping_digest, $row['mapping_digest']);

        Artisan::call('catalog:backfill-marketing-capacity', ['--product' => $product->id, '--batch' => 'approved-map']);
        $this->assertSame(0, Artisan::call('catalog:backfill-marketing-capacity', [
            '--product' => $product->id, '--batch' => 'approved-map', '--apply' => true, '--approved' => true,
        ]));
        $restored = $product->fresh();
        $this->assertSame(18000, $restored->marketing_capacity_btu);
        $this->assertSame('VERIFIED', $restored->marketing_capacity_status);
        $this->assertSame($event->mapping_digest, $restored->marketing_capacity_mapping_digest);
    }

    public function test_permission_and_append_only_audit_event_are_enforced(): void
    {
        $this->taxonomyFixture();
        $audit = app(ProductTaxonomyAuditService::class)->audit();
        $actor = User::factory()->create();

        try {
            app(ProductDataApprovalService::class)->approveTaxonomy($audit['ledger_digest'], $audit['rows'], 'Unauthorized', $actor);
            $this->fail('Unauthorized approval was not blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, ProductDataGovernanceEvent::count());

        $admin = $this->superAdmin();
        app(ProductDataApprovalService::class)->approveTaxonomy($audit['ledger_digest'], $audit['rows'], 'Authorized review', $admin);
        $approval = ProductDataGovernanceEvent::latest('id')->firstOrFail();
        $this->assertSame('UNREVIEWED', $approval->before_json['action']);
        $this->assertSame('APPROVE', $approval->after_json['action']);
        app(ProductDataApprovalService::class)->recordReviewAction(
            ProductDataApprovalService::TAXONOMY, 'REVOKE', $audit['ledger_digest'], [], 'Source changed', $admin,
        );
        $this->assertSame(2, ProductDataGovernanceEvent::count());
        $this->assertFalse(app(ProductDataApprovalService::class)->taxonomyLedgerIsApproved($audit['ledger_digest']));

        $latest = ProductDataGovernanceEvent::latest('id')->firstOrFail();
        $this->expectException(\LogicException::class);
        $latest->update(['reason' => 'Attempted historical rewrite']);
    }

    public function test_governance_audit_event_cannot_be_deleted(): void
    {
        $this->taxonomyFixture();
        $audit = app(ProductTaxonomyAuditService::class)->audit();
        app(ProductDataApprovalService::class)->approveTaxonomy(
            $audit['ledger_digest'], $audit['rows'], 'Immutable audit evidence', $this->superAdmin(),
        );

        $this->expectException(\LogicException::class);
        ProductDataGovernanceEvent::latest('id')->firstOrFail()->delete();
    }

    public function test_rejected_taxonomy_product_is_excluded_from_an_approved_partial_apply(): void
    {
        [$product] = $this->taxonomyFixture();
        $actor = $this->superAdmin();
        $audit = app(ProductTaxonomyAuditService::class)->audit();
        $approvals = app(ProductDataApprovalService::class);
        $approvals->approveTaxonomy($audit['ledger_digest'], $audit['rows'], 'Approve deterministic ledger', $actor);
        $approvals->recordReviewAction(
            ProductDataApprovalService::TAXONOMY, 'REJECT', $audit['ledger_digest'], [$product->id],
            'Product requires an additional source review', $actor,
        );

        $this->assertSame(0, Artisan::call('catalog:apply-product-taxonomy', [
            '--apply' => true, '--approved' => true, '--batch' => 'taxonomy-reject-test', '--digest' => $audit['ledger_digest'],
        ]));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $result['SKIPPED_REJECTED']);
        $this->assertNull($product->fresh()->taxonomy_status);
    }

    public function test_approved_marketing_mapping_fails_closed_when_group_source_state_changes(): void
    {
        [$product] = $this->taxonomyFixture();
        $actor = $this->superAdmin();
        $review = app(ProductDataApprovalCenterService::class)->marketingReview();
        $group = collect($review['groups'])->first(fn (array $candidate): bool => in_array($product->id, $candidate['product_ids'], true));
        app(ProductDataApprovalService::class)->approveMarketingGroup($group, 18000, 'Approve exact source group', $actor);

        $product->forceFill(['capacity_kw' => '9.99'])->save();
        $row = app(ProductMarketingCapacityAuditService::class)->audit($product->id)['products'][0];

        $this->assertSame('CONFLICT', $row['action']);
        $this->assertSame('STALE_APPROVED_MARKETING_MAPPING_SOURCE_OR_GROUP_CHANGED', $row['reason']);
    }

    private function taxonomyFixture(): array
    {
        $brand = Brand::factory()->create(['slug' => 'daikin']);
        $wall = ProductCategory::factory()->create(['id' => 29, 'slug' => 'dieu-hoa-treo-tuong']);
        ProductCategory::factory()->create(['id' => 24, 'slug' => 'dieu-hoa-am-tran-cassette']);
        $product = Product::factory()->create([
            'brand_id' => $brand->id, 'product_category_id' => $wall->id,
            'sku' => 'FCTF50AVM-RZF50DVM', 'model_code' => 'FCTF50AVM/RZF50DVM',
            'marketing_capacity_btu' => null,
        ]);
        $probe = Product::factory()->create([
            'brand_id' => $brand->id, 'product_category_id' => $wall->id,
            'sku' => 'LOCAL-AI-RETRY-PROBE-20260830', 'model_code' => 'LOCAL-AI-RETRY-PROBE-20260830',
        ]);

        return [$product, $probe];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }
}
