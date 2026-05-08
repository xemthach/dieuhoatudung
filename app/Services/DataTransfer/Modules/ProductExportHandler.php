<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Product;
use App\Services\DataTransfer\DataExportService;
use App\Services\DataTransfer\ModuleRegistry;

class ProductExportHandler
{
    /**
     * Export products with brand/category name resolution.
     */
    public static function enrichRow(array $row): array
    {
        // If brand_id is present, resolve to brand name
        if (isset($row['brand_id']) && is_numeric($row['brand_id'])) {
            $brand = \App\Models\Brand::find($row['brand_id']);
            $row['brand_name'] = $brand?->name ?? '';
        }

        // If product_category_id is present, resolve to category name
        if (isset($row['product_category_id']) && is_numeric($row['product_category_id'])) {
            $cat = \App\Models\ProductCategory::find($row['product_category_id']);
            $row['category_name'] = $cat?->name ?? '';
        }

        return $row;
    }
}
