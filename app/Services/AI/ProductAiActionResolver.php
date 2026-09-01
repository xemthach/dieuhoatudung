<?php

namespace App\Services\AI;

use App\Models\Product;

final class ProductAiActionResolver
{
    public function __construct(
        private readonly AiProductContentStateResolver $stateResolver,
        private readonly ProductAiApplyReadiness $applyReadiness,
    ) {}

    /** @return array<string,mixed> */
    public function resolve(Product $product): array
    {
        $resolved = $this->stateResolver->resolve($product);
        $state = (string) $resolved['status'];
        $draft = $resolved['draft'];
        $item = $resolved['item'];
        $active = in_array($state, ['QUEUED', 'PROCESSING', 'VALIDATING', 'RETRYING'], true);
        $review = $state === 'REVIEW_REQUIRED' && $resolved['reviewable'];
        $apply = $this->applyReadiness->resolve($draft);
        $approved = $state === 'APPROVED' && $resolved['approved_unapplied'] && $apply['can_apply'];
        $applyBlocked = $state === 'APPROVED' && $resolved['approved_unapplied'] && ! $apply['can_apply'];
        $applied = $state === 'APPLIED' && $resolved['applied'];
        $blocked = $state === 'BLOCKED' || $applyBlocked;
        $terminalGenerate = in_array($state, ['NOT_GENERATED', 'REJECTED', 'DISCARDED', 'FAILED', 'CANCELLED'], true);
        $warnings = array_values(array_filter(array_map('strval', (array) ($draft?->warnings_json ?? []))));

        $direct = match (true) {
            $terminalGenerate => ['generate'],
            $active => ['processing_status'],
            $review => ['preview', 'approve'],
            $approved => ['preview', 'apply'],
            $applied => ['preview'],
            $blocked => ['block_reason'],
            default => [],
        };

        $menu = array_values(array_filter([
            $review ? 'regenerate' : null,
            $review ? 'reject' : null,
            $review ? 'discard' : null,
            $applied ? 'generate_new' : null,
            $item ? 'view_job' : null,
            $active && $item && app(AiProductLifecycleService::class)->isRecoverable($item) ? 'recover' : null,
        ]));

        return array_merge($resolved, [
            'current_state' => $applyBlocked ? 'HARD_BLOCKED' : $state,
            'apply_readiness' => $apply,
            'warnings' => $warnings,
            'approve_has_warning' => $warnings !== [],
            'can_generate_primary' => in_array('generate', $direct, true),
            'can_generate_more' => in_array('generate_new', $menu, true),
            'show_processing_status' => in_array('processing_status', $direct, true),
            'can_preview' => in_array('preview', $direct, true),
            'can_view_block_reason' => in_array('block_reason', $direct, true),
            'can_approve' => in_array('approve', $direct, true),
            'can_apply' => in_array('apply', $direct, true),
            'can_regenerate' => in_array('regenerate', $menu, true),
            'can_reject' => in_array('reject', $menu, true),
            'can_discard' => in_array('discard', $menu, true),
            'can_view_job' => in_array('view_job', $menu, true),
            'can_recover' => in_array('recover', $menu, true),
            'direct_actions' => $direct,
            'menu_actions' => $menu,
            'maximum_direct_actions' => count($direct),
        ]);
    }
}
