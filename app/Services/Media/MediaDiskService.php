<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Central service for all media storage operations.
 *
 * Determines the active disk (R2 or local public) and provides
 * helper methods for URL resolution, path normalization, and
 * file existence checks.
 *
 * R2 disk credentials are configured at boot time by AppServiceProvider.
 * This service only reads the current disk state — it does NOT reconfigure.
 */
class MediaDiskService
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * Check if R2 storage is enabled in database settings.
     */
    public function isR2Enabled(): bool
    {
        return (bool) $this->settingService->get('r2_storage.r2_enabled', false);
    }

    /**
     * Check if R2 configuration is complete and valid.
     * Must have key (32 chars), secret (64 chars), bucket, and endpoint.
     */
    public function r2ConfigValid(): bool
    {
        if (!$this->isR2Enabled()) {
            return false;
        }

        $key      = $this->settingService->get('r2_storage.r2_access_key_id');
        $secret   = $this->settingService->get('r2_storage.r2_secret_access_key');
        $bucket   = $this->settingService->get('r2_storage.r2_bucket');
        $endpoint = $this->settingService->get('r2_storage.r2_endpoint');

        if (empty($key) || empty($secret) || empty($bucket) || empty($endpoint)) {
            return false;
        }

        // Cloudflare R2 access key must be exactly 32 characters
        if (strlen($key) !== 32) {
            Log::warning("[MediaDiskService] R2 Access Key has length " . strlen($key) . ", expected 32. Check Site Settings → R2 Storage.");
            return false;
        }

        // Cloudflare R2 secret key must be exactly 64 characters
        if (strlen($secret) !== 64) {
            Log::warning("[MediaDiskService] R2 Secret Key has length " . strlen($secret) . ", expected 64. Check Site Settings → R2 Storage.");
            return false;
        }

        return true;
    }

    /**
     * Get the active upload disk name.
     *
     * - R2 enabled + config valid → 'r2'
     * - R2 enabled but config invalid → 'public' (with warning log)
     * - R2 disabled → 'public' (local storage)
     */
    public function getUploadDisk(): string
    {
        if ($this->isR2Enabled()) {
            if ($this->r2ConfigValid()) {
                return 'r2';
            }
            Log::warning('[MediaDiskService] R2 is enabled but config is incomplete — falling back to public disk.');
        }

        return 'public';
    }

    /**
     * Resolve a public URL for a media path.
     * Delegates to the media_url() helper which checks R2 sync status.
     */
    public function getPublicUrl(?string $path, ?string $fallback = null): ?string
    {
        return media_url($path, $fallback);
    }

    /**
     * Normalize a path: strip leading/trailing slashes, remove /storage/ prefix.
     * Also extracts relative path from full URLs if they match known base URLs.
     */
    public function normalizePath(?string $path): string
    {
        if (empty($path)) return '';

        // If it's a full URL, try to extract relative path
        if ($this->isFullUrl($path)) {
            $path = $this->toRelativePath($path) ?? $path;
        }

        // Strip leading /storage/
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        return trim($path, '/');
    }

    /**
     * Check if a value is a full URL (external or CDN).
     */
    public function isFullUrl(?string $value): bool
    {
        return !empty($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Convert a full URL to a relative path by stripping known base URLs.
     * Returns null if the URL doesn't match any known base.
     */
    public function toRelativePath(?string $urlOrPath): ?string
    {
        if (empty($urlOrPath)) return null;

        // Already relative
        if (!$this->isFullUrl($urlOrPath)) {
            $path = $urlOrPath;
            if (str_starts_with($path, '/storage/')) {
                $path = substr($path, strlen('/storage/'));
            }
            return ltrim($path, '/');
        }

        // Try to strip known base URLs
        $baseUrls = [];

        // APP_URL/storage
        $appUrl = config('app.url');
        if ($appUrl) {
            $baseUrls[] = rtrim($appUrl, '/') . '/storage';
        }

        // R2 CDN URL
        $r2PublicUrl = $this->settingService->get('r2_storage.r2_public_url');
        if ($r2PublicUrl) {
            $defaultFolder = $this->settingService->get('r2_storage.r2_default_folder');
            if ($defaultFolder) {
                $baseUrls[] = rtrim($r2PublicUrl, '/') . '/' . trim($defaultFolder, '/');
            }
            $baseUrls[] = rtrim($r2PublicUrl, '/');
        }

        foreach ($baseUrls as $base) {
            $baseWithSlash = rtrim($base, '/') . '/';
            if (str_starts_with($urlOrPath, $baseWithSlash)) {
                return substr($urlOrPath, strlen($baseWithSlash));
            }
        }

        // Not a recognized URL — return null
        return null;
    }

    /**
     * Check if a file exists on the active disk.
     */
    public function exists(?string $path): bool
    {
        $path = $this->normalizePath($path);
        if (empty($path)) return false;

        return Storage::disk($this->getUploadDisk())->exists($path);
    }

    /**
     * Delete a file from the active disk.
     */
    public function delete(?string $path): bool
    {
        $path = $this->normalizePath($path);
        if (empty($path)) return false;

        return Storage::disk($this->getUploadDisk())->delete($path);
    }

    /**
     * Store an uploaded file to the active disk.
     *
     * When R2 is enabled but upload fails, throws an exception
     * to prevent saving a fake/empty path to the database.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $directory
     * @return string  The stored path
     * @throws \RuntimeException  If upload fails on R2 or local disk
     */
    public function putUploadedFile($file, string $directory): string
    {
        $disk = $this->getUploadDisk();

        try {
            $path = $file->store($directory, [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            if (!$path) {
                throw new \RuntimeException("Upload returned empty path on disk '{$disk}'.");
            }

            Log::info("[MediaDiskService] Uploaded to disk '{$disk}': {$path}");

            return $path;
        } catch (\Throwable $e) {
            Log::error("[MediaDiskService] Upload failed on disk '{$disk}': {$e->getMessage()}");

            // Do NOT return false silently — this prevents saving fake paths to DB
            throw new \RuntimeException(
                "Upload failed on disk '{$disk}': {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * @deprecated R2 disk is now configured at boot time by AppServiceProvider.
     *             This method is kept for backward compatibility but is a no-op.
     */
    public function configureR2Disk(): void
    {
        // No-op: R2 disk credentials are configured globally in
        // AppServiceProvider::boot() from database settings.
        // Keeping this method to avoid breaking existing callers.
    }
}
