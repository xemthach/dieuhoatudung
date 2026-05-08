<?php

namespace App\Services\Seo;

use App\Enums\PostStatus;
use App\Enums\TagStatus;
use App\Models\CaseStudy;
use App\Models\PolicyPage;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SeoAuditService
{
    public const CACHE_KEY = 'seo_audit_results';
    public const CACHE_TTL = 300; // 5 minutes

    /**
     * Run all audits and return flat collection of issues.
     */
    public function run(bool $fresh = false): Collection
    {
        if (! $fresh && cache()->has(self::CACHE_KEY)) {
            return collect(cache()->get(self::CACHE_KEY));
        }

        $issues = collect();

        $issues = $issues->merge($this->auditProducts());
        $issues = $issues->merge($this->auditPosts());
        $issues = $issues->merge($this->auditProductCategories());
        $issues = $issues->merge($this->auditPostCategories());
        $issues = $issues->merge($this->auditTags());
        $issues = $issues->merge($this->auditCaseStudies());
        $issues = $issues->merge($this->auditPolicyPages());
        $issues = $issues->merge($this->auditMerchantReadiness());

        $result = $issues->values()->all();
        cache()->put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return collect($result);
    }

    public function clearCache(): void
    {
        cache()->forget(self::CACHE_KEY);
    }

    protected function getAvailableColumns(string $table, array $desiredColumns): array
    {
        $actualColumns = Schema::getColumnListing($table);
        return array_intersect($desiredColumns, $actualColumns);
    }

    // ─────────────────────────────────────────────
    // PRODUCTS
    // ─────────────────────────────────────────────
    protected function auditProducts(): array
    {
        $issues = [];
        $seenSlugs = [];

        $table = (new Product())->getTable();
        $desiredColumns = [
            'id', 'name', 'slug', 'is_active', 'seo_title', 'seo_description',
            'main_image', 'regular_price', 'sale_price', 'brand_id', 'btu',
            'short_description', 'long_description', 'specs_json', 'schema_enabled',
            'robots',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $products = Product::select($columns)->withTrashed(false)->get();

        foreach ($products as $product) {
            $id    = $product->id;
            $name  = $product->name ?? "Product #{$id}";
            $slug  = in_array('slug', $columns) ? $product->slug : '';
            $edit  = route('filament.admin.resources.products.edit', $id);

            if (in_array('slug', $columns)) {
                if (empty($slug)) {
                    $issues[] = $this->issue('Product', $name, 'Slug trống', 'critical', $edit, null, $id);
                } elseif (in_array($slug, $seenSlugs)) {
                    $issues[] = $this->issue('Product', $name, "Slug trùng: /{$slug}", 'critical', $edit);
                } else {
                    $seenSlugs[] = $slug;
                }
            }

            if (in_array('seo_title', $columns) && empty($product->seo_title)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu SEO Title', 'critical', $edit);
            }

            if (in_array('seo_description', $columns) && empty($product->seo_description)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('main_image', $columns) && empty($product->main_image)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu ảnh chính', 'warning', $edit);
            }

            if (in_array('regular_price', $columns) && in_array('sale_price', $columns) && empty($product->regular_price) && empty($product->sale_price)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu cả regular_price và sale_price', 'warning', $edit);
            }

            if (in_array('brand_id', $columns) && empty($product->brand_id)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu Brand', 'warning', $edit);
            }

            if (in_array('btu', $columns) && empty($product->btu)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu thông số BTU', 'notice', $edit);
            }

            if (in_array('short_description', $columns) && empty($product->short_description)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu short_description', 'notice', $edit);
            }

            if (in_array('long_description', $columns) && empty($product->long_description)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu long_description', 'warning', $edit);
            }

            if (in_array('specs_json', $columns) && empty($product->specs_json)) {
                $issues[] = $this->issue('Product', $name, 'Thiếu thông số kỹ thuật', 'notice', $edit);
            }

            if (in_array('schema_enabled', $columns) && ! ($product->schema_enabled ?? true)) {
                $issues[] = $this->issue('Product', $name, 'Schema markup bị tắt', 'warning', $edit);
            }

            if (in_array('is_active', $columns) && in_array('robots', $columns) && $product->is_active && str_contains((string) $product->robots, 'noindex')) {
                $issues[] = $this->issue('Product', $name, 'Sản phẩm active nhưng robots=noindex', 'critical', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // POSTS
    // ─────────────────────────────────────────────
    protected function auditPosts(): array
    {
        $issues = [];

        $table = (new Post())->getTable();
        $desiredColumns = [
            'id', 'title', 'status', 'seo_title', 'seo_description', 'cover_image',
            'content', 'primary_keyword', 'published_at', 'robots',
            'post_category_id', 'author_id',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $posts = Post::select($columns)->get();

        foreach ($posts as $post) {
            $id   = $post->id;
            $name = $post->title ?? "Post #{$id}";
            $edit = route('filament.admin.resources.posts.edit', $id);

            if (in_array('seo_title', $columns) && empty($post->seo_title)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu SEO Title', 'critical', $edit);
            }

            if (in_array('seo_description', $columns) && empty($post->seo_description)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('cover_image', $columns) && empty($post->cover_image)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu Cover Image', 'warning', $edit);
            }

            if (in_array('content', $columns)) {
                $contentLength = mb_strlen(strip_tags((string) ($post->content ?? '')));
                if ($contentLength < 800) {
                    $issues[] = $this->issue('Post', $name, "Content quá ngắn ({$contentLength} ký tự, tối thiểu 800)", 'warning', $edit);
                }
            }

            if (in_array('primary_keyword', $columns) && empty($post->primary_keyword)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu Primary Keyword', 'notice', $edit);
            }

            if (
                in_array('status', $columns) && in_array('published_at', $columns) &&
                $post->status === PostStatus::Published
                && empty($post->published_at)
            ) {
                $issues[] = $this->issue('Post', $name, 'Status=published nhưng published_at trống', 'warning', $edit);
            }

            if (
                in_array('status', $columns) && in_array('robots', $columns) &&
                $post->status === PostStatus::Published
                && str_contains((string) $post->robots, 'noindex')
            ) {
                $issues[] = $this->issue('Post', $name, 'Bài đăng published nhưng robots=noindex', 'critical', $edit);
            }

            if (in_array('post_category_id', $columns) && empty($post->post_category_id)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu chuyên mục (Category)', 'notice', $edit);
            }

            if (in_array('author_id', $columns) && empty($post->author_id)) {
                $issues[] = $this->issue('Post', $name, 'Thiếu Author', 'notice', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // PRODUCT CATEGORIES
    // ─────────────────────────────────────────────
    protected function auditProductCategories(): array
    {
        $issues = [];

        $table = (new ProductCategory())->getTable();
        $desiredColumns = [
            'id', 'name', 'seo_title', 'seo_description', 'intro', 'content',
            'is_indexable', 'robots',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $categories = ProductCategory::select($columns)->get();

        foreach ($categories as $cat) {
            $id   = $cat->id;
            $name = $cat->name ?? "Product Category #{$id}";
            $edit = route('filament.admin.resources.product-categories.edit', $id);

            if (in_array('seo_title', $columns) && empty($cat->seo_title)) {
                $issues[] = $this->issue('Product Category', $name, 'Thiếu SEO Title', 'critical', $edit);
            }

            if (in_array('seo_description', $columns) && empty($cat->seo_description)) {
                $issues[] = $this->issue('Product Category', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('intro', $columns) && empty($cat->intro)) {
                $issues[] = $this->issue('Product Category', $name, 'Thiếu Intro text', 'warning', $edit);
            }

            if (in_array('content', $columns) && empty($cat->content)) {
                $issues[] = $this->issue('Product Category', $name, 'Thiếu Content body', 'notice', $edit);
            }

            if (in_array('is_indexable', $columns) && in_array('robots', $columns) && $cat->is_indexable && str_contains((string) $cat->robots, 'noindex')) {
                $issues[] = $this->issue('Product Category', $name, 'is_indexable=true nhưng robots=noindex', 'critical', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // POST CATEGORIES
    // ─────────────────────────────────────────────
    protected function auditPostCategories(): array
    {
        $issues = [];

        $table = (new PostCategory())->getTable();
        $desiredColumns = [
            'id', 'name', 'seo_title', 'seo_description', 'intro', 'content', 'robots',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $categories = PostCategory::select($columns)->get();

        foreach ($categories as $cat) {
            $id   = $cat->id;
            $name = $cat->name ?? "Post Category #{$id}";
            $edit = route('filament.admin.resources.post-categories.edit', $id);

            if (in_array('seo_title', $columns) && empty($cat->seo_title)) {
                $issues[] = $this->issue('Post Category', $name, 'Thiếu SEO Title', 'critical', $edit);
            }

            if (in_array('seo_description', $columns) && empty($cat->seo_description)) {
                $issues[] = $this->issue('Post Category', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('intro', $columns) && empty($cat->intro)) {
                $issues[] = $this->issue('Post Category', $name, 'Thiếu Intro text', 'notice', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // TAGS
    // ─────────────────────────────────────────────
    protected function auditTags(): array
    {
        $issues = [];

        $table = (new Tag())->getTable();
        $desiredColumns = [
            'id', 'name', 'slug', 'status', 'is_indexable', 'intro', 'seo_title',
            'robots', 'min_content_required',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $tags = Tag::select($columns)->withCount('posts')->get();

        foreach ($tags as $tag) {
            $id   = $tag->id;
            $name = $tag->name ?? "Tag #{$id}";
            $edit = route('filament.admin.resources.tags.edit', $id);

            if (in_array('is_indexable', $columns) && in_array('intro', $columns) && $tag->is_indexable && empty($tag->intro)) {
                $issues[] = $this->issue('Tag', $name, 'is_indexable=true nhưng thiếu intro', 'warning', $edit);
            }

            $minContent = in_array('min_content_required', $columns) ? ($tag->min_content_required ?? 3) : 3;
            if (in_array('is_indexable', $columns) && $tag->is_indexable && ($tag->posts_count ?? 0) < $minContent) {
                $issues[] = $this->issue('Tag', $name, "Số bài liên quan ({$tag->posts_count}) dưới mức tối thiểu ({$minContent})", 'notice', $edit);
            }

            if (
                in_array('status', $columns) && in_array('robots', $columns) &&
                $tag->status === TagStatus::Candidate
                && ! str_contains((string) $tag->robots, 'noindex')
            ) {
                $issues[] = $this->issue('Tag', $name, 'Status=candidate nhưng không có robots noindex', 'warning', $edit);
            }

            if (
                in_array('status', $columns) && in_array('seo_title', $columns) &&
                $tag->status === TagStatus::Approved
                && empty($tag->seo_title)
            ) {
                $issues[] = $this->issue('Tag', $name, 'Tag approved nhưng thiếu SEO Title', 'warning', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // CASE STUDIES
    // ─────────────────────────────────────────────
    protected function auditCaseStudies(): array
    {
        $issues = [];

        $table = (new CaseStudy())->getTable();
        $desiredColumns = [
            'id', 'title', 'slug', 'status', 'seo_title', 'seo_description',
            'cover_image', 'problem', 'solution', 'robots',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $items = CaseStudy::select($columns)->get();

        foreach ($items as $item) {
            $id   = $item->id;
            $name = $item->title ?? "Case Study #{$id}";
            $edit = route('filament.admin.resources.case-studies.edit', $id);

            if (in_array('seo_title', $columns) && empty($item->seo_title)) {
                $issues[] = $this->issue('Case Study', $name, 'Thiếu SEO Title', 'critical', $edit);
            }

            if (in_array('seo_description', $columns) && empty($item->seo_description)) {
                $issues[] = $this->issue('Case Study', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('cover_image', $columns) && empty($item->cover_image)) {
                $issues[] = $this->issue('Case Study', $name, 'Thiếu ảnh bìa (cover_image)', 'warning', $edit);
            }

            if (in_array('problem', $columns) && empty($item->problem)) {
                $issues[] = $this->issue('Case Study', $name, 'Thiếu phần "Bài toán đặt ra"', 'notice', $edit);
            }

            if (in_array('solution', $columns) && empty($item->solution)) {
                $issues[] = $this->issue('Case Study', $name, 'Thiếu phần "Giải pháp thi công"', 'notice', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // POLICY PAGES
    // ─────────────────────────────────────────────
    protected function auditPolicyPages(): array
    {
        $issues = [];

        $table = (new PolicyPage())->getTable();
        $desiredColumns = [
            'id', 'title', 'seo_title', 'seo_description', 'content', 'is_active',
        ];
        $columns = $this->getAvailableColumns($table, $desiredColumns);

        $pages = PolicyPage::select($columns)->get();

        foreach ($pages as $page) {
            $id   = $page->id;
            $name = $page->title ?? "Policy Page #{$id}";
            $edit = route('filament.admin.resources.policy-pages.edit', $id);

            if (in_array('seo_title', $columns) && empty($page->seo_title)) {
                $issues[] = $this->issue('Policy Page', $name, 'Thiếu SEO Title', 'warning', $edit);
            }

            if (in_array('seo_description', $columns) && empty($page->seo_description)) {
                $issues[] = $this->issue('Policy Page', $name, 'Thiếu SEO Description', 'warning', $edit);
            }

            if (in_array('content', $columns) && empty($page->content)) {
                $issues[] = $this->issue('Policy Page', $name, 'Nội dung trang trống', 'critical', $edit);
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────
    protected function issue(
        string $entity,
        string $name,
        string $message,
        string $severity,
        string $editUrl,
        ?string $publicUrl = null,
        $id = null
    ): array {
        $suggestion = 'Cập nhật nội dung';
        $action = null;

        if (str_contains($message, 'SEO Title')) {
            $suggestion = 'Cần thêm tiêu đề SEO';
            $action = 'auto_generate_title';
        } elseif (str_contains($message, 'SEO Description')) {
            $suggestion = 'Cần thêm mô tả SEO';
            $action = 'auto_generate_description';
        } elseif (str_contains($message, 'thông số kỹ thuật')) {
            $suggestion = 'Thêm thông số kỹ thuật';
            $action = 'generate_specs';
        } elseif (str_contains($message, 'ảnh chính')) {
            $suggestion = 'Upload ảnh chính';
        }

        return [
            'entity'     => $entity,
            'id'         => $id,
            'name'       => $name,
            'message'    => $message,
            'severity'   => $severity,
            'edit_url'   => $editUrl,
            'public_url' => $publicUrl,
            'suggestion' => $suggestion,
            'action'     => $action,
        ];
    }

    // ─────────────────────────────────────────────
    // MERCHANT READINESS
    // ─────────────────────────────────────────────

    protected function auditMerchantReadiness(): Collection
    {
        $issues = collect();
        $products = Product::where('is_active', true)->with('brand')->get();

        foreach ($products as $product) {
            $editUrl = route('filament.admin.resources.products.edit', $product->id);
            $publicUrl = route('product.show', $product->slug);

            // No price → excluded from feed
            $price = $product->sale_price ?? $product->regular_price;
            if (!$price || $price <= 0) {
                $issues->push($this->issue(
                    'Merchant: Sản phẩm không có giá', $product->name, 'Product',
                    'critical', $editUrl, $publicUrl,
                    'SP sẽ bị loại khỏi Google Shopping feed. Thêm giá hoặc chuyển sang inactive.',
                    'Thêm regular_price hoặc sale_price'
                ));
            }

            // No image → excluded from feed
            if (empty($product->main_image)) {
                $issues->push($this->issue(
                    'Merchant: Sản phẩm không có hình ảnh', $product->name, 'Product',
                    'critical', $editUrl, $publicUrl,
                    'Google Merchant yêu cầu hình ảnh. SP sẽ bị loại khỏi feed.',
                    'Upload main_image'
                ));
            }

            // No brand
            if (!$product->brand_id) {
                $issues->push($this->issue(
                    'Merchant: Thiếu thương hiệu', $product->name, 'Product',
                    'high', $editUrl, $publicUrl,
                    'Google Merchant yêu cầu brand. Gán thương hiệu cho sản phẩm.',
                    'Chọn brand_id'
                ));
            }

            // No GTIN/MPN and identifier_exists = false
            if (empty($product->gtin) && empty($product->model_code) && !$product->identifier_exists) {
                $issues->push($this->issue(
                    'Merchant: Thiếu GTIN/MPN', $product->name, 'Product',
                    'medium', $editUrl, $publicUrl,
                    'Nên có GTIN hoặc MPN (model_code). Nếu không có, đảm bảo identifier_exists=false.',
                    'Thêm GTIN hoặc model_code, hoặc đánh dấu identifier_exists'
                ));
            }
        }

        return $issues;
    }
}
