<?php

namespace App\Services\DataTransfer;

use App\Models\Brand;
use App\Models\CatalogModel;
use App\Models\CatalogSource;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

final class ProductSystemRestoreContract
{
    public const FORMAT = 'PRODUCT_SYSTEM_RESTORE';

    public const VERSION = 2;

    public const METADATA_SHEET = '_SYSTEM_EXPORT';

    public const PAYLOAD_SHEET = '_SYSTEM_PAYLOAD';

    public const DATA_SHEET = 'Data';

    public const PAYLOAD_TOKEN_PREFIX = '@SYSTEM_PAYLOAD:';

    public const XLSX_CELL_SAFE_LENGTH = 30000;

    /** @return array<int, string> */
    public static function fields(int $version = self::VERSION): array
    {
        return [
            'id', 'name', 'slug', 'sku', 'model_code', 'brand_id', 'product_category_id',
            'catalog_source_id', 'catalog_model_id', 'catalog_match_status',
            'technical_specs_source', 'technical_specs_override_reason', 'technical_specs_overridden_at',
            'series', 'btu', 'marketing_capacity_btu', 'technical_capacity_btu',
            'technical_capacity_status', 'capacity_kw', 'hp', 'inverter', 'cooling_type',
            'voltage', 'refrigerant_gas', 'power_consumption', 'airflow', 'noise_level',
            'indoor_dimensions', 'outdoor_dimensions', 'weight', 'recommended_area',
            'regular_price', 'sale_price', 'discount_percent', 'promotion_start_at',
            'promotion_end_at', 'stock_status', 'short_description', 'long_description',
            'specs_json', 'warranty_info', 'installation_note', 'main_image', 'gallery_json',
            'video_url', 'documents_json', 'is_featured', 'is_bestseller', 'is_new',
            'is_active', 'sort_order', 'seo_title', 'seo_description', 'canonical_url',
            'robots', 'og_title', 'og_description', 'og_image', 'schema_enabled', 'condition',
            'gtin', 'identifier_exists', 'google_product_category', 'product_type',
            'merchant_title', 'merchant_description',
            'shipping_weight', 'shipping_label', 'custom_label_0', 'custom_label_1',
            'custom_label_2', 'custom_label_3', 'custom_label_4', 'price_includes_vat',
            ...($version === 1 ? [] : [
                'taxonomy_status', 'taxonomy_source', 'taxonomy_ledger_digest', 'taxonomy_evidence_json', 'taxonomy_verified_at',
                'marketing_capacity_status', 'marketing_capacity_source', 'marketing_capacity_mapping_digest',
                'marketing_capacity_evidence_json', 'marketing_capacity_verified_at', 'ai_status', 'ai_score', 'ai_last_run_at',
                'ai_error_message', 'ai_warning_count', 'ai_generated_at', 'created_at', 'updated_at', 'deleted_at',
            ]),
        ];
    }

    public static function columnsChecksum(array $fields): string
    {
        return hash('sha256', json_encode(array_values($fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function contentChecksum(array $fields, Collection $rows, int $version = self::VERSION): string
    {
        $canonical = $rows->map(fn (array $row): array => array_map(
            static fn ($value): string => $version === 1 && is_string($value) ? trim($value) : (string) ($value ?? ''),
            array_replace(array_fill_keys($fields, ''), $row),
        ))->values()->all();

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function metadata(array $fields, Collection $rows): array
    {
        return [
            'format' => self::FORMAT,
            'format_version' => (string) self::VERSION,
            'application_version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'product_count' => (string) $rows->count(),
            'id_restore_policy' => 'PRESERVE',
            'columns_sha256' => self::columnsChecksum($fields),
            'content_sha256' => self::contentChecksum($fields, $rows),
            'references_sha256' => self::referenceChecksum($rows, encoded: true),
        ];
    }

    public static function referenceChecksum(Collection $rows, bool $encoded = false): string
    {
        $references = [];
        foreach ([
            'brand_id' => Brand::withTrashed()->select('id', 'slug'),
            'product_category_id' => ProductCategory::withTrashed()->select('id', 'slug'),
            'catalog_source_id' => CatalogSource::query()->select('id', 'source_name', 'source_sha256', 'brand_id', 'category_id'),
            'catalog_model_id' => CatalogModel::query()->select('id', 'catalog_source_id', 'normalized_model', 'normalized_sku'),
        ] as $field => $query) {
            $ids = $rows->pluck($field)->map(fn ($value) => $encoded ? json_decode((string) $value, flags: JSON_THROW_ON_ERROR) : $value)
                ->filter(fn ($value) => filled($value))->unique()->sort(SORT_NUMERIC)->values();
            $references[$field] = [];
            foreach ($ids->chunk(500) as $chunk) {
                $matches = (clone $query)->whereKey($chunk->all())->get()->keyBy('id');
                foreach ($chunk as $id) {
                    $record = $matches->get($id);
                    if (! $record) {
                        throw new \InvalidArgumentException("SYSTEM RESTORE foreign key {$field} #{$id} is missing.");
                    }
                    $references[$field][] = array_map(fn ($value) => $value === null ? null : (string) $value, $record->getRawOriginal());
                }
            }
        }

        return hash('sha256', json_encode($references, JSON_THROW_ON_ERROR));
    }
}
