<?php

namespace App\Services\Media;

use App\Models\Product;

final class ProductMediaResolver
{
    public function __construct(private readonly MediaDiskService $media) {}

    public function fallbackUrl(): string
    {
        $fallback = asset('images/placeholders/product-default.jpg');
        $configured = setting('product_detail.default_product_image');

        return filled($configured)
            ? ($this->media->getPublicUrl((string) $configured, $fallback) ?: $fallback)
            : $fallback;
    }

    /** @return array<int, array{url:string,path:string,alt:string,is_fallback:bool}> */
    public function gallery(Product $product): array
    {
        $resolved = [];
        $seen = [];

        foreach ($this->storedPaths($product) as $path) {
            $url = $this->media->getPublicUrl($path);
            if (blank($url)) {
                continue;
            }

            $key = mb_strtolower(rtrim((string) $url, '/'));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $resolved[] = [
                'url' => (string) $url,
                'path' => $path,
                'alt' => (string) $product->name,
                'is_fallback' => false,
            ];
        }

        if ($resolved !== []) {
            return $resolved;
        }

        return [[
            'url' => $this->fallbackUrl(),
            'path' => '',
            'alt' => (string) $product->name,
            'is_fallback' => true,
        ]];
    }

    public function mainUrl(Product $product): string
    {
        return $this->gallery($product)[0]['url'];
    }

    /** @return array<int, string> */
    private function storedPaths(Product $product): array
    {
        $paths = [];
        if (filled($product->main_image)) {
            $paths[] = (string) $product->main_image;
        }

        foreach ((array) ($product->gallery_json ?? []) as $path) {
            if (is_string($path) && filled($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
