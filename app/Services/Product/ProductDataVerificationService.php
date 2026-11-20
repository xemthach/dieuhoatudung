<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ProductDataVerificationService
{
    public const VERIFIED = 'VERIFIED';
    public const UNRESOLVED = 'UNRESOLVED';
    public const CONFLICT = 'CONFLICT';
    public const UNVERIFIED = 'UNVERIFIED';
    public const MISSING = 'MISSING';

    public function taxonomyStatus(Product $product): string
    {
        $status = strtoupper((string) $product->getAttribute('taxonomy_status'));

        return in_array($status, [self::VERIFIED, self::UNRESOLVED, self::CONFLICT], true)
            ? $status
            : self::UNRESOLVED;
    }

    public function marketingCapacityStatus(Product $product): string
    {
        $status = strtoupper((string) $product->getAttribute('marketing_capacity_status'));
        if (in_array($status, [self::VERIFIED, self::UNVERIFIED, self::MISSING, self::CONFLICT], true)) {
            return $status;
        }

        return $product->marketing_capacity_btu === null ? self::MISSING : self::UNVERIFIED;
    }

    /** Apply the non-bypassable state half of the public filter contract. */
    public function applyVerifiedPublicCapacityState(Builder $query): Builder
    {
        if (! Schema::hasColumns('products', ['taxonomy_status', 'marketing_capacity_status', 'marketing_capacity_btu'])) {
            // A partially deployed schema must expose no unverified capacity.
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('taxonomy_status', self::VERIFIED)
            ->where('marketing_capacity_status', self::VERIFIED)
            ->whereNotNull('marketing_capacity_btu')
            ->where('marketing_capacity_btu', '>', 0);
    }
}
