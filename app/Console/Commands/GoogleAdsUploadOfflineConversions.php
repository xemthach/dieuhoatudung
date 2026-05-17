<?php

namespace App\Console\Commands;

use App\Services\Marketing\GoogleAdsOfflineConversionService;
use Illuminate\Console\Command;

class GoogleAdsUploadOfflineConversions extends Command
{
    protected $signature = 'google-ads:upload-offline-conversions
        {--limit=50 : Maximum pending conversions to upload}
        {--validate-only : Validate payloads without importing conversions}';

    protected $description = 'Upload pending lead and quote offline conversions to Google Ads.';

    public function handle(GoogleAdsOfflineConversionService $service): int
    {
        $summary = $service->uploadPending(
            limit: (int) $this->option('limit'),
            validateOnly: (bool) $this->option('validate-only')
        );

        $this->table(
            ['checked', 'uploaded', 'failed', 'skipped'],
            [[
                $summary['checked'],
                $summary['uploaded'],
                $summary['failed'],
                $summary['skipped'],
            ]]
        );

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
