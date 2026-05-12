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

    private const DEFAULT_IMAGE_MAX_SIZE_KB = 5120;

    private const DEFAULT_FILE_MAX_SIZE_KB = 51200;

    private const DEFAULT_AVATAR_MAX_SIZE_KB = 2048;

    private const DEFAULT_REVIEW_IMAGE_MAX_SIZE_KB = 3072;

    private const DEFAULT_PRODUCT_IMAGE_MAX_SIZE_KB = 5120;

    private const DEFAULT_BRAND_LOGO_MAX_SIZE_KB = 2048;

    private const DEFAULT_DOCUMENT_MAX_SIZE_KB = 51200;

    private const DEFAULT_ALLOWED_IMAGE_TYPES = 'image/jpeg,image/png,image/webp,image/gif';

    private const DEFAULT_ALLOWED_FILE_TYPES = 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const DEFAULT_MAX_IMAGES_PER_UPLOAD = 10;

    public function __construct(SettingService $settings)
    {
        $this->settings = $settings;
    }

    /* ── Size limits (KB) ────────────────────────────────────── */

    public function imageMaxSizeKb(): int
    {
        return $this->integerSetting('upload.image_max_size_kb', self::DEFAULT_IMAGE_MAX_SIZE_KB);
    }

    public function fileMaxSizeKb(): int
    {
        return $this->integerSetting('upload.file_max_size_kb', self::DEFAULT_FILE_MAX_SIZE_KB);
    }

    public function avatarMaxSizeKb(): int
    {
        return $this->integerSetting('upload.avatar_max_size_kb', self::DEFAULT_AVATAR_MAX_SIZE_KB);
    }

    public function reviewImageMaxSizeKb(): int
    {
        return $this->integerSetting('upload.review_image_max_size_kb', self::DEFAULT_REVIEW_IMAGE_MAX_SIZE_KB);
    }

    public function productImageMaxSizeKb(): int
    {
        return $this->integerSetting('upload.product_image_max_size_kb', self::DEFAULT_PRODUCT_IMAGE_MAX_SIZE_KB);
    }

    public function brandLogoMaxSizeKb(): int
    {
        return $this->integerSetting('upload.brand_logo_max_size_kb', self::DEFAULT_BRAND_LOGO_MAX_SIZE_KB);
    }

    public function documentMaxSizeKb(): int
    {
        return $this->integerSetting('upload.document_max_size_kb', self::DEFAULT_DOCUMENT_MAX_SIZE_KB);
    }

    public function temporaryFileUploadMaxSizeKb(): int
    {
        return max(
            $this->imageMaxSizeKb(),
            $this->fileMaxSizeKb(),
            $this->avatarMaxSizeKb(),
            $this->reviewImageMaxSizeKb(),
            $this->productImageMaxSizeKb(),
            $this->brandLogoMaxSizeKb(),
            $this->documentMaxSizeKb(),
        );
    }

    /* ── Type lists ──────────────────────────────────────────── */

    public function allowedImageTypes(): array
    {
        return $this->csvSetting('upload.allowed_image_types', self::DEFAULT_ALLOWED_IMAGE_TYPES);
    }

    public function allowedFileTypes(): array
    {
        return $this->csvSetting('upload.allowed_file_types', self::DEFAULT_ALLOWED_FILE_TYPES);
    }

    /* ── Quantity limits ─────────────────────────────────────── */

    public function maxImagesPerUpload(): int
    {
        return $this->integerSetting('upload.max_images_per_upload', self::DEFAULT_MAX_IMAGES_PER_UPLOAD);
    }

    /* ── Human-readable helpers (for helperText) ─────────────── */

    public function formatMb(int $kb): string
    {
        $mb = round($kb / 1024, 1);

        return $mb >= 1 ? "{$mb} MB" : "{$kb} KB";
    }

    private function integerSetting(string $key, int $default): int
    {
        $value = $this->settings->get($key, $default);

        if ($value === null || $value === '') {
            return $default;
        }

        $value = (int) $value;

        return $value > 0 ? $value : $default;
    }

    private function csvSetting(string $key, string $default): array
    {
        $raw = $this->settings->get($key, $default);

        if ($raw === null || trim((string) $raw) === '') {
            $raw = $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
