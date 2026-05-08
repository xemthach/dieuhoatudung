<?php

namespace App\Services\Settings;

/**
 * Centralized upload limits read from SiteSettings (group: upload).
 * Falls back to sensible defaults if no DB setting exists.
 *
 * All size values are in KILOBYTES (KB) for direct use with Filament's maxSize().
 */
class UploadSettingService
{
    protected SettingService $settings;

    public function __construct(SettingService $settings)
    {
        $this->settings = $settings;
    }

    /* ── Size limits (KB) ────────────────────────────────────── */

    public function imageMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.image_max_size_kb', 5120);
    }

    public function fileMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.file_max_size_kb', 10240);
    }

    public function avatarMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.avatar_max_size_kb', 2048);
    }

    public function reviewImageMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.review_image_max_size_kb', 3072);
    }

    public function productImageMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.product_image_max_size_kb', 5120);
    }

    public function brandLogoMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.brand_logo_max_size_kb', 2048);
    }

    public function documentMaxSizeKb(): int
    {
        return (int) $this->settings->get('upload.document_max_size_kb', 10240);
    }

    /* ── Type lists ──────────────────────────────────────────── */

    public function allowedImageTypes(): array
    {
        $raw = $this->settings->get('upload.allowed_image_types', 'image/jpeg,image/png,image/webp,image/gif');
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    public function allowedFileTypes(): array
    {
        $raw = $this->settings->get('upload.allowed_file_types', 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /* ── Quantity limits ─────────────────────────────────────── */

    public function maxImagesPerUpload(): int
    {
        return (int) $this->settings->get('upload.max_images_per_upload', 10);
    }

    /* ── Human-readable helpers (for helperText) ─────────────── */

    public function formatMb(int $kb): string
    {
        $mb = round($kb / 1024, 1);
        return $mb >= 1 ? "{$mb} MB" : "{$kb} KB";
    }
}
