<?php

namespace Tests\Unit;

use App\Services\AI\SingleOperatorControlledRolloutPolicy;
use RuntimeException;
use Tests\TestCase;

class Phase2I2SingleOperatorPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.single_operator', [
            'enabled' => true,
            'operator_user_id' => 1,
            'policy' => SingleOperatorControlledRolloutPolicy::NAME,
            'super_admin_exception' => true,
            'enforce_in_testing' => true,
            'auto_approve' => false,
            'auto_apply' => false,
            'max_stage1_targets' => 1,
            'max_stage2_targets' => 2,
            'max_stage3_targets' => 5,
        ]);
    }

    public function test_transitions_are_explicit_and_not_combinable(): void
    {
        $policy = app(SingleOperatorControlledRolloutPolicy::class);
        $policy->assertTransition('DRAFT_GENERATED', 'REVIEW_COMPLETED');
        $policy->assertTransition('REVIEW_COMPLETED', 'APPROVED');
        $policy->assertTransition('APPROVED', 'APPLY_AUTHORIZED');
        $policy->assertTransition('APPLY_AUTHORIZED', 'APPLIED');

        $this->expectException(RuntimeException::class);
        $policy->assertTransition('DRAFT_GENERATED', 'APPLIED');
    }

    public function test_auto_approval_and_auto_apply_are_rejected(): void
    {
        $policy = app(SingleOperatorControlledRolloutPolicy::class);

        $this->expectException(RuntimeException::class);
        $policy->assertDraftOnly(['apply_mode' => 'auto_apply']);
    }

    public function test_apply_requires_typed_confirmation(): void
    {
        $policy = app(SingleOperatorControlledRolloutPolicy::class);

        $this->expectException(RuntimeException::class);
        $policy->assertExplicitApplyConfirmation('yes', 'GDC36S6I/GMC36S6I#1241');
    }

    public function test_snapshot_preserves_small_stage_limits_and_exception_marker(): void
    {
        $snapshot = app(SingleOperatorControlledRolloutPolicy::class)->snapshot();

        $this->assertTrue($snapshot['enabled']);
        $this->assertSame(1, $snapshot['operator_user_id']);
        $this->assertTrue($snapshot['super_admin_exception']);
        $this->assertSame([1 => 1, 2 => 2, 3 => 5], $snapshot['stage_limits']);
        $this->assertFalse($snapshot['auto_approve']);
        $this->assertFalse($snapshot['auto_apply']);
    }
}
