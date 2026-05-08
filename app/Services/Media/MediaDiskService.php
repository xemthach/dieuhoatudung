<?php

namespace App\Services\Media;

use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Storage;

class MediaDiskService
{
    public function __construct(
        private SettingService $settingService
    ) {}

    public function isR2Enabled(): bool
    {
        return (bool) $this->settingService->get('r2_storage.r2_enabled', false);
    }

    public function getUploadDisk(): string
    {
        return $this->isR2Enabled() ? 'r2' : 'public';
    }

    public function getPublicUrl(string $path): string
    {
        return media_url($path);
    }

    public function normalizePath(string $path): string
    {
        return trim($path, '/');
    }

    /**
     * Configure R2 disk dynamically based on database settings.
     */
    public function configureR2Disk(): void
    {
        if (!$this->isR2Enabled()) {
            return;
        }

        config([
            'filesystems.disks.r2' => [
                'driver' => 's3',
                'key' => $this->settingService->get('r2_storage.r2_access_key_id'),
                'secret' => $this->settingService->get('r2_storage.r2_secret_access_key'),
                'region' => 'auto',
                'bucket' => $this->settingService->get('r2_storage.r2_bucket'),
                'endpoint' => $this->settingService->get('r2_storage.r2_endpoint'),
                'url' => $this->settingService->get('r2_storage.r2_public_url'),
                'use_path_style_endpoint' => true,
                'throw' => false,
            ]
        ]);
    }
}
