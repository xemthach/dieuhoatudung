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
     * Get the active upload disk name.
     *
     * - R2 enabled → 'r2' (configured by AppServiceProvider::boot)
     * - R2 disabled → 'public' (local storage)
     */
    public function getUploadDisk(): string
    {
        return $this->isR2Enabled() ? 'r2' : 'public';
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
     */
    public function normalizePath(?string $path): string
    {
        if (empty($path)) return '';

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
     * Check if a file exists on the active disk.
     */
    public function exists(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (empty($path)) return false;

        return Storage::disk($this->getUploadDisk())->exists($path);
    }

    /**
     * Delete a file from the active disk.
     */
    public function delete(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (empty($path)) return false;

        return Storage::disk($this->getUploadDisk())->delete($path);
    }

    /**
     * Store an uploaded file to the active disk.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $directory
     * @return string|false  The stored path or false on failure
     */
    public function putUploadedFile($file, string $directory): string|false
    {
        $disk = $this->getUploadDisk();

        try {
            $path = $file->store($directory, [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            if ($path) {
                Log::info("[MediaDiskService] Uploaded to disk '{$disk}': {$path}");
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error("[MediaDiskService] Upload failed on disk '{$disk}': {$e->getMessage()}");
            return false;
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
