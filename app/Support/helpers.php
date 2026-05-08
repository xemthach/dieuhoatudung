<?php

if (! function_exists('setting')) {
    /**
     * Get a site setting value from database via SettingService.
     * Safe to call anywhere — never throws, always returns $default on error.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            // Guard: app container phải sẵn sàng
            if (! app()->bound(\App\Services\Settings\SettingService::class)) {
                return $default;
            }

            return app(\App\Services\Settings\SettingService::class)->get($key, $default);
        } catch (\Throwable $e) {
            // Không bao giờ làm chết app — trả default nếu có bất kỳ lỗi nào
            return $default;
        }
    }
}

if (! function_exists('media_url')) {
    /**
     * Get URL for a media file.
     *
     * Priority:
     * 1. Full external URL → return as-is
     * 2. R2 enabled + file tracked as synced → CDN URL
     * 3. Local file → local storage URL
     * 4. Fallback
     *
     * NEVER returns CDN URL unless file is confirmed synced to R2.
     */
    function media_url(mixed $path = null, ?string $fallback = null): ?string
    {
        // Handle arrays that may come from setting() when type=json
        if (is_array($path)) {
            $path = collect($path)->first();
        }

        if (empty($path) || $path === '{}' || $path === '[]') {
            return $fallback;
        }

        // Full URL → return as-is (external images, already-resolved CDN URLs, etc.)
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Normalize: strip leading /storage/ to get relative path
        $relativePath = $path;
        if (str_starts_with($relativePath, '/storage/')) {
            $relativePath = substr($relativePath, strlen('/storage/'));
        }
        $relativePath = ltrim($relativePath, '/');

        $r2Enabled = setting('r2_storage.r2_enabled', false);
        $publicUrl = setting('r2_storage.r2_public_url');
        $defaultFolder = setting('r2_storage.r2_default_folder');

        // Build CDN base URL
        $baseCdnUrl = $publicUrl;
        if (!empty($baseCdnUrl) && !empty($defaultFolder)) {
            $baseCdnUrl = rtrim($publicUrl, '/') . '/' . trim($defaultFolder, '/');
        }

        // R2 enabled: only return CDN URL if file is CONFIRMED synced
        if ($r2Enabled && !empty($publicUrl)) {
            $isSynced = \Illuminate\Support\Facades\Cache::remember(
                'media_synced_' . md5($relativePath), 600,
                fn() => \App\Models\MediaFile::where('path', $relativePath)
                    ->where('is_synced_to_r2', true)
                    ->exists()
            );

            if ($isSynced) {
                return rtrim($baseCdnUrl, '/') . '/' . $relativePath;
            }
        }

        // Fallback: local storage URL
        return \Illuminate\Support\Facades\Storage::disk('public')->url($relativePath);
    }
}
