<?php

namespace Tests\Feature;

use App\Models\AiProductDraft;
use App\Models\AiProductJob;
use App\Models\AiProductJobItem;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Services\Product\AIProductDraftApplyService;
use App\Services\AI\ProductAiApplyReadiness;
use App\Services\AI\SingleOperatorControlledRolloutPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class Phase2CControlledDraftApplyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $payload = [], array $product = []): array
    {
        $reviewer = User::factory()->create();
        Permission::firstOrCreate(['name' => 'bulk_ai_rollback', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'bulk_ai_approve', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'bulk_ai_apply', 'guard_name' => 'web']);
        $reviewer->givePermissionTo('bulk_ai_rollback');
        $reviewer->givePermissionTo('bulk_ai_approve');
        // This fixture actor performs the isolated apply assertions; production SoD is tested separately.
        $reviewer->givePermissionTo('bulk_ai_apply');
        $product = Product::factory()->create(array_merge([
            'model_code' => 'GCC42S6I/GMC42S6I',
            'btu' => 42650,
            'marketing_capacity_btu' => 42000,
            'technical_capacity_btu' => 42650,
            'technical_capacity_status' => 'verified',
            'short_description' => 'Cũ excerpt',
            'long_description' => 'Cũ content',
            'seo_title' => 'Cũ SEO',
            'seo_description' => 'Cũ meta',
            'og_title' => 'Cũ OG',
            'og_description' => 'Cũ OG description',
            'merchant_title' => 'Cũ merchant',
            'merchant_description' => 'Cũ merchant description',
        ], $product));
        $job = AiProductJob::create(['type' => 'product_content', 'scope' => 'selected', 'status' => 'needs_review', 'created_by' => $reviewer->id]);
        $item = AiProductJobItem::create(['ai_product_job_id' => $job->id, 'product_id' => $product->id, 'status' => 'needs_review', 'generated_payload_json' => $payload]);
        $draft = AiProductDraft::create([
            'job_id' => $job->id,
            'product_id' => $product->id,
            'module' => 'ai_product',
            'normalized_output_json' => $payload,
            'raw_output_json' => $payload,
            'field_status_json' => ['content_html' => 'passed'],
            'warnings_json' => [],
            'validation_errors_json' => [],
            'used_verified_facts_json' => ['technical_capacity_btu' => 42650],
            'token_usage_json' => ['technical_context_hash' => app(\App\Services\Product\AIProductContentSystem::class)->technicalContextHash($product)],
            'status' => 'needs_review',
        ]);
        $item->update(['draft_id' => $draft->id]);

        return [$reviewer, $product->refresh(), $draft->refresh()];
    }

    private function contentPayload(array $extra = []): array
    {
        return array_merge([
            'content_html' => '<h2>Công suất kỹ thuật 42.650 BTU/h</h2><h3>Ứng dụng</h3><p>Nội dung đã được kiểm tra.</p>',
            'blocked_claims' => [],
            'fact_check' => ['blocked_claims' => [], 'status' => 'passed'],
        ], $extra);
    }

    public function test_unapproved_draft_is_blocked_and_explicit_approval_is_required(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload());
        $service = app(AIProductDraftApplyService::class);
        $this->expectExceptionMessage('DRAFT_NOT_APPROVED');
        $service->apply($draft, $reviewer->id);
    }

    public function test_single_operator_apply_requires_and_accepts_exact_confirmation(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload());
        $reviewer->forceFill(['is_active' => true])->save();
        $this->enableSingleOperatorPolicy($reviewer->id);
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft, $reviewer->id, $reviewer);

        try {
            $service->apply($draft->refresh(), $reviewer->id);
            $this->fail('Apply without explicit confirmation must be blocked.');
        } catch (RuntimeException $e) {
            $this->assertSame('APPLY_CONFIRMATION_REQUIRED', $e->getMessage());
        }

        $confirmation = 'APPLY '.$product->model_code.'#'.$product->id;
        $result = $service->apply($draft->refresh(), $reviewer->id, false, $confirmation);

        $this->assertSame('APPLIED', $result['result']);
        $this->assertSame($product->id, $draft->refresh()->product_id);
        $this->assertNotNull($draft->applied_at);
    }

    public function test_apply_readiness_blocks_unverified_technical_claim_that_remains_in_content(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload([
            'content_html' => '<h2>Vận hành êm</h2><p>Độ ồn dàn lạnh là 19 dB.</p>',
        ]));
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft, $reviewer->id, $reviewer);
        $draft->update(['warnings_json' => ['unverified_technical_claim:19 dB']]);

        $readiness = app(ProductAiApplyReadiness::class)->resolve($draft->refresh());

        $this->assertFalse($readiness['can_apply']);
        $this->assertSame(1, $readiness['warning_counts']['hard']);
        $this->expectExceptionMessage('FACT_CHECK_BLOCKED');
        $service->apply($draft->refresh(), $reviewer->id);
    }

    public function test_removed_unverified_claim_is_audited_but_does_not_hard_block_apply(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload([
            'content_html' => '<h2>Vận hành ổn định</h2><p>Vui lòng tham khảo bảng thông số đã xác minh.</p>',
        ]));
        $draft->update(['warnings_json' => ['unverified_technical_claim:19 dB']]);
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft->refresh(), $reviewer->id, $reviewer, '[WARNING_OVERRIDE] claim removed', null, true);

        $readiness = app(ProductAiApplyReadiness::class)->resolve($draft->refresh());

        $this->assertTrue($readiness['can_apply']);
        $this->assertSame(0, $readiness['warning_counts']['hard']);
        $this->assertSame(1, $readiness['warning_counts']['technical_processed']);
    }

    public function test_corrected_persisted_version_is_eligible_but_not_approved(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload());
        $eligibility = app(AIProductDraftApplyService::class)->eligibility($draft);
        $this->assertTrue($eligibility['eligible_for_approval']);
        $this->assertFalse($eligibility['approved']);
        $this->assertSame('needs_review', $draft->status);
    }

    public function test_soft_warning_can_be_explicitly_approved_and_applied_with_audit_note(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload());
        $draft->update(['warnings_json' => ['content_too_short:390/800', 'missing_h2_h3']]);
        $service = app(AIProductDraftApplyService::class);

        $service->approve(
            $draft->refresh(),
            $reviewer->id,
            $reviewer,
            '[WARNING_OVERRIDE] Approved with warnings: content_too_short:390/800, missing_h2_h3',
            null,
            true,
        );
        $result = $service->apply($draft->refresh(), $reviewer->id);

        $this->assertSame('APPLIED', $result['result']);
        $this->assertStringContainsString('[WARNING_OVERRIDE]', (string) $draft->refresh()->review_note);
        $this->assertSame(['content_too_short:390/800', 'missing_h2_h3'], $draft->warnings_json);
        $this->assertTrue($draft->warning_override);
        $this->assertSame(['content_too_short:390/800', 'missing_h2_h3'], $draft->warnings_at_approval);
        $this->assertSame($product->id, $draft->product_id);
    }

    public function test_persisted_blocked_version_cannot_become_eligible(): void
    {
        [, , $draft] = $this->fixture($this->contentPayload(['blocked_claims' => ['CONTRADICTED'], 'fact_check' => ['blocked_claims' => ['CONTRADICTED']]]));
        $eligibility = app(AIProductDraftApplyService::class)->eligibility($draft);
        $this->assertFalse($eligibility['eligible_for_approval']);
        $this->assertNotEmpty($eligibility['reasons']);
    }

    public function test_content_patch_applies_without_overwriting_successful_fields_and_rolls_back_exactly(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload());
        $service = app(AIProductDraftApplyService::class);
        $before = $service->contentHash($product);
        $updatedAtBefore = $product->updated_at?->format('Y-m-d H:i:s.u');
        $technicalBefore = [$product->btu, $product->marketing_capacity_btu, $product->technical_capacity_btu, $product->specs_json];
        $service->approve($draft, $reviewer->id, $reviewer, 'reviewed technical claims');
        $result = $service->apply($draft->refresh(), $reviewer->id);
        $applied = Product::find($product->id);

        $this->assertSame('APPLIED', $result['result']);
        $this->assertSame('Cũ SEO', $applied->seo_title);
        $this->assertSame('Cũ merchant', $applied->merchant_title);
        $this->assertSame($technicalBefore, [$applied->btu, $applied->marketing_capacity_btu, $applied->technical_capacity_btu, $applied->specs_json]);
        $this->assertSame($updatedAtBefore, $applied->updated_at?->format('Y-m-d H:i:s.u'));
        $this->assertNotSame($before, $service->contentHash($applied));
        $this->assertSame('applied', $applied->ai_status);
        $this->assertSame('APPLIED', $draft->refresh()->field_status_json['content_html']);
        $this->assertSame('completed', $draft->job->items()->where('draft_id', $draft->id)->value('status'));
        $this->assertSame('DONE', $draft->job->items()->where('draft_id', $draft->id)->value('canonical_status'));
        $this->assertSame('completed', $draft->job->refresh()->status);
        $this->assertSame('NOOP_ALREADY_APPLIED', $service->apply($draft->refresh(), $reviewer->id)['result']);

        $this->assertTrue($service->rollback($draft->refresh(), $reviewer));
        $this->assertSame($before, $service->contentHash($applied->refresh()));
        $this->assertSame($technicalBefore, [$applied->btu, $applied->marketing_capacity_btu, $applied->technical_capacity_btu, $applied->specs_json]);
    }

    public function test_approved_faq_patch_is_applied_as_content_layer_only(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload([
            'faq' => [
                ['question' => 'Sản phẩm phù hợp không gian nào?', 'answer' => 'Phù hợp không gian thương mại theo nhu cầu sử dụng.'],
                ['question' => 'Model là gì?', 'answer' => 'Model được giữ đúng theo context Product đã cung cấp.'],
                ['question' => 'Có cần tư vấn thêm không?', 'answer' => 'Có thể tham khảo thêm trước khi lựa chọn.'],
            ],
        ]));
        $this->assertArrayHasKey('faq', $draft->normalized_output_json);
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft, $reviewer->id, $reviewer, 'isolated FAQ field review', ['faq']);
        $result = $service->apply($draft->refresh(), $reviewer->id);

        $this->assertSame('APPLIED', $result['result']);
        $this->assertCount(3, $product->faqs()->get());
        $this->assertSame('GCC42S6I/GMC42S6I', $product->refresh()->model_code);
    }

    public function test_tamper_stale_forbidden_rejected_and_transaction_is_atomic(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload());
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft, $reviewer->id, $reviewer);
        $draft->update(['normalized_output_json' => $this->contentPayload(['content_html' => '<p>Tampered</p>'])]);
        try { $service->apply($draft->refresh(), $reviewer->id); $this->fail('tamper must block'); } catch (RuntimeException $e) { $this->assertSame('APPROVED_PAYLOAD_HASH_MISMATCH', $e->getMessage()); }

        $draft->update(['normalized_output_json' => $this->contentPayload()]);
        $product->update(['technical_capacity_btu' => 42651]);
        try { $service->apply($draft->refresh(), $reviewer->id); $this->fail('stale must block'); } catch (RuntimeException $e) { $this->assertSame('STALE_TECHNICAL_CONTEXT', $e->getMessage()); }

        $product->update(['technical_capacity_btu' => 42650]);
        $forbidden = $this->fixture($this->contentPayload(['specs_json' => ['x' => 1]]));
        $forbiddenDraft = $forbidden[2];
        try { $service->approve($forbiddenDraft, $forbidden[0]->id, $forbidden[0]); $this->fail('forbidden must block'); } catch (RuntimeException $e) { $this->assertStringContainsString('FORBIDDEN_PRODUCT_FIELD', $e->getMessage()); }

        $fresh = $this->fixture($this->contentPayload());
        $service->approve($fresh[2], $fresh[0]->id, $fresh[0]);
        $hash = $service->contentHash($fresh[1]);
        try { $service->apply($fresh[2], $fresh[0]->id, true); $this->fail('injected failure must throw'); } catch (RuntimeException $e) { $this->assertSame('CONTROLLED_APPLY_FAILURE', $e->getMessage()); }
        $this->assertSame($hash, $service->contentHash($fresh[1]->refresh()));
        $this->assertNull($fresh[2]->refresh()->applied_at);
    }

    public function test_apply_blocks_when_product_content_changed_after_approval(): void
    {
        [$reviewer, $product, $draft] = $this->fixture($this->contentPayload());
        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft, $reviewer->id, $reviewer);

        $product->forceFill(['long_description' => 'Biên tập thủ công sau khi duyệt'])->save();

        $this->expectExceptionMessage('STALE_PRODUCT_CONTENT');
        $service->apply($draft->refresh(), $reviewer->id);
    }

    public function test_warning_requires_explicit_override(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload());
        $draft->update(['warnings_json' => ['content_too_short:459/800']]);

        $this->expectExceptionMessage('WARNING_OVERRIDE_CONFIRMATION_REQUIRED');
        app(AIProductDraftApplyService::class)->approve($draft->refresh(), $reviewer->id, $reviewer);
    }

    public function test_three_selected_drafts_apply_individually_and_restore_exactly(): void
    {
        $service = app(AIProductDraftApplyService::class);
        $models = ['GCC42S6I/GMC42S6I', 'GDC24', 'GUD50'];
        $capacities = [[42000, 42650], [24000, 24200], [18000, 16400]];
        foreach ($models as $index => $model) {
            [$reviewer, $product, $draft] = $this->fixture($this->contentPayload([
                'content_html' => '<h2>Công suất kỹ thuật '.$capacities[$index][1].' BTU/h</h2><h3>Thông tin</h3><p>Draft '.$model.'</p>',
            ]), [
                'model_code' => $model,
                'marketing_capacity_btu' => $capacities[$index][0],
                'technical_capacity_btu' => $capacities[$index][1],
            ]);
            $before = $service->contentHash($product);
            $service->approve($draft, $reviewer->id, $reviewer, 'isolated clone review');
            $result = $service->apply($draft->refresh(), $reviewer->id);
            $this->assertSame('APPLIED', $result['result']);
            $this->assertStringContainsString((string) $capacities[$index][1], (string) $product->refresh()->long_description);
            $this->assertTrue($service->rollback($draft->refresh(), $reviewer));
            $this->assertSame($before, $service->contentHash($product->refresh()));
        }
    }

    public function test_exact_read_only_selected_1239_payload_is_blocked_if_evidence_is_not_eligible(): void
    {
        $evidence = storage_path('app/private/reports/phase2c_selected_drafts.json');
        if (! is_file($evidence)) $this->markTestSkipped('read-only selection evidence not generated');
        $selected = json_decode((string) file_get_contents($evidence), true)['selected'] ?? [];
        $selected = collect($selected)->firstWhere('product_id', 1239);
        $this->assertIsArray($selected);
        $this->assertIsArray($selected['normalized_payload'] ?? null);
        [$reviewer, $product, $draft] = $this->fixture($selected['normalized_payload'], ['model_code' => 'GCC42S6I/GMC42S6I', 'marketing_capacity_btu' => 42000, 'technical_capacity_btu' => 42650]);
        $service = app(AIProductDraftApplyService::class);
        $service = app(AIProductDraftApplyService::class);
        $this->expectExceptionMessage('FACT_CHECK_BLOCKED');
        $service->approve($draft, $reviewer->id, $reviewer, 'exact evidence eligibility gate');
    }

    public function test_atomic_batch_failure_rolls_back_all_isolated_items(): void
    {
        $service = app(AIProductDraftApplyService::class);
        $first = $this->fixture($this->contentPayload(['content_html' => '<h2>First 24200 BTU/h</h2><h3>Batch</h3><p>Safe.</p>']));
        $second = $this->fixture($this->contentPayload(['content_html' => '<h2>Second 16400 BTU/h</h2><h3>Batch</h3><p>Safe.</p>']));
        $service->approve($first[2], $first[0]->id, $first[0]);
        $service->approve($second[2], $second[0]->id, $second[0]);
        $beforeFirst = $service->contentHash($first[1]);
        $beforeSecond = $service->contentHash($second[1]);
        try {
            $service->applyBatch([$first[2], $second[2]], $first[0]->id, true);
            $this->fail('controlled batch failure must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('CONTROLLED_BATCH_FAILURE', $e->getMessage());
        }
        $this->assertSame($beforeFirst, $service->contentHash($first[1]->refresh()));
        $this->assertSame($beforeSecond, $service->contentHash($second[1]->refresh()));
        $this->assertNull($first[2]->refresh()->applied_at);
        $this->assertNull($second[2]->refresh()->applied_at);
    }

    public function test_optional_catalog_gaps_do_not_require_editorial_warning_override(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload());
        $draft->update(['warnings_json' => [
            'missing_refrigerant',
            'missing_recommended_area',
            'missing_warranty_policy',
            'missing_price',
        ]]);

        $service = app(AIProductDraftApplyService::class);
        $service->approve($draft->refresh(), $reviewer->id, $reviewer);
        $readiness = app(ProductAiApplyReadiness::class)->resolve($draft->refresh());

        $this->assertTrue($readiness['can_apply']);
        $this->assertFalse($readiness['requires_warning_override']);
        $this->assertSame(0, $readiness['warning_counts']['soft']);
        $this->assertSame(4, $readiness['warning_counts']['optional']);
        $this->assertCount(4, $readiness['optional_data']);
    }

    public function test_positive_validation_evidence_is_not_an_editorial_warning(): void
    {
        [$reviewer, , $draft] = $this->fixture($this->contentPayload());
        $draft->update(['warnings_json' => ['encoding_checked', 'vietnamese_verified']]);

        app(AIProductDraftApplyService::class)->approve($draft->refresh(), $reviewer->id, $reviewer);
        $readiness = app(ProductAiApplyReadiness::class)->resolve($draft->refresh());

        $this->assertSame(0, $readiness['warning_counts']['soft']);
        $this->assertSame(0, $readiness['warning_counts']['hard']);
        $this->assertSame(2, $readiness['warning_counts']['informational']);
        $this->assertFalse($readiness['requires_warning_override']);
    }

    private function enableSingleOperatorPolicy(int $operatorId): void
    {
        config()->set('ai.single_operator', [
            'enabled' => true,
            'operator_user_id' => $operatorId,
            'policy' => SingleOperatorControlledRolloutPolicy::NAME,
            'super_admin_exception' => true,
            'enforce_in_testing' => true,
            'auto_approve' => false,
            'auto_apply' => false,
        ]);
    }
}
