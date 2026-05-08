<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /* ── Lead types ── */
    public const TYPE_PRODUCT      = 'product';
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_GENERAL      = 'general';

    /* ── Intent scores ── */
    public const SCORE_PRODUCT      = 100;
    public const SCORE_CONSULTATION = 70;
    public const SCORE_GENERAL      = 40;

    protected function casts(): array
    {
        return [
            'status'       => LeadStatus::class,
            'intent_score' => 'integer',
            'capacity_btu' => 'integer',
        ];
    }

    /* ── Relationships ── */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'interested_product_id');
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /* ── Static helpers ── */

    public static function leadTypeLabels(): array
    {
        return [
            self::TYPE_PRODUCT      => 'Sản phẩm',
            self::TYPE_CONSULTATION => 'Tư vấn',
            self::TYPE_GENERAL      => 'Chung',
        ];
    }

    public static function leadTypeColors(): array
    {
        return [
            self::TYPE_PRODUCT      => 'danger',
            self::TYPE_CONSULTATION => 'warning',
            self::TYPE_GENERAL      => 'info',
        ];
    }

    /**
     * Create a product lead from a product model.
     */
    public static function createProductLead(array $contact, Product $product, array $extra = []): self
    {
        return static::create(array_merge([
            'lead_type'             => self::TYPE_PRODUCT,
            'intent_score'          => self::SCORE_PRODUCT,
            'interested_product_id' => $product->id,
            'product_name'          => $product->name,
            'product_sku'           => $product->sku,
            'product_url'           => route('product.show', $product->slug),
            'brand_name'            => $product->brand?->name,
            'category_name'         => $product->category?->name,
            'capacity_btu'          => $product->btu,
        ], $contact, $extra));
    }

    /**
     * Create a consultation lead (BTU calculator / tư vấn).
     */
    public static function createConsultationLead(array $contact, array $extra = []): self
    {
        return static::create(array_merge([
            'lead_type'    => self::TYPE_CONSULTATION,
            'intent_score' => self::SCORE_CONSULTATION,
        ], $contact, $extra));
    }

    /**
     * Create a general lead (homepage, CTA, landing page).
     */
    public static function createGeneralLead(array $contact, array $extra = []): self
    {
        return static::create(array_merge([
            'lead_type'    => self::TYPE_GENERAL,
            'intent_score' => self::SCORE_GENERAL,
        ], $contact, $extra));
    }
}
