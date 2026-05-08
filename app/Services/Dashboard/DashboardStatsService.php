<?php

namespace App\Services\Dashboard;

use App\Enums\AIContentJobStatus;
use App\Enums\LeadStatus;
use App\Enums\PostStatus;
use App\Models\AiContentJob;
use App\Models\AiProvider;
use App\Models\Lead;
use App\Models\MailLog;
use App\Models\Post;
use App\Models\Product;
use App\Models\R2SyncJob;
use App\Models\Tag;
use App\Services\Mail\MailProviderService;
use App\Services\Media\R2ConnectionService;
use App\Services\Seo\SeoAuditService;
use App\Services\Settings\SettingService;

class DashboardStatsService
{
    public function __construct(
        private SettingService $settingService,
        private MailProviderService $mailProviderService,
        private R2ConnectionService $r2ConnectionService,
    ) {}

    // ─── LEADS ─────────────────────────────────────────────
    public function getLeadStats(): array
    {
        return [
            'today'     => Lead::whereDate('created_at', today())->count(),
            'this_week' => Lead::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'pending'   => Lead::where('status', LeadStatus::New)->count(),
            'latest'    => Lead::with('product')->latest()->take(5)->get(),
        ];
    }

    // ─── PRODUCTS ──────────────────────────────────────────
    public function getProductStats(): array
    {
        return [
            'total'         => Product::count(),
            'missing_seo'   => Product::whereNull('seo_title')->orWhereNull('seo_description')->count(),
            'missing_image' => Product::whereNull('main_image')->count(),
            'on_sale'       => Product::whereNotNull('sale_price')->count(),
        ];
    }

    // ─── POSTS ─────────────────────────────────────────────
    public function getPostStats(): array
    {
        return [
            'total'       => Post::count(),
            'missing_seo' => Post::whereNull('seo_title')->orWhereNull('seo_description')->count(),
            'draft'       => Post::where('status', PostStatus::Draft)->count(),
        ];
    }

    // ─── SEO ───────────────────────────────────────────────
    public function getSeoStats(): array
    {
        try {
            $seoAuditService = app(SeoAuditService::class);
            $seoIssues = $seoAuditService->run(false);

            $critical = $seoIssues->where('severity', 'critical')->count();
            $warning  = $seoIssues->where('severity', 'warning')->count();
            $notice   = $seoIssues->where('severity', 'notice')->count();

            $score = 100 - ($critical * 5) - ($warning * 3) - ($notice * 1);

            return [
                'total'    => $seoIssues->count(),
                'critical' => $critical,
                'warning'  => $warning,
                'notice'   => $notice,
                'score'    => max(0, min(100, $score)),
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0, 'critical' => 0, 'warning' => 0,
                'notice' => 0, 'score' => 100,
            ];
        }
    }

    // ─── MAIL STATUS ───────────────────────────────────────
    public function getMailStatus(): array
    {
        $enabled      = (bool) $this->settingService->get('mail.mail_enabled', false);
        $providerName = $this->settingService->get('mail.mail_provider', 'smtp');

        // Real stats from mail log (7 days)
        $stats7 = MailLog::stats(7);

        if (!$enabled) {
            return [
                'enabled'      => false,
                'provider'     => $providerName,
                'configured'   => false,
                'status'       => 'disabled',
                'label'        => 'Tắt',
                'stats_7d'     => $stats7,
            ];
        }

        // Check if provider is configured
        $provider   = $this->mailProviderService->getProvider($providerName);
        $configured = $provider && $provider->isConfigured();

        if (!$configured) {
            return [
                'enabled'    => true,
                'provider'   => $providerName,
                'configured' => false,
                'status'     => 'misconfigured',
                'label'      => 'Thiếu cấu hình',
                'stats_7d'   => $stats7,
            ];
        }

        // Check recent mail log for failures
        $lastLog     = MailLog::latest()->first();
        $recentFails = MailLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentFails >= 3) {
            return [
                'enabled'    => true,
                'provider'   => $providerName,
                'configured' => true,
                'status'     => 'failed',
                'label'      => 'Lỗi gửi mail',
                'last_error' => $lastLog?->error_message,
                'stats_7d'   => $stats7,
            ];
        }

