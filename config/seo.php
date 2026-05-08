<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SEO Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'title_suffix'    => ' | ' . (function_exists('setting') ? setting('general.site_name', 'Điều Hòa Tủ Đứng') : 'Điều Hòa Tủ Đứng'),
        'title_separator' => ' - ',
        'meta_description'=> 'Chuyên cung cấp điều hòa tủ đứng chính hãng Daikin, LG, Panasonic, Gree. Giá tốt nhất, lắp đặt miễn phí, bảo hành toàn quốc.',
        'robots'          => 'index,follow',
        // Không hardcode dev domain — lấy APP_URL từ .env (sẽ fail rõ ràng nếu chưa set).
        'canonical_base'  => env('APP_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Open Graph Defaults
    |--------------------------------------------------------------------------
    */

    'og' => [
        'site_name' => function_exists('setting') ? setting('general.site_name', 'Điều Hòa Tủ Đứng') : 'Điều Hòa Tủ Đứng',
        'type'      => 'website',
        'locale'    => 'vi_VN',
        'image'     => '/images/og-default.jpg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization Info (for Schema)
    |--------------------------------------------------------------------------
    */

    'organization' => [
        'name'  => function_exists('setting') ? setting('schema_organization.organization_name', 'Điều Hòa Tủ Đứng') : 'Điều Hòa Tủ Đứng',
        'url'   => env('APP_URL'),
        'logo'  => '/images/logo.png',
        'phone' => '',
        'email' => '',
        'address' => [
            'street'      => '',
            'city'        => 'Hồ Chí Minh',
            'region'      => 'Hồ Chí Minh',
            'postal_code' => '',
            'country'     => 'VN',
        ],
        'social' => [
            'facebook' => '',
            'youtube'  => '',
            'zalo'     => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Settings
    |--------------------------------------------------------------------------
    */

    'sitemap' => [
        'base_url'              => env('APP_URL'),
        'products_changefreq'   => 'weekly',
        'products_priority'     => '0.8',
        'posts_changefreq'      => 'weekly',
        'posts_priority'        => '0.7',
        'categories_changefreq' => 'weekly',
        'categories_priority'   => '0.6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Robots Settings
    |--------------------------------------------------------------------------
    */

    'robots' => [
        'disallow' => [
            '/admin',
            '/login',
            '/search',
            '/*?sort=',
            '/*?filter=',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Indexing Rules
    |--------------------------------------------------------------------------
    */

    'tags' => [
        'min_content_for_index' => 5,
        'default_robots'        => 'noindex,follow',
        'approved_robots'       => 'index,follow',
    ],

];
