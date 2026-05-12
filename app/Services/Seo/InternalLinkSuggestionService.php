<?php

namespace App\Services\Seo;

use App\Models\CaseStudy;
use App\Models\InternalLinkSuggestion;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class InternalLinkSuggestionService
{
    // Score weights
    protected const SCORE_SHARED_TAG     = 30;
    protected const SCORE_KEYWORD_MATCH  = 25;
    protected const SCORE_SAME_BRAND     = 20;
    protected const SCORE_SAME_BTU       = 20;
    protected const SCORE_SAME_CATEGORY  = 15;
    protected const SCORE_TITLE_MATCH    = 10;
    protected const MIN_SCORE            = 10; // Only persist if above this

    /**
     * Generate and persist fresh suggestions for a given source model.
     * Skips if suggestions already exist and $force = false.
     */
    public function generateForModel(Model $source, bool $force = false): Collection
    {
        $sourceType = get_class($source);

        if (! $force) {
            $existing = InternalLinkSuggestion::forSource($sourceType, $source->id)->count();
            if ($existing > 0) {
                return InternalLinkSuggestion::forSource($sourceType, $source->id)->with('target')->get();
            }
        }

        // Delete old pending suggestions (keep approved/rejected)
        InternalLinkSuggestion::forSource($sourceType, $source->id)
            ->where('status', 'pending')
            ->delete();

        $candidates = $this->scoreCandidates($source);

        $persisted = collect();
        foreach ($candidates as $candidate) {
            if ($candidate['score'] < self::MIN_SCORE) continue;

            $suggestion = InternalLinkSuggestion::updateOrCreate(
                [
                    'source_type' => $sourceType,
                    'source_id'   => $source->id,
                    'target_type' => get_class($candidate['model']),
                    'target_id'   => $candidate['model']->id,
                ],
                [
                    'anchor_text' => $candidate['anchor_text'],
                    'reason'      => $candidate['reasons'],
                    'score'       => $candidate['score'],
                    'status'      => 'pending',
                ]
            );

            $persisted->push($suggestion);
        }

        return $persisted;
    }

    /**
     * Score all candidate targets relative to the source model.
     */
    protected function scoreCandidates(Model $source): array
    {
        $candidates = [];

        // Gather source attributes for matching
        $sourceTags     = $this->getTagIds($source);
        $sourceKeyword  = $this->getKeyword($source);
        $sourceBrand    = $this->getBrandId($source);
        $sourceBtu      = $this->getBtu($source);
        $sourceCat      = $this->getCategoryId($source);
        $sourceTitle    = $this->getTitle($source);

        $targets = $this->buildTargetPool($source);

        foreach ($targets as $target) {
            $score = 0;
            $reasons = [];

            // Shared tags
            $targetTags = $this->getTagIds($target);
            $sharedTags = array_intersect($sourceTags, $targetTags);
            if (count($sharedTags) > 0) {
                $pts = count($sharedTags) * self::SCORE_SHARED_TAG;
                $score += $pts;
                $reasons[] = count($sharedTags) . ' tag chung (+' . $pts . ')';
            }

            // Keyword match in title/content
            if ($sourceKeyword) {
                $targetTitle   = strtolower($this->getTitle($target));
                $targetContent = strtolower($this->getContent($target));
                $kw            = strtolower($sourceKeyword);
                if (str_contains($targetTitle, $kw) || str_contains($targetContent, $kw)) {
                    $score += self::SCORE_KEYWORD_MATCH;
                    $reasons[] = "Từ khóa khớp: \"{$sourceKeyword}\" (+". self::SCORE_KEYWORD_MATCH . ')';
                }
            }

            // Same brand
            $targetBrand = $this->getBrandId($target);
            if ($sourceBrand && $targetBrand && $sourceBrand === $targetBrand) {
                $score += self::SCORE_SAME_BRAND;
                $reasons[] = 'Cùng thương hiệu (+' . self::SCORE_SAME_BRAND . ')';
            }

            // BTU proximity (within 20% range)
            $targetBtu = $this->getBtu($target);
            if ($sourceBtu && $targetBtu) {
                $diff = abs($sourceBtu - $targetBtu) / $sourceBtu;
                if ($diff <= 0.20) {
                    $score += self::SCORE_SAME_BTU;
                    $reasons[] = "BTU tương đương ({$targetBtu}BTU) (+" . self::SCORE_SAME_BTU . ')';
                }
            }

            // Same category
            $targetCat = $this->getCategoryId($target);
            if ($sourceCat && $targetCat && $sourceCat === $targetCat) {
                $score += self::SCORE_SAME_CATEGORY;
                $reasons[] = 'Cùng danh mục (+' . self::SCORE_SAME_CATEGORY . ')';
            }

            // Title keyword overlap
            $titleWords = $this->getSignificantWords($sourceTitle);
            $targetTitleStr = $this->getTitle($target);
            foreach ($titleWords as $word) {
                if (mb_strlen($word) > 3 && str_contains(strtolower($targetTitleStr), strtolower($word))) {
                    $score += self::SCORE_TITLE_MATCH;
                    $reasons[] = "Tiêu đề có từ khóa: \"{$word}\" (+" . self::SCORE_TITLE_MATCH . ')';
                    break; // Only one bonus per title match
                }
            }

            if ($score > 0) {
                $candidates[] = [
                    'model'       => $target,
                    'score'       => min($score, 100),
                    'anchor_text' => $this->suggestAnchorText($target),
                    'reasons'     => implode('; ', $reasons),
                ];
            }
        }

        // Sort by score desc
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, 20); // Max 20 suggestions per source
    }

    /**
     * Build pool of target candidates (exclude self).
     */
    protected function buildTargetPool(Model $source): array
    {
        $targets = [];

        // Posts (only published)
        if (! $source instanceof Post) {
            $targets = array_merge($targets, Post::where('status', 'published')
                ->select(['id', 'title', 'slug', 'content', 'primary_keyword', 'post_category_id', 'author_id'])
                ->limit(200)
                ->get()
                ->all());
        }

        // Products (only active)
        if (! $source instanceof Product) {
            $targets = array_merge($targets, Product::where('is_active', true)
                ->select(['id', 'name', 'slug', 'short_description', 'long_description', 'brand_id', 'btu', 'product_category_id'])
                ->limit(200)
                ->get()
                ->all());
        }

        // Product Categories (indexable)
        if (! $source instanceof ProductCategory) {
            $targets = array_merge($targets, ProductCategory::where('is_indexable', true)
                ->select(['id', 'name', 'slug', 'intro', 'seo_title'])
                ->limit(50)
                ->get()
                ->all());
        }

        // Case Studies (published)
        if (! $source instanceof CaseStudy) {
            $targets = array_merge($targets, CaseStudy::where('status', 'published')
                ->select(['id', 'title', 'slug', 'problem', 'solution', 'project_type'])
                ->limit(100)
                ->get()
                ->all());
        }

        return $targets;
    }

    // ─── Attribute Extractors ────────────────────────────────────────────────

    protected function getTagIds(Model $model): array
    {
        if (! method_exists($model, 'tags')) return [];
        return $model->tags()->pluck('tags.id')->toArray();
    }

    protected function getKeyword(Model $model): ?string
    {
        return $model->primary_keyword ?? null;
    }

    protected function getBrandId(Model $model): ?int
    {
        return $model->brand_id ?? null;
    }

    protected function getBtu(Model $model): ?int
    {
        $btu = $model->btu ?? null;
        if (! $btu) return null;
        return (int) preg_replace('/[^0-9]/', '', (string) $btu);
    }

    protected function getCategoryId(Model $model): ?int
    {
        return $model->product_category_id ?? $model->post_category_id ?? null;
    }

    protected function getTitle(Model $model): string
    {
        return $model->title ?? $model->name ?? '';
    }

    protected function getContent(Model $model): string
    {
        return strip_tags(
            ($model->content ?? '') .
            ($model->long_description ?? '') .
            ($model->problem ?? '') .
            ($model->solution ?? '') .
            ($model->intro ?? '')
        );
    }

    protected function getSignificantWords(string $text): array
    {
        $stopWords = ['và', 'của', 'trong', 'có', 'là', 'để', 'tủ', 'đứng', 'cho', 'điều', 'hòa', 'the', 'and', 'for'];
        $words = preg_split('/\s+/', $text);
        return array_values(array_filter($words, fn ($w) => ! in_array(strtolower($w), $stopWords)));
    }

    protected function suggestAnchorText(Model $target): string
    {
        if ($target instanceof Product) {
            return $target->name;
        }
        if ($target instanceof Post) {
            return $target->title;
        }
        if ($target instanceof ProductCategory) {
            return $target->name;
        }
        if ($target instanceof CaseStudy) {
            return $target->title;
        }
        return $this->getTitle($target);
    }

    /**
     * Get approved suggestions for a source (for frontend rendering).
     */
    public function getApproved(string $sourceType, int $sourceId): Collection
    {
        return InternalLinkSuggestion::forSource($sourceType, $sourceId)
            ->approved()
            ->with('target')
            ->orderByDesc('score')
            ->get();
    }
}
