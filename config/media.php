<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk Configuration
    |--------------------------------------------------------------------------
    |
    | In local development, we use the 'public' disk.
    | In production, switch to 'r2' for Cloudflare R2.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Media Folders
    |--------------------------------------------------------------------------
    */

    'folders' => [
        'products' => 'media/products',
        'products_gallery' => 'media/products/gallery',
        'blog' => 'media/blog',
        'case_studies' => 'media/case-studies',
        'brands' => 'media/brands',
        'banners' => 'media/banners',
        'policies' => 'media/policies',
        'tmp' => 'media/tmp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Directory Aliases (backward compatibility)
    |--------------------------------------------------------------------------
    | Some forms reference config('media.directories.images').
    | This maps to the same folders as above.
    */

    'directories' => [
        'images' => 'media/blog',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Settings
    |--------------------------------------------------------------------------
    */

    'images' => [
        'max_upload_size' => 5120, // KB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
        'fallback' => '/images/placeholder.jpg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 Configuration
    |--------------------------------------------------------------------------
    */

    'r2' => [
        'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
        'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
        'bucket' => env('CLOUDFLARE_R2_BUCKET'),
        'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
        'url' => env('CLOUDFLARE_R2_URL'),
        'region' => 'auto',
    ],

];
