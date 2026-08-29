<?php

namespace Database\Seeders;

use App\Enums\ProductCategoryType;
use App\Models\ProductCategory;
use App\Support\Catalog\WallMountedTechnicalSchema;
use Illuminate\Database\Seeder;
use RuntimeException;

class WallMountedProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $category = ProductCategory::withTrashed()
            ->where('slug', WallMountedTechnicalSchema::CATEGORY_SLUG)
            ->orWhere('name', WallMountedTechnicalSchema::CATEGORY_NAME)
            ->first();

        if ($category && filled($category->technical_schema_version)
            && $category->technical_schema_version !== WallMountedTechnicalSchema::VERSION
            && $category->hasTechnicalSchema()) {
            throw new RuntimeException('Existing wall-mounted category has a different active schema version; refusing to overwrite it.');
        }

        $category ??= new ProductCategory();

        $category->forceFill([
            'name' => WallMountedTechnicalSchema::CATEGORY_NAME,
            'slug' => WallMountedTechnicalSchema::CATEGORY_SLUG,
            'type' => ProductCategoryType::Main,
            // Keep the empty category out of public navigation until products are
            // deliberately imported and an operator enables publication.
            'is_active' => false,
            'is_indexable' => false,
            'robots' => 'noindex,follow',
            'technical_schema_status' => 'active',
            'technical_schema_version' => WallMountedTechnicalSchema::VERSION,
            'technical_schema_json' => WallMountedTechnicalSchema::schema(),
            'technical_schema_locked_at' => $category->technical_schema_locked_at ?? now(),
            'technical_schema_notes' => 'Schema treo tường dựa trên Daikin 2026 QA_SOURCE. Refrigerant type không được suy đoán; tính năng giữ ngoài Product specs cho đến khi có storage contract đa trạng thái.',
        ]);

        if ($category->trashed()) {
            $category->restore();
        }

        $category->save();
    }
}
