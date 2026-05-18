<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Settings\SettingService;
use App\Services\Settings\UploadSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_upload_settings_fall_back_to_safe_defaults(): void
    {
        SiteSetting::create(['group' => 'upload', 'key' => 'document_max_size_kb', 'value' => '', 'type' => 'text']);
        SiteSetting::create(['group' => 'upload', 'key' => 'file_max_size_kb', 'value' => '', 'type' => 'text']);
        SiteSetting::create(['group' => 'upload', 'key' => 'allowed_file_types', 'value' => '', 'type' => 'text']);

        app(SettingService::class)->clearAllCache();

        $uploads = app(UploadSettingService::class);

        $this->assertSame(51200, $uploads->documentMaxSizeKb());
        $this->assertSame(51200, $uploads->fileMaxSizeKb());
        $this->assertContains('application/pdf', $uploads->allowedFileTypes());
        $this->assertSame(51200, $uploads->temporaryFileUploadMaxSizeKb());
    }

    public function test_livewire_temporary_upload_limit_uses_application_upload_limit(): void
    {
        $this->assertContains('max:51200', config('livewire.temporary_file_upload.rules'));
    }

    public function test_campaign_image_limit_uses_general_image_limit(): void
    {
        SiteSetting::updateOrCreate(
            ['group' => 'upload', 'key' => 'image_max_size_kb'],
            ['value' => '20480', 'type' => 'text']
        );

        app(SettingService::class)->clearAllCache();

        $uploads = app(UploadSettingService::class);

        $this->assertSame(20480, $uploads->campaignImageMaxSizeKb());
    }

    public function test_allowed_image_extensions_are_derived_from_mime_settings(): void
    {
        SiteSetting::updateOrCreate(
            ['group' => 'upload', 'key' => 'allowed_image_types'],
            ['value' => 'image/jpeg,image/webp', 'type' => 'text']
        );

        app(SettingService::class)->clearAllCache();

        $this->assertSame(['jpg', 'jpeg', 'webp'], app(UploadSettingService::class)->allowedImageExtensions());
    }
}
