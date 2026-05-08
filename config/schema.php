<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema.org JSON-LD Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Schema Types Configuration
    |--------------------------------------------------------------------------
    */

    'types' => [

        'organization' => [
            'enabled' => true,
            '@type' => 'Organization',
        ],

        'website' => [
            'enabled' => true,
            '@type' => 'WebSite',
        ],

        'product' => [
            'enabled' => true,
            '@type' => 'Product',
            'offer_type' => 'Offer',
            'currency' => 'VND',
            'availability_map' => [
                'in_stock' => 'https://schema.org/InStock',
                'out_of_stock' => 'https://schema.org/OutOfStock',
                'pre_order' => 'https://schema.org/PreOrder',
                'contact' => 'https://schema.org/InStock',
            ],
        ],

        'article' => [
            'enabled' => true,
            '@type' => 'Article',
        ],

        'blog_posting' => [
            'enabled' => true,
            '@type' => 'BlogPosting',
        ],

        'faq_page' => [
            'enabled' => true,
            '@type' => 'FAQPage',
        ],

        'breadcrumb' => [
            'enabled' => true,
            '@type' => 'BreadcrumbList',
        ],

        'aggregate_rating' => [
            'enabled' => false, // Enable when real review data exists
            '@type' => 'AggregateRating',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Publisher Info (for Article schema)
    |--------------------------------------------------------------------------
    */

    'publisher' => [
        'name' => function_exists('setting') ? setting('general.site_name', 'Điều Hòa Tủ Đứng') : 'Điều Hòa Tủ Đứng',
        'logo' => '/images/logo.png',
    ],

];
