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
     * Get URL for a media file, handling R2 public URL fallback.
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

        $r2Enabled = setting('r2_storage.r2_enabled', false);
        $publicUrl = setting('r2_storage.r2_public_url');
        $defaultFolder = setting('r2_storage.r2_default_folder');
        
        $baseCdnUrl = $publicUrl;
        if (!empty($baseCdnUrl) && !empty($defaultFolder)) {
            $baseCdnUrl = rtrim($publicUrl, '/') . '/' . trim($defaultFolder, '/');
        }

        // Handle full URLs
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            if ($r2Enabled && !empty($publicUrl)) {
                if (str_contains($path, '/storage/')) {
                    $parts = explode('/storage/', $path);
                    if (count($parts) === 2) {
                        return rtrim($baseCdnUrl, '/') . '/' . ltrim($parts[1], '/');
                    }
                }
            }
            return $path;
        }

        // Handle paths starting with /storage/
        $relativePath = str_starts_with($path, '/storage/') ? substr($path, strlen('/storage/')) : $path;

        if ($r2Enabled && !empty($publicUrl)) {
            // Check if file is tracked as synced to R2
            $isSynced = \Illuminate\Support\Facades\Cache::remember('media_file_synced_' . md5($relativePath), 600, function() use ($relativePath) {
                return \App\Models\MediaFile::where('path', $relativePath)->where('is_synced_to_r2', true)->exists();
            });

            // If it's synced, OR if the local file doesn't exist (meaning it was a new upload straight to R2)
            $localFilePath = public_path('storage/' . ltrim($relativePath, '/'));
            if ($isSynced || !file_exists($localFilePath)) {
                return rtrim($baseCdnUrl, '/') . '/' . ltrim($relativePath, '/');
            }
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($relativePath);
    }
}
