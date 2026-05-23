<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Services\Catalog\CategoryTechnicalSchemaService;
use Illuminate\Validation\ValidationException;

trait ValidatesProductCategorySchema
{
    protected function validateTechnicalSchemaData(array $data): array
    {
        $fields = $data['technical_schema_fields'] ?? data_get($data, 'technical_schema_json.fields', []);

        $schema = app(CategoryTechnicalSchemaService::class)->normalize(
            [
                'version' => (string) ($data['technical_schema_version'] ?? 'v1'),
                'status' => (string) ($data['technical_schema_status'] ?? 'draft'),
                'fields' => is_array($fields) ? $fields : [],
            ],
            (string) ($data['technical_schema_version'] ?? 'v1'),
            (string) ($data['technical_schema_status'] ?? 'draft'),
        );

        $errors = app(CategoryTechnicalSchemaService::class)->validate($schema);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'technical_schema_json' => implode("\n", $errors),
            ]);
        }

        $data['technical_schema_json'] = $schema;
        $data['technical_schema_version'] = $schema['version'];
        $data['technical_schema_status'] = $schema['status'];
        unset($data['technical_schema_fields']);

        return $data;
    }
}
