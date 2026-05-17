<?php

namespace App\Services\Marketing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleClient
{
    public function __construct(private ?string $accessToken = null) {}

    public function searchAnalytics(string $siteUrl, string $startDate, string $endDate, array $dimensions = ['query', 'page'], int $rowLimit = 100): array
    {
        return $this->http()
            ->post('https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => $rowLimit,
            ])
            ->throw()
            ->json();
    }

    public function inspectUrl(string $siteUrl, string $inspectionUrl): array
    {
        return $this->http()
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $inspectionUrl,
                'siteUrl' => $siteUrl,
            ])
            ->throw()
            ->json();
    }

    public function submitSitemap(string $siteUrl, string $sitemapUrl): bool
    {
        $this->http()
            ->put('https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/sitemaps/'.rawurlencode($sitemapUrl))
            ->throw();

        return true;
    }

    protected function http(): PendingRequest
    {
        $token = $this->accessToken ?: setting('integrations.google_search_console_access_token', config('marketing_integrations.google_search_console.access_token'));

        if (blank($token)) {
            throw new RuntimeException('google_search_console_access_token_missing');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
