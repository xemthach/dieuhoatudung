<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class IndexNowClient
{
    public function submitUrl(string $url): bool
    {
        $key = setting('integrations.indexnow_key', config('marketing_integrations.indexnow.key'));

        if (blank($key)) {
            throw new RuntimeException('indexnow_key_missing');
        }

        Http::acceptJson()
            ->timeout(15)
            ->retry(2, 500)
            ->get('https://api.indexnow.org/indexnow', [
                'url' => $url,
                'key' => $key,
                'keyLocation' => setting('integrations.indexnow_key_location', config('marketing_integrations.indexnow.key_location')),
            ])
            ->throw();

        return true;
    }
}
