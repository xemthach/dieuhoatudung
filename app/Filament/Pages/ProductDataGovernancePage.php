<?php

namespace App\Filament\Pages;

use App\Models\ProductDataGovernanceEvent;
use App\Services\Product\ProductDataApprovalCenterService;
use App\Services\Product\ProductDataApprovalService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductDataGovernancePage extends Page
{
    protected string $view = 'filament.pages.product-data-governance';
    protected static ?string $slug = 'product-data-governance';
    protected static ?int $navigationSort = 3;

    public string $taxonomyReason = '';
    public array $mappingTiers = [];
    public array $mappingReasons = [];

    public static function getNavigationIcon(): ?string { return 'heroicon-o-check-badge'; }
    public static function getNavigationLabel(): string { return 'Product Data Governance'; }
    public static function getNavigationGroup(): ?string { return 'Hệ thống'; }
    public function getTitle(): string { return 'Product Taxonomy & Marketing Capacity Review'; }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isSuperAdmin()
            || $user->can('product_taxonomy.view')
            || $user->can('marketing_capacity.view')));
    }

    public function taxonomy(): array
    {
        return app(ProductDataApprovalCenterService::class)->taxonomyReview();
    }

    public function marketing(): array
    {
        return app(ProductDataApprovalCenterService::class)->marketingReview();
    }

    public function recentEvents()
    {
        return ProductDataGovernanceEvent::query()->with('operator')->latest('id')->limit(30)->get();
    }

    public function approveTaxonomy(): void
    {
        $review = $this->taxonomy();
        app(ProductDataApprovalService::class)->approveTaxonomy(
            $review['ledger_digest'], $review['rows'], $this->taxonomyReason, auth()->user(),
        );
        $this->taxonomyReason = '';
        Notification::make()->success()->title('Taxonomy ledger approved')->send();
    }

    public function approveMarketingGroup(string $groupKey): void
    {
        $review = $this->marketing();
        $group = collect($review['groups'])->sole('group_key', $groupKey);
        app(ProductDataApprovalService::class)->approveMarketingGroup(
            $group,
            (int) ($this->mappingTiers[$groupKey] ?? 0),
            (string) ($this->mappingReasons[$groupKey] ?? ''),
            auth()->user(),
        );
        unset($this->mappingTiers[$groupKey], $this->mappingReasons[$groupKey]);
        Notification::make()->success()->title('Commercial tier mapping approved')->send();
    }

    public function markTaxonomyForReview(int $productId): void
    {
        $review = $this->taxonomy();
        $row = collect($review['rows'])->sole('product_id', $productId);
        app(ProductDataApprovalService::class)->recordReviewAction(
            ProductDataApprovalService::TAXONOMY,
            'MARK_REVIEW',
            $review['ledger_digest'],
            [$productId],
            'Requires explicit source review from Admin taxonomy queue.',
            auth()->user(),
            ['row' => $row],
        );
        Notification::make()->success()->title('Product marked for source review')->send();
    }

    public function rejectTaxonomyProposal(int $productId): void
    {
        $review = $this->taxonomy();
        $row = collect($review['rows'])->sole('product_id', $productId);
        app(ProductDataApprovalService::class)->recordReviewAction(
            ProductDataApprovalService::TAXONOMY,
            'REJECT',
            $review['ledger_digest'],
            [$productId],
            $this->taxonomyReason,
            auth()->user(),
            ['row' => $row],
        );
        $this->taxonomyReason = '';
        Notification::make()->success()->title('Taxonomy proposal rejected')->send();
    }

    public function rejectMarketingGroup(string $groupKey): void
    {
        $group = collect($this->marketing()['groups'])->sole('group_key', $groupKey);
        app(ProductDataApprovalService::class)->recordReviewAction(
            ProductDataApprovalService::MARKETING_CAPACITY,
            'REJECT',
            $group['ledger_digest'],
            $group['product_ids'],
            (string) ($this->mappingReasons[$groupKey] ?? ''),
            auth()->user(),
            ['group_key' => $groupKey, 'source_digest' => $group['source_digest']],
        );
        unset($this->mappingReasons[$groupKey]);
        Notification::make()->success()->title('Commercial mapping group rejected')->send();
    }

    public function markMarketingGroupForReview(string $groupKey): void
    {
        $group = collect($this->marketing()['groups'])->sole('group_key', $groupKey);
        app(ProductDataApprovalService::class)->recordReviewAction(
            ProductDataApprovalService::MARKETING_CAPACITY,
            'MARK_REVIEW',
            $group['ledger_digest'],
            $group['product_ids'],
            (string) ($this->mappingReasons[$groupKey] ?? ''),
            auth()->user(),
            ['group_key' => $groupKey, 'source_digest' => $group['source_digest']],
        );
        unset($this->mappingReasons[$groupKey]);
        Notification::make()->success()->title('Commercial mapping group marked for source review')->send();
    }

    public function exportTaxonomyLedger(): StreamedResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin() || auth()->user()?->can('product_taxonomy.view'), 403);
        $review = $this->taxonomy();
        $filename = 'product-taxonomy-review-'.$review['ledger_digest'].'.json';

        return response()->streamDownload(
            fn () => print json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
