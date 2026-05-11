<?php

namespace App\Http\Controllers;

use App\Services\Sitemap\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private SitemapService $sitemap) {}

    public function index(): Response
    {
        return response($this->sitemap->buildIndex(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function products(): Response
    {
        return response($this->sitemap->buildProducts(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function categories(): Response
    {
        return response($this->sitemap->buildCategories(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function posts(): Response
    {
        return response($this->sitemap->buildPosts(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function staticPages(): Response
    {
        return response($this->sitemap->buildStatic(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function brands(): Response
    {
        return response($this->sitemap->buildBrands(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function caseStudies(): Response
    {
        return response($this->sitemap->buildCaseStudies(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
