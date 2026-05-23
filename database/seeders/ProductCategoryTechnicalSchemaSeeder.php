<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Services\Catalog\CategoryTechnicalSchemaService;
use Illuminate\Database\Seeder;

class ProductCategoryTechnicalSchemaSeeder extends Seeder
{
    public function run(): void
    {
        ProductCategory::query()->orderBy('id')->each(function (ProductCategory $category): void {
            if (
                $category->hasTechnicalSchema()
                && ! $category->technicalSchemaHasIssues()
                && $this->hasBuilderMetadata($category)
                && ! in_array($category->technical_schema_version, ['floor-ceiling-v1', 'hvac-general-v1'], true)
            ) {
                return;
            }

            $schema = app(CategoryTechnicalSchemaService::class)->presetFor($category->name);

            $category->forceFill([
                'technical_schema_status' => 'active',
                'technical_schema_version' => $schema['version'] ?: 'v1',
                'technical_schema_notes' => "Schema kỹ thuật cho {$category->name}. Catalog/product mới là nguồn giá trị thông số; schema chỉ dùng để mapping, validate, hiển thị, compare và AI.",
                'technical_schema_json' => array_merge($schema, ['status' => 'active']),
            ])->save();
        });
    }

    private function hasBuilderMetadata(ProductCategory $category): bool
    {
        $first = $category->technicalSchemaFieldDefinitions()[0] ?? null;

        return is_array($first)
            && array_key_exists('visible_frontend', $first)
            && array_key_exists('visible_compare', $first)
            && array_key_exists('use_for_ai', $first)
            && array_key_exists('sort_order', $first);
    }
}
