<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BingWebmasterClient
{
    public function __construct(private ?string $apiKey = null) {}

    public function submitUrl(string $siteUrl, string $url): bool
    {
        $this->post('https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl', [
            'siteUrl' => $siteUrl,
            'url' => $url,
        ]);

        return true;
    }

    public function submitSitemap(string $siteUrl, string $sitemapUrl): bool
    {
        $this->post('https://ssl.bing.com/webmaster/api.svc/json/SubmitFeed', [
            'siteUrl' => $siteUrl,
            'feedUrl' => $sitemapUrl,
        ]);

        return true;
    }

    protected function post(string $url, array $payload): array
    {
        $key = $this->apiKey ?: setting('integrations.bing_webmaster_api_key', config('marketing_integrations.bing_webmaster.api_key'));

        if (blank($key)) {
            throw new RuntimeException('bing_webmaster_api_key_missing');
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($url.'?apikey='.rawurlencode($key), $payload)
            ->throw()
            ->json() ?? [];
    }
}
