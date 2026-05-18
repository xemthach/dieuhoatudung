<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $content = setting('robots.robots_content');
        
        if (!$content) {
            $disallow = ['/login'];

            if (setting('robots.robots_disallow_admin', true)) {
                $disallow[] = '/admin';
            }

            if (setting('robots.robots_disallow_search', true)) {
                $disallow[] = '/search';
                $disallow[] = '/tim-kiem';
            }

            if (setting('robots.robots_disallow_filter_urls', true)) {
                $disallow[] = '/*?sort=';
                $disallow[] = '/*?filter=';
            }

            $sitemapUrl = setting('seo.canonical_base_url', config('app.url')) . '/sitemap.xml';

            $lines = ["User-agent: *"];

            foreach (array_values(array_unique($disallow)) as $path) {
                $lines[] = "Disallow: {$path}";
            }

            $lines[] = "";
            $lines[] = "Allow: /";
            $lines[] = "";
            $lines[] = "Sitemap: {$sitemapUrl}";

            $content = implode("\n", $lines);
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
