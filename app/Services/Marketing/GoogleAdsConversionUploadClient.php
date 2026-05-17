<?php

namespace App\Services\Marketing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAdsConversionUploadClient
{
    public function __construct(
        private ?string $accessToken = null,
        private ?string $developerToken = null,
        private ?string $customerId = null,
        private ?string $loginCustomerId = null,
        private ?string $apiVersion = null,
    ) {}

    public function uploadClickConversions(array $conversions, bool $validateOnly = false): array
    {
        $customerId = $this->customerId();
        $version = $this->apiVersion ?: config('marketing_integrations.google_ads.api_version', 'v20');

        return $this->http()
            ->post("https://googleads.googleapis.com/{$version}/customers/{$customerId}:uploadClickConversions", [
                'conversions' => $conversions,
                'partialFailure' => true,
                'validateOnly' => $validateOnly,
            ])
            ->throw()
            ->json() ?? [];
    }

    protected function http(): PendingRequest
    {
        $token = $this->accessToken ?: setting('integrations.google_ads_access_token', config('marketing_integrations.google_ads.access_token'));
        $developerToken = $this->developerToken ?: setting('integrations.google_ads_developer_token', config('marketing_integrations.google_ads.developer_token'));

        if (blank($token)) {
            throw new RuntimeException('google_ads_access_token_missing');
        }

        if (blank($developerToken)) {
            throw new RuntimeException('google_ads_developer_token_missing');
        }

        $headers = ['developer-token' => $developerToken];
        $loginCustomerId = $this->loginCustomerId ?: setting('integrations.google_ads_login_customer_id', config('marketing_integrations.google_ads.login_customer_id'));

        if (filled($loginCustomerId)) {
            $headers['login-customer-id'] = $this->normalizeCustomerId($loginCustomerId);
        }

        return Http::withToken($token)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500);
    }

    protected function customerId(): string
    {
        $customerId = $this->customerId ?: setting('integrations.google_ads_customer_id', config('marketing_integrations.google_ads.customer_id'));

        if (blank($customerId)) {
            throw new RuntimeException('google_ads_customer_id_missing');
        }

        return $this->normalizeCustomerId($customerId);
    }

    protected function normalizeCustomerId(string $customerId): string
    {
        return preg_replace('/\D+/', '', $customerId) ?: $customerId;
    }
}
