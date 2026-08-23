<?php

namespace Tests\Feature;

use App\Filament\Pages\AIQueueHealth;
use App\Filament\Pages\DataTransferPage;
use App\Filament\Pages\MarketingIntegrations;
use App\Filament\Pages\R2SyncManager;
use App\Filament\Resources\AiProductJobs\AiProductJobResource;
use App\Filament\Resources\AiProductJobs\Pages\ListAiProductJobs;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\AIRuntimePolicyWidget;
use App\Filament\Widgets\MainDashboardWidget;
use App\Filament\Widgets\SystemHealthWidget;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Database\Factories\UserFactory;
use Tests\TestCase;

class AdminUxInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_navigation_uses_workflow_domains_and_vietnamese_labels(): void
    {
        $this->assertSame('Bán hàng', LeadResource::getNavigationGroup());
        $this->assertSame('Sản phẩm', ProductResource::getNavigationGroup());
        $this->assertSame('Nội dung', PostResource::getNavigationGroup());
        $this->assertSame('AI Content', AiProductJobResource::getNavigationGroup());
        $this->assertSame('Hệ thống', DataTransferPage::getNavigationGroup());
        $this->assertSame('Vận hành', AIQueueHealth::getNavigationGroup());

        $this->assertSame('Khách hàng tiềm năng', LeadResource::getNavigationLabel());
        $this->assertSame('Nhập / Xuất dữ liệu', DataTransferPage::getNavigationLabel());
        $this->assertSame('Media & CDN', R2SyncManager::getNavigationLabel());
        $this->assertSame('Tích hợp Marketing', MarketingIntegrations::getNavigationLabel());
    }

    public function test_dashboard_prioritizes_business_widgets_and_does_not_discover_runtime_policy(): void
    {
        $this->assertFalse(AIRuntimePolicyWidget::isDiscovered());
        $this->assertLessThan(SystemHealthWidget::getSort(), MainDashboardWidget::getSort());

        $dashboard = File::get(resource_path('views/filament/widgets/main-dashboard.blade.php'));
        $this->assertStringNotContainsString('<style>', $dashboard);
    }

    public function test_operational_pages_make_raw_diagnostics_secondary(): void
    {
        $queueView = File::get(resource_path('views/filament/pages/ai-queue-health.blade.php'));
        $marketingView = File::get(resource_path('views/filament/pages/marketing-integrations.blade.php'));

        $this->assertStringContainsString('Chi tiết vận hành', $queueView);
        $this->assertStringContainsString('<details', $queueView);
        $this->assertStringContainsString('Chi tiết kỹ thuật', $marketingView);
        $this->assertStringContainsString('Thông tin còn thiếu', $marketingView);
    }

    public function test_r2_dangerous_actions_remain_permission_guarded(): void
    {
        $source = File::get(app_path('Filament/Pages/R2SyncManager.php'));

        $this->assertStringContainsString("ActionGroup::make([", $source);
        $this->assertStringContainsString("->color('danger')", $source);
        $this->assertGreaterThanOrEqual(4, substr_count($source, "abort_unless(auth()->user()?->can('r2.sync'), 403)"));
        $this->assertStringContainsString("auth()->user()?->can('r2.test')", $source);
        $this->assertStringContainsString("auth()->user()?->can('r2.scan')", $source);
    }

    public function test_admin_version_uses_the_canonical_release_file(): void
    {
        $this->assertSame('1.26.0', trim(File::get(base_path('VERSION'))));
        $this->assertStringContainsString(
            'bootstrap="tests/bootstrap.php"',
            File::get(base_path('phpunit.xml')),
        );

        $provider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));
        $this->assertStringContainsString("file_get_contents(base_path('VERSION'))", $provider);
        $this->assertStringNotContainsString('v1.24.0', $provider);
    }

    public function test_key_operator_pages_render_for_an_authorized_user(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach (['dashboard.view', 'seo_audit.view', 'bulk_ai_view', 'product.ai_generate', 'r2.view', 'r2.test', 'r2.scan', 'r2.sync'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['dashboard.view', 'seo_audit.view', 'bulk_ai_view', 'product.ai_generate', 'r2.view', 'r2.test', 'r2.scan', 'r2.sync']);
        $this->actingAs($user);

        Livewire::test(AIQueueHealth::class)->assertOk()->assertSee('AI Worker')->assertSee('Chi tiết vận hành');
        Livewire::test(R2SyncManager::class)->assertOk()->assertSee('Cloudflare R2')->assertSee('Di chuyển URL');
        Livewire::test(MarketingIntegrations::class)->assertOk()->assertSee('Đã cấu hình')->assertSee('Sự kiện chuyển đổi khuyến nghị');
        Livewire::test(ListAiProductJobs::class)->assertOk()->assertSee('Đang chờ')->assertSee('Chính sách vận hành AI');
        Livewire::test(MainDashboardWidget::class)->assertOk()->assertSee('Sản Phẩm')->assertSee('Cảnh Báo Cần Xử Lý');
        Livewire::test(SystemHealthWidget::class)->assertOk()->assertSee('Tình trạng hệ thống')->assertSee('AI Worker');
    }
}
