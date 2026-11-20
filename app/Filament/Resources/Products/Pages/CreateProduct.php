<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $category = \App\Models\ProductCategory::find($data['product_category_id'] ?? null);
        $aliases = $category ? app(\App\Services\Catalog\CategoryTechnicalSchemaService::class)->aliasMap($category) : [];
        $rows = app(\App\Services\Product\ProductTechnicalSpecWriter::class)->mergeManualRows([], $data['manual_extended_specs'] ?? [], $aliases);
        unset($data['manual_extended_specs']);
        foreach (['phase', 'frequency'] as $key) {
            if (filled($data['technical_'.$key] ?? null)) {
                $rows[] = ['key' => $key, 'value' => $data['technical_'.$key], 'source_section' => 'MANUAL_OVERRIDE'];
            }
            unset($data['technical_'.$key]);
        }
        $data['specs_json'] = $rows ?: null;
        return $data;
    }

    protected static string $resource = ProductResource::class;
}