        $rate = $stats7['total'] > 0
            ? round(($stats7['sent'] / $stats7['total']) * 100)
            : 100;

        return [
            'enabled'      => true,
            'provider'     => $providerName,
            'configured'   => true,
            'status'       => 'active',
            'label'        => 'Hoạt động',
            'last_sent_at' => $lastLog?->sent_at,
            'success_rate' => $rate,
            'stats_7d'     => $stats7,
        ];
    }

    // ─── R2/CDN STATUS ─────────────────────────────────────
    public function getR2Status(): array
    {
        $enabled = $this->r2ConnectionService->isEnabled();

        if (!$enabled) {
            return [
                'enabled'     => false,
                'configured'  => false,
                'status'      => 'disabled',
                'label'       => 'Tắt',
                'mode'        => 'Local',
                'last_sync'   => null,
                'failed_jobs' => 0,
            ];
        }

        // Check config completeness via settings
        $hasKey      = !empty($this->settingService->get('r2_storage.r2_access_key_id'));
        $hasSecret   = !empty($this->settingService->get('r2_storage.r2_secret_access_key'));
        $hasBucket   = !empty($this->settingService->get('r2_storage.r2_bucket'));
        $hasEndpoint = !empty($this->settingService->get('r2_storage.r2_endpoint'));
        $configured  = $hasKey && $hasSecret && $hasBucket && $hasEndpoint;

        $failedJobs = R2SyncJob::where('status', 'failed')->count();
        $lastSync   = R2SyncJob::whereIn('status', ['completed', 'completed_with_errors'])
            ->latest('finished_at')
            ->first();

        if (!$configured) {
            return [
                'enabled'     => true,
                'configured'  => false,
                'status'      => 'misconfigured',
                'label'       => 'Thiếu cấu hình',
                'mode'        => 'Local (fallback)',
                'last_sync'   => null,
                'failed_jobs' => $failedJobs,
            ];
        }

        return [
            'enabled'     => true,
            'configured'  => true,
            'status'      => 'active',
            'label'       => 'Hoạt động',
            'mode'        => 'CDN Active',
            'last_sync'   => $lastSync?->finished_at,
            'failed_jobs' => $failedJobs,
        ];
    }

    // ─── AI STATUS ─────────────────────────────────────────
    public function getAIStatus(): array
    {
        $activeProviders = AiProvider::where('status', 'active')
            ->whereNull('deleted_at')
            ->get();

        $activeCount     = $activeProviders->count();
        $rateLimitedAll  = $activeCount > 0 && $activeProviders->every(fn ($p) =>
            $p->rate_limited_until && $p->rate_limited_until->isFuture()
        );

        $pendingJobs = AiContentJob::whereIn('status', [
            AIContentJobStatus::Pending,
            AIContentJobStatus::Processing,
        ])->count();

        $failedJobs = AiContentJob::where('status', AIContentJobStatus::Failed)->count();

        if ($activeCount === 0) {
            $status = 'disabled';
            $label  = 'Tắt';
        } elseif ($rateLimitedAll) {
            $status = 'rate_limited';
            $label  = 'Rate Limited';
        } else {
            $status = 'active';
            $label  = 'Hoạt động';
        }

        return [
            'enabled'          => $activeCount > 0,
            'active_providers' => $activeCount,
            'status'           => $status,
            'label'            => $label,
            'pending_jobs'     => $pendingJobs,
            'failed_jobs'      => $failedJobs,
        ];
    }

    // ─── ALERTS ────────────────────────────────────────────
    public function getAlerts(): \Illuminate\Support\Collection
    {
        $alerts   = collect();
        $products = $this->getProductStats();
        $posts    = $this->getPostStats();

        // Products missing SEO
        if ($products['missing_seo'] > 0) {
            $alerts->push([
                'title'       => 'Sản phẩm thiếu SEO',
                'description' => "Có {$products['missing_seo']} sản phẩm cần tối ưu thẻ Meta.",
                'severity'    => 'warning',
                'url'         => route('filament.admin.resources.products.index'),
            ]);
        }

        // Posts missing SEO
        if ($posts['missing_seo'] > 0) {
            $alerts->push([
                'title'       => 'Bài viết thiếu SEO',
                'description' => "Có {$posts['missing_seo']} bài viết cần tối ưu Meta.",
                'severity'    => 'warning',
                'url'         => route('filament.admin.resources.posts.index'),
            ]);
        }

        // Products missing image
        if ($products['missing_image'] > 0) {
            $alerts->push([
                'title'       => 'Sản phẩm thiếu ảnh',
                'description' => "Có {$products['missing_image']} sản phẩm chưa có ảnh đại diện.",
                'severity'    => 'critical',
                'url'         => route('filament.admin.resources.products.index'),
            ]);
        }

        // R2 failed jobs
        $r2 = $this->getR2Status();
        if ($r2['failed_jobs'] > 0) {
            $alerts->push([
                'title'       => 'Đồng bộ R2 thất bại',
                'description' => "Có {$r2['failed_jobs']} tiến trình đồng bộ ảnh bị lỗi.",
                'severity'    => 'critical',
                'url'         => route('filament.admin.pages.r2-sync-manager'),
            ]);
        }

        // Mail misconfigured or disabled
        $mail = $this->getMailStatus();
        if ($mail['status'] === 'misconfigured') {
            $alerts->push([
                'title'       => 'Mail thiếu cấu hình',
                'description' => "Provider {$mail['provider']} chưa được cấu hình đầy đủ.",
                'severity'    => 'warning',
                'url'         => route('filament.admin.pages.manage-settings'),
            ]);
        } elseif ($mail['status'] === 'failed') {
            $alerts->push([
                'title'       => 'Lỗi gửi Mail',
                'description' => "Hệ thống gặp lỗi khi gửi mail gần đây.",
                'severity'    => 'critical',
                'url'         => route('filament.admin.resources.mail-logs.index'),
            ]);
        }
        // NOTE: If mail is 'active' or 'disabled' → no alert needed

        // AI status
        $ai = $this->getAIStatus();
        if ($ai['failed_jobs'] > 0) {
            $alerts->push([
                'title'       => 'AI Job thất bại',
                'description' => "Có {$ai['failed_jobs']} job AI content bị lỗi.",
                'severity'    => 'warning',
                'url'         => route('filament.admin.resources.ai-content-jobs.index'),
            ]);
        }
        if ($ai['status'] === 'rate_limited') {
            $alerts->push([
                'title'       => 'AI bị Rate Limit',
                'description' => "Tất cả provider AI đang bị rate limit.",
                'severity'    => 'warning',
                'url'         => route('filament.admin.resources.ai-providers.index'),
            ]);
        }

        return $alerts;
    }

    // ─── QUICK ACTIONS ─────────────────────────────────────
    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'Tạo SP',
                'icon'  => 'plus',
                'url'   => route('filament.admin.resources.products.create'),
            ],
            [
                'label' => 'Tạo Bài',
                'icon'  => 'plus',
                'url'   => route('filament.admin.resources.posts.create'),
            ],
            [
                'label' => 'Tạo Lead',
                'icon'  => 'plus',
                'url'   => route('filament.admin.resources.leads.create'),
            ],
            [
                'label' => 'SEO Audit',
                'icon'  => 'search',
                'url'   => route('filament.admin.pages.seo-audit'),
            ],
            [
                'label' => 'R2 Sync',
                'icon'  => 'sync',
                'url'   => route('filament.admin.pages.r2-sync-manager'),
            ],
            [
                'label' => 'Cài đặt',
                'icon'  => 'cog',
                'url'   => route('filament.admin.pages.manage-settings'),
            ],
        ];
    }
}
