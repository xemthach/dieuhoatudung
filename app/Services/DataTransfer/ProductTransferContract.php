<?php

namespace App\Services\DataTransfer;

use Illuminate\Support\Collection;

/** Checksummed Product transfer between environments. */
final class ProductTransferContract
{
    public const FORMAT = 'PRODUCT_TRANSFER';

    public const VERSION = 2;

    public const METADATA_SHEET = '_PRODUCT_TRANSFER';

    public const PAYLOAD_SHEET = '_PRODUCT_TRANSFER_PAYLOAD';

    public static function fields(int $version = self::VERSION): array
    {
        return array_merge(ProductSystemRestoreContract::fields(1), [
            'source_brand_id', 'brand_slug', 'source_category_id', 'category_slug',
            'source_catalog_source_id', 'source_catalog_model_id',
            ...($version === 1 ? [] : [
                'catalog_source_sha256', 'catalog_source_brand_slug', 'catalog_source_category_slug',
                'catalog_normalized_model', 'catalog_normalized_sku',
            ]),
        ]);
    }

    public static function metadata(array $fields, Collection $rows, string $scope): array
    {
        return [
            'format' => self::FORMAT,
            'format_version' => (string) self::VERSION,
            'application_version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'source_environment' => app()->environment(),
            'generated_at' => now()->toIso8601String(),
            'product_count' => (string) $rows->count(),
            'scope' => $scope,
            'matching_policy' => 'UPSERT_BY_SKU_THEN_SLUG',
            'catalog_lineage_policy' => 'PRESERVE_BY_SHA256_AND_SCOPED_NORMALIZED_IDENTITY',
            'columns_sha256' => ProductSystemRestoreContract::columnsChecksum($fields),
            'content_sha256' => ProductSystemRestoreContract::contentChecksum($fields, $rows),
        ];
    }
}
