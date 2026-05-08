<?php

namespace App\Services\Schema;

/**
 * Schema.org JSON-LD generator service.
 * Generates structured data for Organization, WebSite, Product, Article, FAQ, BreadcrumbList.
 */
class SchemaService
{
    /**
     * Organization + WebSite schema for homepage.
     */
    public function organizationAndWebsite(): array
    {
        $siteName = setting('general.site_name', config('app.name', ''));
        $siteUrl = setting('seo.canonical_base_url', config('app.url'));
        $orgName = setting('schema_organization.organization_name', $siteName);
        $phone = setting('schema_organization.organization_phone', setting('contact.hotline', ''));
        $email = setting('schema_organization.organization_email', setting('contact.email', ''));
        $address = setting('schema_organization.organization_address', setting('general.company_address', ''));
        $logoPath = setting('branding.logo_image') ? media_url(setting('branding.logo_image')) : url('/images/logo.png');

        // Social links
        $sameAs = [];
        $sameAsRaw = setting('schema_organization.organization_same_as', '');
        if ($sameAsRaw) {
            $sameAs = array_filter(array_map('trim', explode("\n", $sameAsRaw)));
        }
        if (empty($sameAs)) {
            foreach (['facebook_link', 'youtube_link', 'tiktok_link'] as $key) {
                $url = setting("contact.{$key}", '');
                if ($url && $url !== '#') $sameAs[] = $url;
            }
        }

        $schemas = [];

        // Organization
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $siteUrl . '/#organization',
            'name' => $orgName,
            'url' => $siteUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $logoPath,
            ],
        ];
        if ($phone) $org['telephone'] = $phone;
        if ($email) $org['email'] = $email;
        if ($address) {
            $org['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => 'Hồ Chí Minh',
                'addressCountry' => 'VN',
            ];
        }
        if (!empty($sameAs)) $org['sameAs'] = $sameAs;
        $schemas[] = $org;

        // WebSite with SearchAction
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $siteUrl . '/#website',
            'name' => $siteName,
            'url' => $siteUrl,
            'publisher' => ['@id' => $siteUrl . '/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $siteUrl . '/san-pham?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return $schemas;
    }

    /**
     * LocalBusiness schema for contact page.
     */
    public function localBusiness(): array
    {
        $siteName = setting('general.site_name', config('app.name', ''));
        $siteUrl = setting('seo.canonical_base_url', config('app.url'));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $siteUrl . '/#business',
            'name' => setting('general.company_name', $siteName),
            'url' => $siteUrl,
            'telephone' => setting('contact.hotline', ''),
            'email' => setting('contact.email', ''),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => setting('contact.contact_address', setting('general.company_address', '')),
                'addressLocality' => 'Hồ Chí Minh',
                'addressCountry' => 'VN',
            ],
            'openingHours' => setting('general.working_hours', ''),
            'image' => setting('branding.logo_image') ? media_url(setting('branding.logo_image')) : url('/images/logo.png'),
        ];
    }

    /**
     * Product schema — fixed version (no @@, no price=0 issue).
     */
    public function product(object $product, ?array $ratingStats = null): array
    {
        $siteUrl = setting('seo.canonical_base_url', config('app.url'));
        $productUrl = route('product.show', $product->slug);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->short_description ?? $product->seo_description ?? '',
            'url' => $productUrl,
            'sku' => $product->sku ?? '',
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand?->name ?? '',
            ],
        ];

        // MPN from model_code
        if (!empty($product->model_code)) {
            $schema['mpn'] = $product->model_code;
        }

        // Image
        if (!empty($product->main_image)) {
            $schema['image'] = media_url($product->main_image);
        }

        // Additional images
        $gallery = $product->gallery_json ?? [];
        if (is_array($gallery) && count($gallery) > 0) {
            $schema['image'] = array_merge(
                [$schema['image'] ?? ''],
                array_map(fn($img) => media_url($img), $gallery)
            );
            $schema['image'] = array_filter($schema['image']);
        }

        // Offer — ONLY if price exists (fixes price=0 issue)
        $price = $product->sale_price ?? $product->regular_price;
        if ($price && $price > 0) {
            $offer = [
                '@type' => 'Offer',
                'priceCurrency' => 'VND',
                'price' => (string) $price,
                'url' => $productUrl,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => $siteUrl . '/#organization'],
            ];

            // Availability
            $stockMap = [
                'in_stock' => 'https://schema.org/InStock',
                'out_of_stock' => 'https://schema.org/OutOfStock',
                'pre_order' => 'https://schema.org/PreOrder',
                'contact' => 'https://schema.org/InStock',
            ];
            $stockValue = $product->stock_status?->value ?? $product->stock_status ?? 'in_stock';
            $offer['availability'] = $stockMap[$stockValue] ?? 'https://schema.org/InStock';

            $schema['offers'] = $offer;
        }

        // AggregateRating
        if ($ratingStats && ($ratingStats['total'] ?? 0) > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $ratingStats['average'],
                'reviewCount' => (string) $ratingStats['total'],
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $schema;
    }

    /**
     * BreadcrumbList schema.
     */
    public function breadcrumbs(array $items): array
    {
        $listItems = [];
        foreach ($items as $i => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['label'] ?? $item['name'] ?? '',
            ];
            if (!empty($item['url'])) {
                $entry['item'] = $item['url'];
            }
            $listItems[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }

    /**
     * FAQPage schema.
     */
    public function faqPage(iterable $faqs): array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $question = $faq->question ?? $faq->title ?? '';
            $answer = $faq->answer ?? $faq->content ?? '';
            if (!$question || !$answer) continue;

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($answer),
                ],
            ];
        }

        if (empty($entities)) return [];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Render a schema array as JSON-LD script tag.
     */
    public static function toScript(array $schema): string
    {
        if (empty($schema)) return '';
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }
}
