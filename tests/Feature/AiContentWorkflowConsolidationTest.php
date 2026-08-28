<?php

namespace Tests\Feature;

use App\Enums\AIContentJobStatus;
use App\Filament\Resources\AiContentJobs\AiContentJobResource;
use App\Jobs\GenerateBlogDraftJob;
use App\Livewire\AiPostWorkflowPanel;
use App\Models\AiProvider;
use App\Models\Post;
use App\Services\AI\AiProviderReadinessService;
use App\Services\AI\PostAiWorkflowService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiContentWorkflowConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_post_ai_entry_creates_governed_operation_without_overwriting_post(): void
    {
        Bus::fake();
        $user = $this->actor(['post.create', 'post.edit', 'post.view', 'ai_content_job.create', 'ai_content_job.view']);
        $post = Post::factory()->create(['content' => '<p>Original content</p>']);
        AiProvider::create([
            'provider' => 'custom',
            'name' => 'Fixture provider',
            'model' => 'fake-content-model',
            'api_key' => 'fixture-only',
            'status' => 'active',
            'priority' => 'primary',
            'weight' => 1,
        ]);

        $result = app(PostAiWorkflowService::class)->createForPost($post, [
            'title' => $post->title,
            'primary_keyword' => 'điều hòa',
            'ai_requested_fields' => ['content', 'seo', 'faq'],
        ], $user);

        $this->assertTrue($result['dispatched']);
        $this->assertSame('ai_governed', $result['job']->queue_name);
        $this->assertSame($post->id, data_get($result['job']->input_payload, 'target_post_id'));
        $this->assertSame('<p>Original content</p>', $post->refresh()->content);
        Bus::assertDispatched(GenerateBlogDraftJob::class, fn (GenerateBlogDraftJob $job): bool => $job->queue === 'ai_governed');
    }

    public function test_review_then_apply_is_explicit_and_idempotent(): void
    {
        $user = $this->actor(['post.edit', 'post.view', 'ai_content_job.view']);
        $post = Post::factory()->create(['content' => '<p>Original content</p>', 'seo_title' => 'Original SEO']);
        $job = $this->postJob($post, $user->id);
        $workflow = app(PostAiWorkflowService::class);
        $postCount = Post::count();

        $workflow->approve($post, $job, $user);
        $this->assertSame('<p>Original content</p>', $post->refresh()->content);

        $first = $workflow->apply($post, $job->refresh(), $user);
        $second = $workflow->apply($post->refresh(), $job->refresh(), $user);

        $this->assertSame('APPLIED', $first['result']);
        $this->assertSame('NOOP_ALREADY_APPLIED', $second['result']);
        $this->assertSame('<h2>AI draft</h2><p>Safe content.</p>', $post->refresh()->content);
        $this->assertSame('AI SEO title', $post->seo_title);
        $this->assertSame($postCount, Post::count(), 'Applying a Post-origin AI draft must update the same Post.');
    }

    public function test_apply_rejects_stale_target_content_instead_of_overwriting_manual_edits(): void
    {
        $user = $this->actor(['post.edit', 'post.view', 'ai_content_job.view']);
        $post = Post::factory()->create(['content' => '<p>Original content</p>']);
        $job = $this->postJob($post, $user->id);
        $job->update(['input_payload' => array_merge((array) $job->input_payload, [
            'current_content_hash' => hash('sha256', '<p>Original content</p>'),
        ])]);
        $job->update(['status' => AIContentJobStatus::Reviewed]);
        $post->update(['content' => '<p>Manual edit after generation</p>']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI_POST_TARGET_CONTENT_CHANGED');

        app(PostAiWorkflowService::class)->apply($post->refresh(), $job->refresh(), $user);
    }

    public function test_live_panel_uses_persisted_status_and_provider_evidence(): void
    {
        $user = $this->actor(['post.view', 'ai_content_job.view']);
        $post = Post::factory()->create();
        $job = $this->postJob($post, $user->id, AIContentJobStatus::Queued);

        Livewire::actingAs($user)
            ->test(AiPostWorkflowPanel::class, ['postId' => $post->id])
            ->assertSeeHtml('wire:poll.10s')
            ->assertSee('Đang chờ')
            ->assertSee('Provider request: Chưa gửi')
            ->assertSee('Credit/Quota: Không được provider cung cấp');

        $job->update(['status' => AIContentJobStatus::CompletedVerified]);
        Livewire::actingAs($user)
            ->test(AiPostWorkflowPanel::class, ['postId' => $post->id])
            ->assertSee('Chờ duyệt');
    }

    public function test_provider_readiness_is_honest_about_connectivity_and_quota(): void
    {
        $provider = AiProvider::create([
            'provider' => 'custom',
            'name' => 'Configured only',
            'model' => 'model-x',
            'api_key' => 'fixture-only',
            'status' => 'active',
            'priority' => 'primary',
            'weight' => 1,
        ]);

        $view = app(AiProviderReadinessService::class)->present($provider);
        $this->assertTrue($view['configured']);
        $this->assertSame('NOT_CHECKED', $view['connection']);
        $this->assertFalse($view['quota_supported']);
        $this->assertSame('Không được provider cung cấp', $view['quota_label']);
    }

    public function test_history_resource_is_not_a_duplicate_creator(): void
    {
        $this->actor(['ai_content_job.create', 'ai_content_job.view']);

        $this->assertFalse(AiContentJobResource::canCreate());
        $this->assertSame('Nhật ký AI bài viết', AiContentJobResource::getNavigationLabel());
        $this->assertSame('Vận hành', AiContentJobResource::getNavigationGroup());
    }

    public function test_normal_user_cannot_generate_for_existing_post_without_edit_permission(): void
    {
        Bus::fake();
        $user = $this->actor(['post.create', 'post.view', 'ai_content_job.create']);
        $post = Post::factory()->create();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PostAiWorkflowService::class)->createForPost($post, ['title' => $post->title], $user);
    }

    private function postJob(Post $post, int $userId, AIContentJobStatus $status = AIContentJobStatus::CompletedVerified)
    {
        return \App\Models\AiContentJob::create([
            'topic' => $post->title,
            'status' => $status,
            'created_by' => $userId,
            'input_payload' => [
                'target_post_id' => $post->id,
                'context_id' => 'hvac_blog_job_fixture_'.$post->id,
                'requested_fields' => ['content', 'seo'],
            ],
            'output_draft' => '<h2>AI draft</h2><p>Safe content.</p>',
            'output_meta' => ['seo_title' => 'AI SEO title', 'meta_description' => 'AI meta description'],
        ]);
    }

    private function actor(array $permissions)
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);
        $this->actingAs($user);

        return $user;
    }
}
