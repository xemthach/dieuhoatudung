<?php

namespace App\Services\Marketing;

use App\Models\GoogleAdsConversionImport;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class GoogleAdsOfflineConversionService
{
    public function __construct(private GoogleAdsConversionUploadClient $client) {}

    public function recordQuoteRequest(QuoteRequest $quote, ?Request $request = null, string $eventName = 'submit_quote'): ?GoogleAdsConversionImport
    {
        $gclid = $quote->gclid ?: $request?->input('gclid');
        $gbraid = $quote->gbraid ?: $request?->input('gbraid');
        $wbraid = $quote->wbraid ?: $request?->input('wbraid');
        $identifiers = $this->userIdentifiers($quote->email, $quote->phone);

        if (blank($gclid) && blank($gbraid) && blank($wbraid) && $identifiers === []) {
            return null;
        }

        return GoogleAdsConversionImport::updateOrCreate(
            [
                'source_type' => QuoteRequest::class,
                'source_id' => $quote->id,
                'event_name' => $eventName,
            ],
            [
                'status' => 'pending',
                'failed_reason' => null,
                'last_error_message' => null,
                'customer_id' => $this->customerId(),
                'conversion_action_resource_name' => $this->conversionActionResourceName(),
                'gclid' => $gclid,
                'gbraid' => $gbraid,
                'wbraid' => $wbraid,
                'conversion_date_time' => $quote->created_at ?: now(),
                'conversion_value' => $this->conversionValue($quote),
                'currency_code' => config('marketing_integrations.google_ads.default_currency_code', 'VND'),
                'order_id' => "quote-{$quote->id}",
                'user_identifiers_json' => $identifiers,
            ]
        );
    }

    public function uploadPending(int $limit = 50, bool $validateOnly = false): array
    {
        if (! Schema::hasTable('google_ads_conversion_imports')) {
            return [
                'checked' => 0,
                'uploaded' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'google_ads_conversion_imports_table_missing',
            ];
        }

        $imports = GoogleAdsConversionImport::query()
            ->whereIn('status', ['pending', 'failed'])
            ->whereNotNull('conversion_action_resource_name')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $summary = [
            'checked' => $imports->count(),
            'uploaded' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($imports as $import) {
            try {
                $payload = $this->buildClickConversion($import);

                if ($payload === null) {
                    $this->markFailed($import, 'missing_click_or_user_identifier', 'Conversion has no gclid, gbraid, wbraid, email, or phone identifier.');
                    $summary['skipped']++;

                    continue;
                }

                $response = $this->client->uploadClickConversions([$payload], $validateOnly);
                $hasPartialFailure = filled(data_get($response, 'partialFailureError.message'));

                $import->forceFill([
                    'status' => $hasPartialFailure ? 'failed' : ($validateOnly ? 'validated' : 'synced'),
                    'failed_reason' => $hasPartialFailure ? 'partial_failure' : null,
                    'last_error_message' => data_get($response, 'partialFailureError.message'),
                    'payload_json' => $payload,
                    'response_json' => $response,
                    'attempts' => $import->attempts + 1,
                    'last_attempted_at' => now(),
                    'uploaded_at' => $hasPartialFailure || $validateOnly ? null : now(),
                ])->save();

                $hasPartialFailure ? $summary['failed']++ : $summary['uploaded']++;
            } catch (\Throwable $exception) {
                $this->markFailed($import, $this->errorCode($exception), $exception->getMessage());
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function buildClickConversion(GoogleAdsConversionImport $import): ?array
    {
        if (blank($import->gclid) && blank($import->gbraid) && blank($import->wbraid) && empty($import->user_identifiers_json)) {
            return null;
        }

        $conversion = [
            'conversionAction' => $import->conversion_action_resource_name,
            'conversionDateTime' => $this->formatConversionDateTime($import->conversion_date_time ?: $import->created_at),
            'conversionValue' => (float) $import->conversion_value,
            'currencyCode' => $import->currency_code ?: 'VND',
            'orderId' => $import->order_id,
            'conversionEnvironment' => 'WEB',
        ];

        foreach (['gclid', 'gbraid', 'wbraid'] as $clickId) {
            if (filled($import->{$clickId})) {
                $conversion[$clickId] = $import->{$clickId};
                break;
            }
        }

        if (! empty($import->user_identifiers_json)) {
            $conversion['userIdentifiers'] = $import->user_identifiers_json;
        }

        return array_filter($conversion, fn ($value) => $value !== null && $value !== '');
    }

    public function userIdentifiers(?string $email, ?string $phone): array
    {
        $identifiers = [];

        if (filled($email)) {
            $identifiers[] = ['hashedEmail' => hash('sha256', mb_strtolower(trim($email)))];
        }

        $phone = $this->normalizeVietnamPhone($phone);
        if (filled($phone)) {
            $identifiers[] = ['hashedPhoneNumber' => hash('sha256', $phone)];
        }

        return array_slice($identifiers, 0, 5);
    }

    protected function conversionActionResourceName(): ?string
    {
        $resource = setting('integrations.google_ads_conversion_action_resource_name', config('marketing_integrations.google_ads.conversion_action_resource_name'));
        if (filled($resource)) {
            return $resource;
        }

        $customerId = $this->customerId();
        $actionId = setting('integrations.google_ads_conversion_action_id', config('marketing_integrations.google_ads.conversion_action_id'));

        if (blank($customerId) || blank($actionId)) {
            return null;
        }

        return 'customers/'.$customerId.'/conversionActions/'.preg_replace('/\D+/', '', (string) $actionId);
    }

    protected function customerId(): ?string
    {
        $customerId = setting('integrations.google_ads_customer_id', config('marketing_integrations.google_ads.customer_id'));

        return filled($customerId) ? preg_replace('/\D+/', '', (string) $customerId) : null;
    }

    protected function conversionValue(QuoteRequest $quote): float
    {
        if ($quote->product?->regular_price) {
            return (float) $quote->product->regular_price;
        }

        return (float) setting('tracking.google_ads_default_conversion_value', 0);
    }

    protected function formatConversionDateTime(Carbon|string|null $dateTime): string
    {
        return Carbon::parse($dateTime ?: now())->format('Y-m-d H:i:sP');
    }

    protected function normalizeVietnamPhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '84')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+84'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    protected function markFailed(GoogleAdsConversionImport $import, string $reason, string $message): void
    {
        $import->forceFill([
            'status' => 'failed',
            'failed_reason' => $reason,
            'last_error_message' => $message,
            'attempts' => $import->attempts + 1,
            'last_attempted_at' => now(),
        ])->save();

        Log::warning('Google Ads offline conversion import failed', [
            'conversion_import_id' => $import->id,
            'reason' => $reason,
        ]);
    }

    protected function errorCode(\Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'google_ads_upload_failed';
    }
}
