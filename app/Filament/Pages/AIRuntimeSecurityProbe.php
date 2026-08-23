<?php

namespace App\Filament\Pages;

use App\Models\AiProductDraft;
use App\Services\AI\BulkRuntimeAuthorizationService;
use App\Services\Product\AIProductDraftApplyService;
use Filament\Pages\Page;
use Throwable;

class AIRuntimeSecurityProbe extends Page
{
    protected string $view = 'filament.pages.ai-runtime-security-probe';

    protected static ?string $slug = 'ai-runtime-security-probe';

    public array $results = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('bulk_ai_view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function probeApprove(): void
    {
        $this->runProbe('approve', function (): void {
            $draft = new AiProductDraft(['status' => 'processing', 'normalized_output_json' => []]);
            app(AIProductDraftApplyService::class)->approve($draft, (int) auth()->id(), auth()->user());
        });
    }

    public function probeApply(): void
    {
        $this->runProbe('apply', function (): void {
            $draft = new AiProductDraft(['status' => 'needs_review']);
            app(AIProductDraftApplyService::class)->apply($draft, (int) auth()->id());
        });
    }

    public function probeRollback(): void
    {
        $this->runProbe('rollback', function (): void {
            $draft = new AiProductDraft(['product_id' => 0]);
            app(AIProductDraftApplyService::class)->rollback($draft, auth()->user());
        });
    }

    public function probeApplyWithoutConfirmation(): void
    {
        $this->runProbe('apply_without_confirmation', function (): void {
            $draft = new AiProductDraft(['product_id' => 1241, 'status' => 'needs_review', 'normalized_output_json' => []]);
            $draft->forceFill(['approval_status' => 'APPROVED_FOR_APPLY', 'approved_payload_hash' => hash('sha256', json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))]);
            app(AIProductDraftApplyService::class)->apply($draft, (int) auth()->id());
        });
    }

    public function probeStaleContext(): void
    {
        $this->runProbe('stale_context', function (): void {
            $draft = new AiProductDraft(['product_id' => 1241, 'status' => 'needs_review', 'normalized_output_json' => []]);
            $draft->forceFill([
                'approval_status' => 'APPROVED_FOR_APPLY',
                'approved_payload_hash' => hash('sha256', json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'approved_technical_context_hash' => 'stale-context-proof',
            ]);
            app(AIProductDraftApplyService::class)->apply($draft, (int) auth()->id(), false, 'APPLY GDC36S6I/GMC36S6I#1241');
        });
    }

    private function runProbe(string $action, callable $callback): void
    {
        try {
            $callback();
            $this->results[$action] = ['result' => 'ALLOWED_UNEXPECTEDLY'];
        } catch (Throwable $e) {
            $this->results[$action] = [
                'result' => str_ends_with($e->getMessage(), '_FORBIDDEN') ? 'DENIED' : 'REJECTED_SAFE_FIXTURE',
                'code' => $e->getMessage(),
            ];
        }
    }
}
