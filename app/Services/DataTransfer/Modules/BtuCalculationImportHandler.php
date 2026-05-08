<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\BtuCalculation;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;

class BtuCalculationImportHandler implements ImportHandlerInterface
{
    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $errors = [];

        // Validate required fields for create
        if ($mode === 'create' || $mode === 'upsert') {
            if (empty($row['area_m2'] ?? null)) {
                $errors[] = 'area_m2 là bắt buộc.';
            }
            if (empty($row['space_type'] ?? null)) {
                $errors[] = 'space_type là bắt buộc.';
            }
            if (empty($row['recommended_btu'] ?? null)) {
                $errors[] = 'recommended_btu là bắt buộc.';
            }
        }

        // Validate numeric fields
        foreach (['area_m2', 'ceiling_height'] as $numField) {
            if (!empty($row[$numField] ?? null) && !is_numeric($row[$numField])) {
                $errors[] = "{$numField} phải là số.";
            }
        }

        foreach (['recommended_btu', 'calculated_btu', 'people_count', 'cooling_w_per_m2'] as $intField) {
            if (!empty($row[$intField] ?? null) && !is_numeric($row[$intField])) {
                $errors[] = "{$intField} phải là số nguyên.";
            }
        }

        // Email validation
        if (!empty($row['email'] ?? null) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ: {$row['email']}";
        }

        // Phone validation
        if (!empty($row['phone'] ?? null)) {
            $phone = preg_replace('/\s+/', '', $row['phone']);
            if (!preg_match('/^(0|\+84|84)\d{9,10}$/', $phone)) {
                $errors[] = "Số điện thoại không hợp lệ: {$row['phone']}";
            }
        }

        // Validate JSON fields
        if (!empty($row['matched_product_ids'] ?? null) && is_string($row['matched_product_ids'])) {
            json_decode($row['matched_product_ids']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "matched_product_ids JSON không hợp lệ.";
            }
        }

        return $errors;
    }

    public function findExisting(array $row, string $matchingKey): mixed
    {
        return match ($matchingKey) {
            'id' => !empty($row['id']) ? BtuCalculation::find($row['id']) : null,
            default => null,
        };
    }

    public function importRow(array $row, string $mode, string $matchingKey): string
    {
        $data = $this->prepareData($row);
        $existing = $this->findExisting($row, $matchingKey);

        if ($mode === 'update') {
            if (!$existing) return 'skipped';
            $existing->update($data);
            return 'updated';
        }

        if ($mode === 'upsert') {
            if ($existing) {
                $existing->update($data);
                return 'updated';
            }
        }

        BtuCalculation::create($data);
        return 'created';
    }

    protected function prepareData(array $row): array
    {
        $data = [];
        $fillableFields = [
            'area_m2', 'ceiling_height', 'space_type', 'people_count',
            'direct_sunlight', 'heat_equipment', 'priority',
            'recommended_btu', 'calculated_btu', 'cooling_w_per_m2',
            'matched_product_ids',
            'full_name', 'phone', 'email', 'note',
            'source_page', 'ip_address',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $row[$field];
            }
        }

        // Parse JSON fields
        if (!empty($row['matched_product_ids']) && is_string($row['matched_product_ids'])) {
            $decoded = json_decode($row['matched_product_ids'], true);
            if ($decoded !== null) {
                $data['matched_product_ids'] = $decoded;
            }
        }

        // Parse boolean fields
        foreach (['direct_sunlight', 'heat_equipment'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        // Parse numeric fields
        foreach (['recommended_btu', 'calculated_btu', 'people_count', 'cooling_w_per_m2'] as $intField) {
            if (isset($data[$intField])) {
                $data[$intField] = is_numeric($data[$intField]) ? (int) $data[$intField] : null;
            }
        }

        return $data;
    }
}
