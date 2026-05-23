<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    use ValidatesProductCategorySchema;

    protected static string $resource = ProductCategoryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $schema = is_array($data['technical_schema_json'] ?? null) ? $data['technical_schema_json'] : [];
        $data['technical_schema_fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->validateTechnicalSchemaData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
