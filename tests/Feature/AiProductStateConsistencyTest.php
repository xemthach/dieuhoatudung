<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AiProductLiveStatusService;
use App\Services\Product\ProductContentEligibilityPolicy;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiProductStateConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_stale_product_review_status_without_item_or_draft_is_not_review_required(): void
    {
        $product = Product::factory()->create(['ai_status' => 'needs_review']);

        $state = app(AiProductContentStateResolver::class)->resolve($product);
        $live = app(AiProductLiveStatusService::class)->forProduct($product->id);

        $this->assertSame('NOT_GENERATED', $state['status']);
        $this->assertSame('STALE_DENORMALIZED_STATUS', $state['state_issue']);
        $this->assertFalse($state['reviewable']);
        $this->assertSame('NOT_GENERATED', $live['status']['key']);
        $this->assertFalse($live['review_required']);
    }

    public function test_review_required_always_has_an_actionable_draft(): void
    {
        [$product] = $this->makeState('REVIEW_REQUIRED', 'needs_review');

        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('REVIEW_REQUIRED', $state['status']);
        $this->assertTrue($state['reviewable']);
        $this->assertNotNull(app(AiProductContentStateResolver::class)->reviewableDraft($product));
    }

    public function test_review_status_without_persisted_draft_is_blocked_not_reviewable(): void
    {
        $product = Product::factory()->create(['ai_status' => 'needs_review']);
        $job = $this->job();
        AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'needs_review',
            'canonical_status' => 'REVIEW_REQUIRED',
        ]);

        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('BLOCKED', $state['status']);
        $this->assertSame('REVIEWABLE_DRAFT_MISSING', $state['state_issue']);
        $this->assertFalse($state['reviewable']);
    }

    public function test_approved_unapplied_and_applied_drafts_have_distinct_actions(): void
    {
        [$product, $draft] = $this->makeState('REVIEW_REQUIRED', 'needs_review', 'APPROVED_FOR_APPLY');
        $resolver = app(AiProductContentStateResolver::class);

        $this->assertSame('APPROVED', $resolver->resolve($product)['status']);
        $this->assertNotNull($resolver->approvedUnappliedDraft($product));
        $this->assertNull($resolver->reviewableDraft($product));

        $draft->update(['approval_status' => 'APPLIED', 'applied_at' => now()]);
        $product->unsetRelation('latestAiProductJobItem');

        $this->assertSame('APPLIED', $resolver->resolve($product)['status']);
        $this->assertNull($resolver->approvedUnappliedDraft($product));
    }

    public function test_product_edit_actions_match_the_canonical_actionable_state(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve']);
        $this->actingAs($user);

        $stale = Product::factory()->create(['ai_status' => 'needs_review']);
        Livewire::test(EditProduct::class, ['record' => $stale->getRouteKey()])
            ->assertActionHidden('ai_approve_latest_draft')
            ->assertActionHidden('ai_apply_latest_draft');

        [$reviewable] = $this->makeState('REVIEW_REQUIRED', 'needs_review');
        Livewire::test(EditProduct::class, ['record' => $reviewable->getRouteKey()])
            ->assertActionVisible('ai_preview_latest_draft')
            ->assertActionVisible('ai_approve_latest_draft')
            ->assertActionVisible('ai_regenerate_latest_draft')
            ->assertActionVisible('ai_discard_latest_draft')
            ->assertActionHidden('ai_apply_latest_draft');
    }

    public function test_discard_is_logical_and_keeps_draft_evidence(): void
    {
        $reviewer = $this->reviewer();
        [$product, $draft] = $this->makeState('REVIEW_REQUIRED', 'needs_review');

        app(\App\Services\Product\AIProductDraftApplyService::class)
            ->discard($draft, $reviewer->id, 'Không phù hợp định hướng biên tập', $reviewer);
        $product->unsetRelation('latestAiProductJobItem');
        $state = app(AiProductContentStateResolver::class)->resolve($product);

        $this->assertSame('DISCARDED', $state['status']);
        $this->assertFalse($state['reviewable']);
        $this->assertDatabaseHas('ai_product_drafts', [
            'id' => $draft->id,
            'status' => 'discarded',
            'approval_status' => 'DISCARDED',
            'discarded_by' => $reviewer->id,
        ]);
    }

    public function test_generate_preflight_does_not_create_blocked_job_spam_for_reviewable_draft(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['product.view', 'product.edit', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['product.view', 'product.edit', 'product.ai_generate']);
        $this->actingAs($user);
        [$product] = $this->makeState('REVIEW_REQUIRED', 'needs_review');
        $before = AiProductJob::count();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHidden('ai_product_generate')
            ->assertActionVisible('ai_regenerate_latest_draft');

        $this->assertSame($before, AiProductJob::count());
        $this->assertSame(0, AiProductJobItem::where('status', 'blocked')->count());
    }

    public function test_rejected_discarded_and_applied_drafts_do_not_block_future_generation(): void
    {
        $reviewer = $this->reviewer();
        foreach (['rejected', 'discarded', 'applied'] as $terminalState) {
            [$product, $draft, $item] = $this->makeState('REVIEW_REQUIRED', 'needs_review');
            $item->update(['status' => 'completed', 'canonical_status' => 'DONE']);

            if ($terminalState === 'rejected') {
                app(\App\Services\Product\AIProductDraftApplyService::class)
                    ->reject($draft, $reviewer->id, 'Không sử dụng', $reviewer);
            } elseif ($terminalState === 'discarded') {
                app(\App\Services\Product\AIProductDraftApplyService::class)
                    ->discard($draft, $reviewer->id, 'Lưu trữ logic', $reviewer);
            } else {
                $draft->update(['approval_status' => 'APPLIED', 'applied_at' => now()]);
            }

            $result = app(ProductContentEligibilityPolicy::class)
                ->evaluate($product->refresh(), ProductContentEligibilityPolicy::LONG_DESCRIPTION);
            $this->assertNotContains('ACTIVE_DRAFT_OR_APPLY_CONFLICT', $result['reasons'], $terminalState);
        }
    }

    /** @return array{Product,AiProductDraft,AiProductJobItem} */
    private function makeState(string $canonical, string $draftStatus, string $approval = 'REVIEW_REQUIRED'): array
    {
        $product = Product::factory()->create(['ai_status' => 'needs_review']);
        $job = $this->job();
        $draft = AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $product->id,
            'status' => $draftStatus,
            'approval_status' => $approval,
            'normalized_output_json' => ['content_html' => '<h2>Draft</h2>'],
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id,
            'product_id' => $product->id,
            'status' => 'needs_review',
            'canonical_status' => $canonical,
            'draft_id' => $draft->id,
            'generated_payload_json' => ['content_html' => '<h2>Draft</h2>'],
        ]);

        return [$product, $draft, $item];
    }

    private function job(): AiProductJob
    {
        return AiProductJob::create([
            'type' => 'single_product_preview',
            'scope' => 'selected',
            'status' => 'completed',
            'total' => 1,
            'config_json' => [],
        ]);
    }

    private function reviewer(): \App\Models\User
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'bulk_ai_approve', 'guard_name' => 'web']);
        $user->givePermissionTo('bulk_ai_approve');

        return $user;
    }
}
