<?php

return [
    'google_search_console' => [
        'access_token' => env('GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN'),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
        'scopes' => [
            'readonly' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'write' => 'https://www.googleapis.com/auth/webmasters',
        ],
    ],

    'ga4' => [
        'property_id' => env('GA4_PROPERTY_ID'),
        'access_token' => env('GA4_ACCESS_TOKEN'),
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
    ],

    'google_ads' => [
        'api_version' => env('GOOGLE_ADS_API_VERSION', 'v20'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'access_token' => env('GOOGLE_ADS_ACCESS_TOKEN'),
        'conversion_action_resource_name' => env('GOOGLE_ADS_CONVERSION_ACTION_RESOURCE_NAME'),
        'conversion_action_id' => env('GOOGLE_ADS_CONVERSION_ACTION_ID'),
        'default_currency_code' => env('GOOGLE_ADS_DEFAULT_CURRENCY_CODE', 'VND'),
    ],

    'bing_webmaster' => [
        'api_key' => env('BING_WEBMASTER_API_KEY'),
        'site_url' => env('BING_WEBMASTER_SITE_URL'),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
        'key_location' => env('INDEXNOW_KEY_LOCATION'),
    ],
];
