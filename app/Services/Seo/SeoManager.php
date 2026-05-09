<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * Centralized SEO Manager — single source of truth for all page-level SEO data.
 *
 * Fallback chain: Explicit set → Record data → Setting defaults → Config fallback
 */
class SeoManager
{
    protected string $title = '';
    protected string $description = '';
    protected string $canonical = '';
    protected string $robots = '';
    protected string $ogTitle = '';
    protected string $ogDescription = '';
    protected string $ogImage = '';
    protected string $ogType = 'website';
    protected string $twitterCard = 'summary_large_image';
    protected array $breadcrumbs = [];
    protected array $extraMeta = [];

    /**
     * Quick setter for common page SEO from a model record.
     */
    public function fromRecord(object $record, array $overrides = []): static
    {
        $siteName = setting('general.site_name', config('app.name', ''));

        // Title
        $this->title = $overrides['title']
            ?? $record->seo_title
            ?? $record->name
            ?? $record->title
            ?? '';
        if ($this->title && !str_contains($this->title, $siteName) && !str_contains($this->title, '|')) {
            $this->title .= ' | ' . $siteName;
        }

        // Description
        $this->description = $overrides['description']
            ?? $record->seo_description
            ?? $record->short_description
            ?? Str::limit(strip_tags($record->content ?? $record->long_description ?? ''), 160)
            ?? '';

        // Canonical
        $this->canonical = $overrides['canonical'] ?? $record->canonical_url ?? '';

        // Robots
        $this->robots = $overrides['robots'] ?? $record->robots ?? setting('seo.default_robots', 'index,follow');

        // Open Graph
        $this->ogTitle = $overrides['og_title'] ?? $record->og_title ?? $this->title;
        $this->ogDescription = $overrides['og_description'] ?? $record->og_description ?? $this->description;

        if (!empty($overrides['og_image'])) {
            $this->ogImage = $overrides['og_image'];
        } elseif (!empty($record->og_image)) {
            $this->ogImage = media_url($record->og_image);
        } elseif (!empty($record->main_image)) {
            $this->ogImage = media_url($record->main_image);
        } elseif (!empty($record->cover_image)) {
            $this->ogImage = media_url($record->cover_image);
        }

        return $this;
    }

    /**
     * Set SEO for a static/listing page.
     */
    public function forPage(string $title, string $description = '', string $canonical = '', string $robots = ''): static
    {
        $siteName = setting('general.site_name', config('app.name', ''));

        $this->title = $title;
        if ($this->title && !str_contains($this->title, $siteName) && !str_contains($this->title, '|')) {
            $this->title .= ' | ' . $siteName;
        }

        $this->description = $description ?: setting('seo.default_meta_description', '');
        $this->canonical = $canonical;
        $this->robots = $robots ?: setting('seo.default_robots', 'index,follow');
        $this->ogTitle = $this->title;
        $this->ogDescription = $this->description;

        return $this;
    }

    /**
     * Set page to noindex.
     */
    public function noindex(): static
    {
        $this->robots = 'noindex,follow';
        return $this;
    }

    public function setCanonical(string $url): static { $this->canonical = $url; return $this; }
    public function setOgType(string $type): static { $this->ogType = $type; return $this; }
    public function setOgImage(string $url): static { $this->ogImage = $url; return $this; }
    public function setTwitterCard(string $card): static { $this->twitterCard = $card; return $this; }

    public function setBreadcrumbs(array $items): static
    {
        $this->breadcrumbs = $items;
        return $this;
    }

    public function addMeta(string $name, string $content): static
    {
        $this->extraMeta[$name] = $content;
        return $this;
    }

    // ─── Getters ───

    public function getTitle(): string
    {
        return $this->title ?: setting('seo.default_seo_title', config('app.name', ''));
    }

    public function getDescription(): string
    {
        return $this->description ?: setting('seo.default_meta_description', '');
    }

    public function getCanonical(): string
    {
        if ($this->canonical) return $this->canonical;
        $base = setting('seo.canonical_base_url', config('app.url'));
        return rtrim($base, '/') . '/' . ltrim(request()->path(), '/');
    }

    public function getRobots(): string
    {
        return $this->robots ?: setting('seo.default_robots', 'index,follow');
    }

    public function getOgTitle(): string { return $this->ogTitle ?: $this->getTitle(); }
    public function getOgDescription(): string { return $this->ogDescription ?: $this->getDescription(); }

    public function getOgImage(): string
    {
        if ($this->ogImage) return $this->ogImage;
        $default = setting('seo.default_og_image', config('seo.og.image', '/images/og-default.jpg'));
        return $default ? url($default) : '';
    }

    public function getOgType(): string { return $this->ogType; }
    public function getTwitterCard(): string { return $this->twitterCard; }
    public function getBreadcrumbs(): array { return $this->breadcrumbs; }
    public function getExtraMeta(): array { return $this->extraMeta; }

    /**
     * Export all SEO data as array (for views).
     */
    public function toArray(): array
    {
        return [
            'title'          => $this->getTitle(),
            'description'    => $this->getDescription(),
            'canonical'      => $this->getCanonical(),
            'robots'         => $this->getRobots(),
            'og_title'       => $this->getOgTitle(),
            'og_description' => $this->getOgDescription(),
            'og_image'       => $this->getOgImage(),
            'og_type'        => $this->getOgType(),
            'twitter_card'   => $this->getTwitterCard(),
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'extra_meta'     => $this->getExtraMeta(),
        ];
    }
}
