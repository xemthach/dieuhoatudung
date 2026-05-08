<?php

namespace App\Services\Settings;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SettingService
{
    /**
     * Get a setting value. Key format: 'group.key' or pass group separately.
     * Never throws — returns $default on any error.
     */
    public function get(string $key, mixed $default = null, ?string $group = null): mixed
    {
        try {
            // Guard: bảng chưa tồn tại (fresh install)
            if (! Schema::hasTable('site_settings')) {
                return $default;
            }

            if (str_contains($key, '.')) {
                [$group, $key] = explode('.', $key, 2);
            }

            if (empty($group)) {
                return $default;
            }

            $cacheKey = "site_setting_{$group}_{$key}";

            return Cache::rememberForever($cacheKey, function () use ($group, $key, $default) {
                $setting = SiteSetting::where('group', $group)->where('key', $key)->first();

                if (! $setting) {
                    return $default;
                }

                $value = $setting->value;

                if ($setting->is_encrypted && ! empty($value)) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable $e) {
                        return $default; // APP_KEY changed or value corrupted
                    }
                }

                return match ($setting->type) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'integer' => (int) $value,
                    'float'   => (float) $value,
                    'json'    => (! empty($value)) ? json_decode($value, true) : $default,
                    default   => $value ?? $default,
                };
            });
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Set / update a setting value.
     */
    public function set(string $key, mixed $value, ?string $group = null, bool $encrypted = false, string $type = 'text'): void
    {
        if (str_contains($key, '.')) {
            [$group, $key] = explode('.', $key, 2);
        }

        $finalValue = $value;

        if ($encrypted && $value !== null && $value !== '') {
            $finalValue = Crypt::encryptString((string) $value);
        }

        if (is_array($finalValue) || is_object($finalValue)) {
            $finalValue = json_encode($finalValue);
            $type = 'json';
        }

        if (is_bool($finalValue)) {
            $finalValue = $finalValue ? '1' : '0';
            $type = 'boolean';
        }

        SiteSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value'        => $finalValue,
                'is_encrypted' => $encrypted,
                'type'         => $type,
            ]
        );

        $this->forgetCache($key, $group);
    }

    /**
     * Get all settings in a group as associative array.
     */
    public function getGroup(string $group): array
    {
        try {
            $settings = SiteSetting::where('group', $group)->get();
            $result   = [];

            foreach ($settings as $setting) {
                $result[$setting->key] = $this->get("{$group}.{$setting->key}");
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get all settings as nested array [group => [key => value]].
     */
    public function all(): array
    {
        try {
            $result = [];
            SiteSetting::all()->each(function ($s) use (&$result) {
                $result[$s->group][$s->key] = $this->get("{$s->group}.{$s->key}");
            });
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Clear cache for a single setting.
     */
    public function forgetCache(string $key, ?string $group = null): void
    {
        if (str_contains($key, '.')) {
            [$group, $key] = explode('.', $key, 2);
        }
        Cache::forget("site_setting_{$group}_{$key}");
    }

    /**
     * Clear cache for ALL settings.
     */
    public function clearAllCache(): void
    {
        try {
            SiteSetting::all()->each(function ($s) {
                Cache::forget("site_setting_{$s->group}_{$s->key}");
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Mask a secret value for display.
     */
    public function maskSecret(?string $value): ?string
    {
        if (empty($value)) return null;
        $len = strlen($value);
        if ($len <= 4) return str_repeat('*', $len);
        return str_repeat('*', 8) . substr($value, -4);
    }

    /**
     * Cast a raw value to the given type.
     */
    public function castValue(mixed $value, ?string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => is_string($value) ? json_decode($value, true) : $value,
            default   => $value,
        };
    }
}
