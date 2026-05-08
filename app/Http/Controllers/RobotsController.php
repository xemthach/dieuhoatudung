<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $content = setting('robots.robots_content');
        
        if (!$content) {
            $disallow  = config('seo.robots.disallow', ['/admin']);
            $sitemapUrl = setting('seo.canonical_base_url', config('app.url')) . '/sitemap.xml';

            $lines = ["User-agent: *"];

            foreach ($disallow as $path) {
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
