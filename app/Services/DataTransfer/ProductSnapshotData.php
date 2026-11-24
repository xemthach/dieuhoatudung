<?php

namespace App\Services\DataTransfer;

/** Primitive snapshot decoding shared by transfer and restore policy handlers. */
final class ProductSnapshotData
{
    public static function prepare(array $row, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];
            if (in_array($field, ['specs_json', 'gallery_json', 'documents_json', 'taxonomy_evidence_json', 'marketing_capacity_evidence_json'], true) && is_string($value)) {
                $value = blank($value) ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            }
            if (in_array($field, ['inverter', 'is_featured', 'is_bestseller', 'is_new', 'is_active', 'schema_enabled', 'identifier_exists', 'price_includes_vat'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
            if (in_array($field, ['promotion_start_at', 'promotion_end_at', 'technical_specs_overridden_at'], true) && blank($value)) {
                $value = null;
            }

            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }
}
