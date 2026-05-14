<?php

namespace App\Services\Merchant;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Google Merchant Center feed generator.
 * Generates RSS 2.0 / Atom feed in Google Shopping XML format.
 */
class MerchantFeedService
{
    /**
     * Generate the full Google Merchant XML feed.
     */
    public function generateXml(): string
    {
        $products = $this->getEligibleProducts();
        $siteName = setting('general.site_name', config('app.name', ''));
        $siteUrl = setting('seo.canonical_base_url', config('app.url'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'."\n";
        $xml .= "<channel>\n";
        $xml .= '  <title>'.$this->escape($siteName)."</title>\n";
        $xml .= "  <link>{$siteUrl}</link>\n";
        $xml .= '  <description>'.$this->escape($siteName.' - Product Feed')."</description>\n";

        foreach ($products as $product) {
            $xml .= $this->buildItem($product, $siteUrl);
        }

        $xml .= "</channel>\n</rss>";

        return $xml;
    }

    /**
     * Get products eligible for the merchant feed.
     * Excludes: inactive, no price, placeholder images.
     */
    protected function getEligibleProducts(): Collection
    {
        return Product::with(['brand', 'category'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('regular_price')->where('regular_price', '>', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('sale_price')->where('sale_price', '>', 0);
                    });
            })
            ->whereNotNull('main_image')
            ->where('main_image', '!=', '')
            ->get();
    }

    /**
     * Build a single <item> element.
     */
    protected function buildItem(Product $product, string $siteUrl): string
    {
        $price = $product->sale_price ?? $product->regular_price;
        $productUrl = route('product.show', $product->slug);
        $imageUrl = media_url($product->main_image);
        $condition = $product->condition ?? 'new';
        $availability = $this->mapAvailability($product->stock_status?->value ?? $product->stock_status ?? 'in_stock');

        $xml = "  <item>\n";
        $xml .= "    <g:id>{$product->id}</g:id>\n";
        $xml .= '    <g:title>'.$this->escape($product->merchant_title ?? $product->name)."</g:title>\n";
        $xml .= '    <g:description>'.$this->escape($product->merchant_description ?? $product->short_description ?? $product->seo_description ?? $product->name)."</g:description>\n";
        $xml .= "    <g:link>{$productUrl}</g:link>\n";
        $xml .= "    <g:image_link>{$imageUrl}</g:image_link>\n";

        // Additional images
        $gallery = $product->gallery_json ?? [];
        if (is_array($gallery)) {
            foreach (array_slice($gallery, 0, 10) as $img) {
                $xml .= '    <g:additional_image_link>'.media_url($img)."</g:additional_image_link>\n";
            }
        }

        $xml .= "    <g:condition>{$condition}</g:condition>\n";
        $xml .= "    <g:availability>{$availability}</g:availability>\n";
        $xml .= '    <g:price>'.number_format($price, 0, '.', '')." VND</g:price>\n";

        // Sale price
        if ($product->sale_price && $product->regular_price && $product->sale_price < $product->regular_price) {
            $xml .= '    <g:sale_price>'.number_format($product->sale_price, 0, '.', '')." VND</g:sale_price>\n";
        }

        // Brand
        if ($product->brand) {
            $xml .= '    <g:brand>'.$this->escape($product->brand->name)."</g:brand>\n";
        }

        // MPN (from model_code)
        if (! empty($product->model_code)) {
            $xml .= '    <g:mpn>'.$this->escape($product->model_code)."</g:mpn>\n";
        }

        // GTIN
        if (! empty($product->gtin)) {
            $xml .= '    <g:gtin>'.$this->escape($product->gtin)."</g:gtin>\n";
        }

        // Identifier exists
        $identifierExists = $product->identifier_exists || ! empty($product->gtin);
        $xml .= '    <g:identifier_exists>'.($identifierExists ? 'true' : 'false')."</g:identifier_exists>\n";

        // Google Product Category
        if (! empty($product->google_product_category)) {
            $xml .= '    <g:google_product_category>'.$this->escape($product->google_product_category)."</g:google_product_category>\n";
        } else {
            // Default: Home & Garden > Heating, Ventilation & Air Conditioning
            $xml .= "    <g:google_product_category>604</g:google_product_category>\n";
        }

        // Product type
        if (! empty($product->product_type)) {
            $xml .= '    <g:product_type>'.$this->escape($product->product_type)."</g:product_type>\n";
        } elseif ($product->category) {
            $xml .= '    <g:product_type>Điều Hòa Tủ Đứng > '.$this->escape($product->category->name)."</g:product_type>\n";
        }

        // Shipping weight
        if (! empty($product->shipping_weight)) {
            $xml .= '    <g:shipping_weight>'.$this->escape($product->shipping_weight)."</g:shipping_weight>\n";
        }

        // Custom labels
        for ($i = 0; $i <= 4; $i++) {
            $field = "custom_label_{$i}";
            if (! empty($product->$field)) {
                $xml .= "    <g:custom_label_{$i}>".$this->escape($product->$field)."</g:custom_label_{$i}>\n";
            }
        }

        $xml .= "  </item>\n";

        return $xml;
    }

    /**
     * Map stock status to Google Merchant availability.
     */
    protected function mapAvailability(string $status): string
    {
        return match ($status) {
            'in_stock' => 'in_stock',
            'out_of_stock' => 'out_of_stock',
            'pre_order' => 'preorder',
            'contact' => 'in_stock',
            default => 'in_stock',
        };
    }

    /**
     * Escape XML special characters.
     */
    protected function escape(string $text): string
    {
        return htmlspecialchars(strip_tags($text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get feed diagnostics.
     */
    public function getDiagnostics(): array
    {
        $total = Product::where('is_active', true)->count();
        $eligible = $this->getEligibleProducts()->count();
        $noPrice = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('regular_price')->orWhere('regular_price', 0);
            })
            ->where(function ($q) {
                $q->whereNull('sale_price')->orWhere('sale_price', 0);
            })
            ->count();
        $noImage = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('main_image')->orWhere('main_image', '');
            })
            ->count();
        $noBrand = Product::where('is_active', true)->whereNull('brand_id')->count();

        return [
            'total_active' => $total,
            'eligible_for_feed' => $eligible,
            'excluded_no_price' => $noPrice,
            'excluded_no_image' => $noImage,
            'missing_brand' => $noBrand,
        ];
    }
}
