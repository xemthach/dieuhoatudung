<?php

declare(strict_types=1);

use App\Models\AiContentJob;
use App\Models\AiProvider;
use App\Models\Post;
use App\Models\Promotion;
use App\Models\SiteCampaign;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'setup';
$marker = 'browser-certification-20260828';
$statePath = storage_path('framework/testing/marketing-browser-fixture.json');

if ($mode === 'cleanup') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
    DB::transaction(function () use ($state): void {
        foreach (($state['provider_statuses'] ?? []) as $providerId => $status) {
            AiProvider::query()->whereKey($providerId)->update(['status' => $status]);
        }
        $navigation = $state['navigation_setting'] ?? null;
        if ($navigation === null) {
            SiteSetting::query()->where('group', 'navigation')->where('key', 'header_primary')->delete();
        } else {
            SiteSetting::query()->updateOrCreate(
                ['group' => 'navigation', 'key' => 'header_primary'],
                $navigation,
            );
        }
        DB::table('site_campaign_events')->whereIn('site_campaign_id', $state['campaign_ids'] ?? [0])->delete();
        AiContentJob::query()->whereIn('id', $state['ai_job_ids'] ?? [0])->delete();
        SiteCampaign::withTrashed()->whereIn('id', $state['campaign_ids'] ?? [0])->forceDelete();
        Promotion::withTrashed()->whereIn('id', $state['promotion_ids'] ?? [0])->forceDelete();
        Post::withTrashed()->whereIn('id', $state['post_ids'] ?? [0])->forceDelete();
        User::query()->whereIn('id', $state['user_ids'] ?? [0])->delete();
    });
    app(\App\Services\Settings\SettingService::class)->clearAllCache();
    @unlink(storage_path('app/public/browser-certification-20260828.svg'));
    @unlink($statePath);
    echo json_encode(['cleaned' => true], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'snapshot') {
    $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    $post = Post::find($state['post_id']);
    $promotion = Promotion::find($state['promotion_ai_id']);
    echo json_encode([
        'post_id' => $post?->id,
        'post_count' => Post::query()->count(),
        'post_content' => $post?->content,
        'job_status' => AiContentJob::find($state['ai_job_id'])?->status?->value,
        'job_payload' => AiContentJob::find($state['ai_job_id'])?->input_payload,
        'preview_events' => DB::table('site_campaign_events')->where('site_campaign_id', $state['preview_campaign_id'])->count(),
        'promotion' => $promotion?->only(['id', 'description', 'content', 'discount_type', 'discount_value', 'start_at', 'end_at', 'scope']),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit(0);
}

DB::transaction(function () use ($marker, $statePath): void {
    // Browser certification must never call a real provider. Force the existing
    // governed generator down its deterministic local-draft path and restore
    // provider status during cleanup.
    $providerStatuses = AiProvider::query()->pluck('status', 'id')->all();
    AiProvider::query()->update(['status' => 'inactive']);
    $navigationSetting = SiteSetting::query()->where('group', 'navigation')->where('key', 'header_primary')->first();
    $navigationSnapshot = $navigationSetting?->only(['value', 'type', 'is_encrypted', 'is_public']);
    SiteSetting::query()->updateOrCreate(
        ['group' => 'navigation', 'key' => 'header_primary'],
        [
            'value' => json_encode([
                [
                    'label' => 'Sản phẩm kiểm thử',
                    'type' => 'route',
                    'target' => 'products.index',
                    'sort_order' => 20,
                    'is_active' => true,
                    'open_new_tab' => false,
                ],
                [
                    'label' => 'Bảng giá',
                    'type' => 'route',
                    'target' => 'price-list',
                    'sort_order' => 30,
                    'is_active' => true,
                    'open_new_tab' => false,
                ],
                [
                    'label' => 'Blog',
                    'type' => 'route',
                    'target' => 'blog.index',
                    'sort_order' => 40,
                    'is_active' => true,
                    'open_new_tab' => false,
                ],
            ], JSON_THROW_ON_ERROR),
            'type' => 'json',
            'is_encrypted' => false,
            'is_public' => true,
        ],
    );
    $imagePath = $marker.'.svg';
    file_put_contents(storage_path('app/public/'.$imagePath), '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="320"><rect width="100%" height="100%" fill="#f97316"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="34">Browser Campaign Image</text></svg>');
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $password = bin2hex(random_bytes(18));
    $user = User::create([
        'name' => 'Browser Certification Operator',
        'email' => $marker.'@example.test',
        'password' => Hash::make($password),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    $initialHtml = '<h2>Kiểm thử trình soạn thảo AI</h2><p>Nội dung an toàn để kiểm tra con trỏ, lựa chọn và lưu.</p><p style="position:fixed;inset:0" contenteditable="false" onclick="return false"><a href="javascript:alert(1)">Liên kết không an toàn</a></p>';
    $post = Post::create([
        'title' => 'Browser Certification Post',
        'slug' => $marker.'-post',
        'content' => app(\App\Services\Content\RichHtmlSanitizer::class)->sanitize($initialHtml),
        'excerpt' => 'Isolated browser fixture',
        'status' => 'draft',
        'ai_generated' => false,
        'ai_review_status' => 'none',
        'schema_enabled' => true,
    ]);

    $draft = '<h2>Nội dung AI đã kiểm duyệt</h2><p>Bản nháp dùng cho chứng nhận browser, không gọi provider.</p>';
    $job = AiContentJob::create([
        'topic' => 'Browser Certification Post',
        'primary_keyword' => 'điều hòa kiểm thử',
        'input_payload' => [
            'target_type' => Post::class,
            'target_post_id' => $post->id,
            'operation' => 'update_post',
            'requested_fields' => ['content'],
            'current_content_hash' => hash('sha256', (string) $post->content),
            'fixture_transport' => 'deterministic_non_provider',
        ],
        'output_draft' => $draft,
        'status' => 'completed_verified',
        'created_by' => $user->id,
    ]);

    $campaignBase = [
        'type' => 'modal', 'placement' => 'home', 'device' => 'both', 'priority' => 100,
        'content_json' => ['title' => 'Campaign Browser Active', 'subtitle' => 'Production renderer', 'content' => 'Fixture cô lập', 'button_primary_text' => 'Xem', 'button_primary_url' => '/'],
        'design_json' => ['position' => 'center'],
        'frequency_json' => ['delay_seconds' => 0, 'frequency' => 'visit'],
        'created_by' => $user->id, 'updated_by' => $user->id,
    ];
    $active = SiteCampaign::create($campaignBase + ['title' => 'Campaign Browser Active', 'status' => 'active']);
    $inactive = SiteCampaign::create(array_merge($campaignBase, ['title' => 'Campaign Browser Inactive', 'status' => 'draft', 'priority' => 90, 'content_json' => ['title' => 'Campaign Browser Inactive']]));
    $future = SiteCampaign::create(array_merge($campaignBase, ['title' => 'Campaign Browser Future', 'status' => 'active', 'priority' => 80, 'start_at' => now()->addDay(), 'content_json' => ['title' => 'Campaign Browser Future']]));
    $preview = SiteCampaign::create(array_merge($campaignBase, ['title' => 'Campaign Browser Preview', 'status' => 'draft', 'placement' => 'blog_post', 'content_json' => ['title' => 'Campaign Browser Preview', 'subtitle' => 'Inactive preview uses production component']]));
    $image = SiteCampaign::create(array_merge($campaignBase, ['title' => 'Campaign Browser Image', 'type' => 'image_popup', 'status' => 'draft', 'content_json' => ['title' => 'Campaign Browser Image', 'image' => $imagePath]]));
    $video = SiteCampaign::create(array_merge($campaignBase, ['title' => 'Campaign Browser Video', 'type' => 'video_popup', 'status' => 'draft', 'content_json' => ['title' => 'Campaign Browser Video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']]));

    $promotionRows = [];
    foreach (['banner', 'landing', 'popup', 'announcement_bar'] as $placement) {
        $promotionRows[$placement] = Promotion::create([
            'title' => 'Browser Promotion '.ucfirst($placement),
            'slug' => $marker.'-'.$placement,
            'description' => 'Mô tả promotion '.$placement,
            'content' => '<p>Nội dung promotion '.$placement.'</p>',
            'cta_content' => 'Liên hệ tư vấn',
            'banner_copy' => 'Ưu đãi browser '.$placement,
            'placement' => $placement,
            'scope' => 'global',
            'discount_type' => 'percent',
            'discount_value' => 7.5,
            'is_active' => true,
            'start_at' => now()->subHour(),
            'end_at' => now()->addDay(),
        ]);
    }
    $promotionAi = Promotion::create([
        'title' => 'Browser Promotion AI',
        'slug' => $marker.'-ai',
        'description' => null,
        'content' => null,
        'placement' => 'banner',
        'scope' => 'global',
        'discount_type' => 'fixed',
        'discount_value' => 123456,
        'is_active' => false,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(3),
    ]);

    $campaigns = [$active, $inactive, $future, $preview, $image, $video];
    $promotions = [...array_values($promotionRows), $promotionAi];
    $state = [
        'provider_statuses' => $providerStatuses,
        'navigation_setting' => $navigationSnapshot,
        'email' => $user->email, 'password' => $password,
        'user_ids' => [$user->id], 'post_ids' => [$post->id], 'post_id' => $post->id,
        'ai_job_ids' => [$job->id], 'ai_job_id' => $job->id,
        'campaign_ids' => array_map(fn ($row) => $row->id, $campaigns),
        'active_campaign_id' => $active->id, 'preview_campaign_id' => $preview->id,
        'image_campaign_id' => $image->id, 'video_campaign_id' => $video->id,
        'promotion_ids' => array_map(fn ($row) => $row->id, $promotions),
        'promotion_banner_id' => $promotionRows['banner']->id,
        'promotion_landing_id' => $promotionRows['landing']->id,
        'promotion_popup_id' => $promotionRows['popup']->id,
        'promotion_announcement_id' => $promotionRows['announcement_bar']->id,
        'promotion_ai_id' => $promotionAi->id,
    ];
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
});
app(\App\Services\Settings\SettingService::class)->clearAllCache();
