<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Str;

class AIProductSeoScorer
{
    public function score(Product $product, array $warnings = []): array
    {
        $score = 0;
        $details = [];
        $content = (string) ($product->long_description ?? '');
        $plainContent = $this->plainText($content);
        $wordCount = $this->wordCount($content);
        $seoTitleLength = mb_strlen((string) ($product->seo_title ?? ''));
        $metaLength = mb_strlen((string) ($product->seo_description ?? ''));

        $this->add($score, $details, 'excerpt', filled($product->short_description), 10);
        $this->add($score, $details, 'content_800_words', $wordCount >= 800, 15, ['words' => $wordCount]);
        $this->add($score, $details, 'h2_h3', $this->hasHeadingStructure($content), 10);
        $this->add($score, $details, 'keyword_model', $this->containsProductKeyword($product, $plainContent), 10);
        $this->add($score, $details, 'seo_title_length', $seoTitleLength >= 50 && $seoTitleLength <= 65, 10, ['length' => $seoTitleLength]);
        $this->add($score, $details, 'meta_description_length', $metaLength >= 140 && $metaLength <= 160, 10, ['length' => $metaLength]);
        $this->add($score, $details, 'og_metadata', filled($product->og_title) && filled($product->og_description), 5);
        $this->add($score, $details, 'faq', $product->relationLoaded('faqs') ? $product->faqs->isNotEmpty() : $product->faqs()->exists(), 10);
        $this->add($score, $details, 'tags', $product->relationLoaded('tags') ? $product->tags->isNotEmpty() : $product->tags()->exists(), 5);
        $this->add($score, $details, 'internal_links', $this->hasInternalLinks($product), 5);
        $this->add($score, $details, 'no_duplicate_warning', ! in_array('duplicate_content_risk', $warnings, true), 10);

        return [
            'score' => min(100, $score),
            'details' => $details,
            'warnings' => $this->auditWarnings($product, $warnings),
        ];
    }

    public function auditWarnings(Product $product, array $warnings = []): array
    {
        $warnings = array_values(array_unique($warnings));
        $wordCount = $this->wordCount((string) ($product->long_description ?? ''));

        if (blank($product->short_description) || blank($product->long_description)) {
            $warnings[] = 'missing_content';
        }

        if ($wordCount > 0 && $wordCount < 800) {
            $warnings[] = 'content_too_short';
        }

        if (! $this->hasHeadingStructure((string) ($product->long_description ?? ''))) {
            $warnings[] = 'missing_h2_h3';
        }

        if (blank($product->seo_title) || blank($product->seo_description)) {
            $warnings[] = 'missing_seo';
        }

        if (blank($product->google_product_category) || blank($product->product_type) || blank($product->merchant_title) || blank($product->merchant_description)) {
            $warnings[] = 'missing_merchant';
        }

        if (! ($product->relationLoaded('faqs') ? $product->faqs->isNotEmpty() : $product->faqs()->exists())) {
            $warnings[] = 'missing_faq';
        }

        if (! $this->hasTechnicalData($product)) {
            $warnings[] = 'missing_technical_data';
        }

        return array_values(array_unique($warnings));
    }

    public function wordCount(string $html): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $this->plainText($html), $matches);

        return count($matches[0] ?? []);
    }

    private function add(int &$score, array &$details, string $key, bool $passed, int $points, array $meta = []): void
    {
        if ($passed) {
            $score += $points;
        }

        $details[$key] = array_merge([
            'passed' => $passed,
            'points' => $passed ? $points : 0,
            'max' => $points,
        ], $meta);
    }

    private function hasHeadingStructure(string $html): bool
    {
        $lower = Str::lower($html);

        return str_contains($lower, '<h2') && str_contains($lower, '<h3');
    }

    private function containsProductKeyword(Product $product, string $plainContent): bool
    {
        $haystack = Str::ascii(Str::lower($plainContent.' '.$product->seo_title.' '.$product->seo_description));
        $needles = array_filter([
            $product->model_code,
            $product->sku,
            $product->brand?->name,
            $product->btu ? $product->btu.' btu' : null,
        ]);

        foreach ($needles as $needle) {
            if (str_contains($haystack, Str::ascii(Str::lower((string) $needle)))) {
                return true;
            }
        }

        return str_contains($haystack, Str::ascii(Str::lower(Str::words($product->name, 4, ''))));
    }

    private function hasInternalLinks(Product $product): bool
    {
        return ($product->relationLoaded('relatedProducts') ? $product->relatedProducts->isNotEmpty() : $product->relatedProducts()->exists())
            || ($product->relationLoaded('posts') ? $product->posts->isNotEmpty() : $product->posts()->exists())
            || str_contains((string) $product->long_description, '<a ');
    }

    private function hasTechnicalData(Product $product): bool
    {
        return filled($product->model_code)
            || filled($product->btu)
            || filled($product->capacity_kw)
            || filled($product->refrigerant_gas)
            || filled($product->voltage)
            || ! empty($product->specs_json);
    }

    private function plainText(string $html): string
    {
        return preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?: '';
    }
}
