<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\AIProductContentSystem;
use App\Services\Product\AIProductDraftApplyService;
use App\Services\AI\AiContentStatusPresenter;
use App\Services\AI\SingleOperatorControlledRolloutPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AIProductActionRbacCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve', 'bulk_ai_apply'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_browser_action_visibility_matches_each_rbac_actor(): void
    {
        [$product] = $this->draftFixture();
        $matrix = [
            [['product.view', 'product.edit', 'product.ai_generate'], ['ai_regenerate_latest_draft'], ['ai_product_generate', 'ai_approve_latest_draft', 'ai_reject_latest_draft', 'ai_discard_latest_draft', 'ai_apply_latest_draft']],
            [['product.view', 'product.edit', 'bulk_ai_approve'], ['ai_preview_latest_draft', 'ai_approve_latest_draft', 'ai_reject_latest_draft', 'ai_discard_latest_draft'], ['ai_product_generate', 'ai_regenerate_latest_draft', 'ai_apply_latest_draft']],
            [['product.view', 'product.edit'], [], ['ai_product_generate', 'ai_approve_latest_draft', 'ai_reject_latest_draft', 'ai_discard_latest_draft', 'ai_regenerate_latest_draft', 'ai_apply_latest_draft']],
        ];

        foreach ($matrix as [$permissions, $visible, $hidden]) {
            $user = $this->actor($permissions);
            $this->actingAs($user);
            $component = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
            foreach ($visible as $action) $component->assertActionVisible($action);
            foreach ($hidden as $action) $component->assertActionHidden($action);
        }
    }

    public function test_service_boundaries_reject_unauthorized_approve_reject_discard_regenerate_and_apply(): void
    {
        $service = app(AIProductDraftApplyService::class);
        $none = $this->actor(['product.view', 'product.edit']);

        foreach (['approve', 'reject', 'discard', 'regenerate'] as $operation) {
            [, $draft] = $this->draftFixture();
            try {
                match ($operation) {
                    'approve' => $service->approve($draft, $none->id, $none),
                    'reject' => $service->reject($draft, $none->id, 'Không được phép', $none),
                    'discard' => $service->discard($draft, $none->id, 'Không được phép', $none),
                    'regenerate' => $service->supersedeForRegeneration($draft, $none),
                };
                $this->fail("{$operation} must be forbidden");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('FORBIDDEN', $e->getMessage());
            }
        }

        [$product, $draft] = $this->draftFixture();
        $approver = $this->actor(['bulk_ai_approve']);
        $service->approve($draft, $approver->id, $approver);
        $before = $service->contentHash($product);
        try {
            $service->apply($draft->refresh(), $none->id);
            $this->fail('apply must be forbidden');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('FORBIDDEN', $e->getMessage());
        }
        $this->assertSame($before, $service->contentHash($product->refresh()));
    }

    public function test_authenticated_actor_identity_is_persisted_for_all_review_transitions(): void
    {
        $reviewer = $this->actor(['bulk_ai_approve']);
        $service = app(AIProductDraftApplyService::class);

        [, $approved] = $this->draftFixture(['content_too_short:459/800']);
        $service->approve($approved, $reviewer->id, $reviewer, '[WARNING_OVERRIDE] Browser certification', null, true);
        $this->assertSame($reviewer->id, $approved->refresh()->approved_by);
        $this->assertTrue($approved->warning_override);

        [, $rejected] = $this->draftFixture();
        $service->reject($rejected, $reviewer->id, 'Từ chối có lý do', $reviewer);
        $this->assertSame($reviewer->id, $rejected->refresh()->rejected_by);

        [, $discarded] = $this->draftFixture();
        $service->discard($discarded, $reviewer->id, 'Loại bỏ có lý do', $reviewer);
        $this->assertSame($reviewer->id, $discarded->refresh()->discarded_by);
    }

    public function test_unauthorized_livewire_generate_creates_no_job_and_dispatches_nothing(): void
    {
        Bus::fake();
        $user = $this->actor(['product.view', 'product.edit']);
        $product = Product::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHidden('ai_product_generate');
        try {
            $component->callAction('ai_product_generate', [
                'outputs' => ['content'], 'mode' => 'missing_only', 'depth' => 'seo',
                'tone' => 'hvac_expert', 'apply_mode' => 'needs_review',
            ]);
            $this->fail('Hidden unauthorized action must not be callable');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->assertStringContainsString('action with name [ai_product_generate]', $e->getMessage());
        }

        $this->assertSame(0, AiProductJob::count());
        Bus::assertNothingDispatched();
    }

    public function test_enforced_single_operator_rollout_hides_single_product_mutations_for_other_authorized_users(): void
    {
        [$product] = $this->draftFixture();
        $operator = $this->actor(['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve', 'bulk_ai_apply']);
        $nonOperator = $this->actor(['product.view', 'product.edit', 'product.ai_generate', 'bulk_ai_approve', 'bulk_ai_apply']);
        config()->set('ai.single_operator', [
            'enabled' => true,
            'operator_user_id' => $operator->id,
            'policy' => SingleOperatorControlledRolloutPolicy::NAME,
            'super_admin_exception' => true,
            'enforce_in_testing' => true,
            'auto_approve' => false,
            'auto_apply' => false,
        ]);

        $this->actingAs($nonOperator);
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionVisible('ai_preview_latest_draft')
            ->assertActionHidden('ai_approve_latest_draft')
            ->assertActionHidden('ai_regenerate_latest_draft')
            ->assertActionHidden('ai_reject_latest_draft')
            ->assertActionHidden('ai_discard_latest_draft');
    }

    public function test_filament_apply_action_passes_explicit_confirmation_to_domain_service(): void
    {
        [$product, $draft] = $this->draftFixture();
        $operator = $this->actor(['product.view', 'product.edit', 'bulk_ai_approve', 'bulk_ai_apply']);
        app(AIProductDraftApplyService::class)->approve($draft, $operator->id, $operator);
        config()->set('ai.single_operator', [
            'enabled' => true,
            'operator_user_id' => $operator->id,
            'policy' => SingleOperatorControlledRolloutPolicy::NAME,
            'super_admin_exception' => true,
            'enforce_in_testing' => true,
            'auto_approve' => false,
            'auto_apply' => false,
        ]);
        $this->actingAs($operator);
        $confirmation = 'APPLY '.($product->model_code ?: 'UNKNOWN').'#'.$product->id;

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionVisible('ai_apply_latest_draft')
            ->callAction('ai_apply_latest_draft', ['apply_confirmation' => $confirmation])
            ->assertHasNoActionErrors();

        $this->assertSame('APPLIED', $draft->refresh()->approval_status);
        $this->assertSame($operator->id, $draft->applied_by);
    }

    public function test_panel_presenter_maps_review_terminal_and_field_states_truthfully(): void
    {
        $presenter = app(AiContentStatusPresenter::class);

        $this->assertSame('Đã loại bỏ', $presenter->present('DISCARDED')['label']);
        $this->assertSame('Đã tạo', $presenter->present('GENERATED')['label']);
        $this->assertSame('Không yêu cầu', $presenter->present('NOT_REQUESTED')['label']);
        $this->assertSame('Lỗi đọc phản hồi', $presenter->present('PARSE_FAILED')['label']);
        $this->assertNotSame('Chưa xác định', $presenter->present('VALIDATION_FAILED')['label']);
    }

    private function actor(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        if ($permissions !== []) $user->givePermissionTo($permissions);
        return $user;
    }

    /** @return array{Product,AiProductDraft,AiProductJobItem} */
    private function draftFixture(array $warnings = []): array
    {
        $product = Product::factory()->create([
            'short_description' => 'Nội dung cũ',
            'long_description' => '<p>Nội dung cũ.</p>',
        ]);
        $payload = [
            'content_html' => '<h2>Nội dung AI</h2><h3>Chi tiết</h3><p>Fixture.</p>',
            'blocked_claims' => [],
            'fact_check' => ['status' => 'passed', 'blocked_claims' => []],
        ];
        $job = AiProductJob::create(['type' => 'single_product_preview', 'scope' => 'selected', 'status' => 'completed', 'total' => 1, 'config_json' => []]);
        $draft = AiProductDraft::create([
            'job_id' => $job->id, 'product_id' => $product->id, 'status' => 'needs_review',
            'approval_status' => 'REVIEW_REQUIRED', 'normalized_output_json' => $payload,
            'raw_output_json' => $payload, 'warnings_json' => $warnings,
            'token_usage_json' => ['technical_context_hash' => app(AIProductContentSystem::class)->technicalContextHash($product)],
        ]);
        $item = AiProductJobItem::create([
            'ai_product_job_id' => $job->id, 'product_id' => $product->id,
            'status' => 'needs_review', 'canonical_status' => 'REVIEW_REQUIRED',
            'draft_id' => $draft->id, 'generated_payload_json' => $payload,
        ]);
        return [$product->refresh(), $draft->refresh(), $item];
    }
}
