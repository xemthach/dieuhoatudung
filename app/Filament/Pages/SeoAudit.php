<?php

namespace App\Filament\Pages;

use App\Services\Seo\SeoAuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;

class SeoAudit extends Page
{
    protected string $view = 'filament.pages.seo-audit';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('seo_audit.view') ?? false;
    }

    protected static ?string $title = 'SEO Audit';

    protected static ?string $navigationLabel = 'SEO Audit';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::SevenExtraLarge;
    }

    public function getSubheading(): ?string
    {
        return 'Kiểm tra lỗi SEO của sản phẩm, bài viết, danh mục và nội dung.';
    }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-magnifying-glass'; }

    public static function getNavigationGroup(): ?string
    {
        return 'SEO';
    }

    protected static ?int $navigationSort = 10;

    // ─── State ───────────────────────────────────────────
    public string $filterEntity = 'all';
    public string $filterSeverity = 'all';
    public string $search = '';

    public Collection $groupedIssues;
    public int $totalCritical = 0;
    public int $totalWarning = 0;
    public int $totalNotice = 0;
    public int $totalIssues = 0;
    public int $seoScore = 100;
    public ?string $errorMessage = null;

    // ─── Lifecycle ───────────────────────────────────────
    public function mount(): void
    {
        $this->loadAudit();
    }

    protected function loadAudit(bool $fresh = false): void
    {
        try {
            $service = app(SeoAuditService::class);
            $all = collect($service->run($fresh));

            $filtered = $all->when(
                $this->filterEntity !== 'all',
                fn ($c) => $c->filter(fn ($i) => $i['entity'] === $this->filterEntity)
            )->when(
                $this->filterSeverity !== 'all',
                fn ($c) => $c->filter(fn ($i) => $i['severity'] === $this->filterSeverity)
            )->when(
                trim($this->search) !== '',
                fn ($c) => $c->filter(fn ($i) => stripos($i['name'], trim($this->search)) !== false)
            )->values();

            $this->totalCritical = $all->where('severity', 'critical')->count();
            $this->totalWarning = $all->where('severity', 'warning')->count();
            $this->totalNotice = $all->where('severity', 'notice')->count();
            $this->totalIssues = $all->count();

            // Calculate SEO Score
            $score = 100 - ($this->totalCritical * 5) - ($this->totalWarning * 3) - ($this->totalNotice * 1);
            $this->seoScore = max(0, min(100, $score));

            // Group issues by entity and name (edit_url)
            $this->groupedIssues = $filtered->groupBy('edit_url')->map(function ($items, $url) {
                $first = $items->first();
                $severities = $items->pluck('severity')->toArray();
                
                $maxSeverity = 'notice';
                if (in_array('critical', $severities)) {
                    $maxSeverity = 'critical';
                } elseif (in_array('warning', $severities)) {
                    $maxSeverity = 'warning';
                }

                return [
                    'entity' => $first['entity'],
                    'name' => $first['name'],
                    'edit_url' => $url,
                    'public_url' => $first['public_url'] ?? null,
                    'max_severity' => $maxSeverity,
                    'issues' => $items->toArray(),
                ];
            })->values();

        } catch (\Throwable $e) {
            $this->groupedIssues = collect();
            $this->seoScore = 0;
            $this->errorMessage = $e->getMessage();
            
            Notification::make()
                ->title('Lỗi khi chạy audit')
                ->danger()
                ->send();
        }
    }

    // ─── Actions ─────────────────────────────────────────
    public function refreshAudit(): void
    {
        $this->errorMessage = null;
        $this->loadAudit(fresh: true);

        Notification::make()
            ->title('Đã làm mới kết quả SEO Audit.')
            ->success()
            ->send();
    }

    public function clearCache(): void
    {
        $this->errorMessage = null;
        app(SeoAuditService::class)->clearCache();
        $this->loadAudit(fresh: true);

        Notification::make()
            ->title('Đã xóa cache và chạy lại audit.')
            ->success()
            ->send();
    }

    public function quickFix(string $action, string $name): void
    {
        Notification::make()
            ->title('Tính năng Quick Fix đang phát triển')
            ->body("Action: {$action} cho {$name}")
            ->warning()
            ->send();
    }

    public function updatedFilterEntity(): void
    {
        $this->loadAudit();
    }

    public function updatedFilterSeverity(): void
    {
        $this->loadAudit();
    }

    public function updatedSearch(): void
    {
        $this->loadAudit();
    }

    // ─── Header Actions ──────────────────────────────────
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Làm mới audit')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshAudit')
                ->color('primary'),

            Action::make('clear_cache')
                ->label('Xóa cache')
                ->icon('heroicon-o-trash')
                ->action('clearCache')
                ->color('gray')
                ->requiresConfirmation(),
        ];
    }

    // ─── Convenience getters for view ────────────────────
    public function getEntityTypes(): array
    {
        return [
            'all' => 'Tất cả',
            'Product' => 'Sản phẩm',
            'Post' => 'Bài viết',
            'Product Category' => 'Danh mục SP',
            'Post Category' => 'Danh mục Blog',
            'Tag' => 'Tag',
            'Case Study' => 'Dự án',
            'Policy Page' => 'Trang chính sách',
        ];
    }

    public function getSeverityTypes(): array
    {
        return [
            'all' => 'Tất cả mức độ',
            'critical' => 'Critical',
            'warning' => '🟡 Warning',
            'notice' => 'Notice',
        ];
    }
}
