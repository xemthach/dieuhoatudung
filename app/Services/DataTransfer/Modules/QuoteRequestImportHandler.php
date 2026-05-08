<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\QuoteRequest;
use App\Models\Product;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;

class QuoteRequestImportHandler implements ImportHandlerInterface
{
    public function validateRow(array $row, string $mode, string $matchingKey): array
    {
        $errors = [];

        // Phone validation
        if (!empty($row['phone'] ?? null)) {
            $phone = preg_replace('/\s+/', '', $row['phone']);
            if (!preg_match('/^(0|\+84|84)\d{9,10}$/', $phone)) {
                $errors[] = "Số điện thoại không hợp lệ: {$row['phone']}";
            }
        }

        // Email validation
        if (!empty($row['email'] ?? null) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ: {$row['email']}";
        }

        // Validate product_id exists
        if (!empty($row['product_id'] ?? null) && is_numeric($row['product_id'])) {
            if (!Product::find($row['product_id'])) {
                $errors[] = "Product ID {$row['product_id']} không tồn tại.";
            }
        }

        // Validate numeric fields
        foreach (['area_m2', 'ceiling_height', 'estimated_volume_m3', 'pipe_distance_m'] as $numField) {
            if (!empty($row[$numField] ?? null) && !is_numeric($row[$numField])) {
                $errors[] = "{$numField} phải là số.";
            }
        }

        foreach (['preferred_btu', 'calculated_btu', 'number_of_rooms', 'number_of_people', 'intent_score'] as $intField) {
            if (!empty($row[$intField] ?? null) && !is_numeric($row[$intField])) {
                $errors[] = "{$intField} phải là số nguyên.";
            }
        }

        // Validate status
        if (!empty($row['status'] ?? null)) {
            $validStatuses = ['new', 'contacted', 'quoted', 'won', 'lost'];
            if (!in_array($row['status'], $validStatuses)) {
                $errors[] = "Trạng thái không hợp lệ: {$row['status']}. Giá trị hợp lệ: " . implode(', ', $validStatuses);
            }
        }

        // Validate JSON fields
        foreach (['recommended_product_ids', 'preferred_brands', 'selected_product_snapshot'] as $jsonField) {
            if (!empty($row[$jsonField] ?? null) && is_string($row[$jsonField])) {
                json_decode($row[$jsonField]);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "{$jsonField} JSON không hợp lệ.";
                }
            }
        }

        return $errors;
    }

    public function findExisting(array $row, string $matchingKey): mixed
    {
        return match ($matchingKey) {
            'phone' => !empty($row['phone'])
                ? QuoteRequest::where('phone', $row['phone'])
                    ->when(!empty($row['source_page']), fn ($q) => $q->where('source_page', $row['source_page']))
                    ->latest()->first()
                : null,
            'id' => !empty($row['id']) ? QuoteRequest::find($row['id']) : null,
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

        QuoteRequest::create($data);
        return 'created';
    }

    protected function prepareData(array $row): array
    {
        $data = [];
        $fillableFields = [
            'lead_type', 'intent_score', 'full_name', 'phone', 'email', 'address',
            'province_city', 'district', 'message', 'project_type',
            'area_m2', 'ceiling_height', 'estimated_volume_m3',
            'number_of_rooms', 'number_of_people', 'sun_exposure', 'insulation_quality',
            'glass_area', 'open_space', 'current_aircon_status',
            'preferred_btu', 'calculated_btu', 'suggested_capacity_range',
            'need_inverter', 'need_three_phase', 'power_supply',
            'installation_type', 'pipe_distance_m', 'outdoor_unit_location',
            'budget_range', 'installation_time', 'need_installation_service',
            'need_invoice', 'need_site_survey', 'payment_method',
            'preferred_contact_method', 'preferred_contact_time',
            'product_id', 'product_name', 'product_sku', 'product_model',
            'product_brand', 'product_category', 'product_capacity_btu', 'product_url',
            'source_page', 'utm_source', 'utm_medium', 'utm_campaign',
            'utm_term', 'utm_content', 'landing_page', 'referrer',
            'status', 'admin_note',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $row[$field];
            }
        }

        // Parse JSON fields
        foreach (['recommended_product_ids', 'preferred_brands', 'selected_product_snapshot'] as $jsonField) {
            if (!empty($row[$jsonField]) && is_string($row[$jsonField])) {
                $decoded = json_decode($row[$jsonField], true);
                if ($decoded !== null) {
                    $data[$jsonField] = $decoded;
                }
            }
        }

        // Parse boolean fields
        foreach (['need_inverter', 'need_three_phase', 'open_space', 'need_invoice', 'need_site_survey'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // Parse numeric fields
        foreach (['intent_score', 'preferred_btu', 'calculated_btu', 'number_of_rooms', 'number_of_people', 'product_capacity_btu'] as $intField) {
            if (isset($data[$intField])) {
                $data[$intField] = is_numeric($data[$intField]) ? (int) $data[$intField] : null;
            }
        }

        return $data;
    }
}
