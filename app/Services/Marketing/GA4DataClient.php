<?php

namespace App\Services\Marketing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GA4DataClient
{
    public function __construct(private ?string $accessToken = null) {}

    public function runReport(string $propertyId, array $metrics, array $dimensions = [], array $dateRanges = [['startDate' => '28daysAgo', 'endDate' => 'today']]): array
    {
        return $this->http()
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                'dateRanges' => $dateRanges,
                'metrics' => array_map(fn (string $name) => ['name' => $name], $metrics),
                'dimensions' => array_map(fn (string $name) => ['name' => $name], $dimensions),
            ])
            ->throw()
            ->json();
    }

    protected function http(): PendingRequest
    {
        $token = $this->accessToken ?: setting('integrations.ga4_access_token', config('marketing_integrations.ga4.access_token'));

        if (blank($token)) {
            throw new RuntimeException('ga4_access_token_missing');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
