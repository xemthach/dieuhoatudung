<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Services\AI\AiProductContentStateResolver;
use App\Services\AI\AiProductLiveStatusService;
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
        foreach (['product.view', 'product.edit', 'product.ai_generate'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['product.view', 'product.edit', 'product.ai_generate']);
        $this->actingAs($user);

        $stale = Product::factory()->create(['ai_status' => 'needs_review']);
        Livewire::test(EditProduct::class, ['record' => $stale->getRouteKey()])
            ->assertActionHidden('ai_approve_latest_draft')
            ->assertActionHidden('ai_apply_latest_draft');

        [$reviewable] = $this->makeState('REVIEW_REQUIRED', 'needs_review');
        Livewire::test(EditProduct::class, ['record' => $reviewable->getRouteKey()])
            ->assertActionVisible('ai_approve_latest_draft')
            ->assertActionHidden('ai_apply_latest_draft');
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
}
