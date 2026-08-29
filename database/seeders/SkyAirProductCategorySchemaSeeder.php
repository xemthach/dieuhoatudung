<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Support\Catalog\SkyAirTechnicalSchema;
use Illuminate\Database\Seeder;
use RuntimeException;

class SkyAirProductCategorySchemaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SkyAirTechnicalSchema::CATEGORIES as $type => $definition) {
            $category = ProductCategory::query()->where('name', $definition['name'])->first();
            if (! $category) {
                throw new RuntimeException("SkyAir category is missing: {$definition['name']}");
            }

            $targetVersion = SkyAirTechnicalSchema::version($type);
            if (
                $category->hasTechnicalSchema()
                && ! in_array($category->technical_schema_version, ['v1', $targetVersion], true)
            ) {
                throw new RuntimeException("Refusing to overwrite unexpected active schema for {$category->name}.");
            }

            $category->forceFill([
                'technical_schema_status' => 'active',
                'technical_schema_version' => $targetVersion,
                'technical_schema_json' => SkyAirTechnicalSchema::schema($type),
                'technical_schema_locked_at' => $category->technical_schema_locked_at ?? now(),
                'technical_schema_notes' => 'Category-specific SkyAir 2026 schema. Features, controllers and accessories remain in compatibility artifacts and are not flattened into boolean Product specs.',
            ])->save();
        }
    }
}
