<?php

namespace App\Services\DataTransfer\Modules;

use App\Models\Lead;
use App\Services\DataTransfer\Contracts\ImportHandlerInterface;

class LeadImportHandler implements ImportHandlerInterface
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

        // Validate intent_score is numeric
        if (!empty($row['intent_score'] ?? null) && !is_numeric($row['intent_score'])) {
            $errors[] = 'intent_score phải là số.';
        }

        // Validate status
        if (!empty($row['status'] ?? null)) {
            $validStatuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];
            if (!in_array($row['status'], $validStatuses)) {
                $errors[] = "Trạng thái không hợp lệ: {$row['status']}. Giá trị hợp lệ: " . implode(', ', $validStatuses);
            }
        }

        return $errors;
    }

    public function findExisting(array $row, string $matchingKey): mixed
    {
        return match ($matchingKey) {
            'phone' => !empty($row['phone']) ? Lead::where('phone', $row['phone'])->latest()->first() : null,
            'id'    => !empty($row['id']) ? Lead::find($row['id']) : null,
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

        Lead::create($data);
        return 'created';
    }

    protected function prepareData(array $row): array
    {
        $data = [];
        $fillableFields = [
            'full_name', 'phone', 'email', 'lead_type', 'intent_score',
            'interested_product_id', 'product_name', 'product_sku', 'product_url',
            'brand_name', 'category_name', 'capacity_btu',
            'need_type', 'area', 'budget', 'usage_type', 'region',
            'message', 'source_page', 'status', 'admin_note',
            'quote_request_id',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== '') {
                $data[$field] = $row[$field];
            }
        }

        // Parse numeric fields
        foreach (['intent_score', 'capacity_btu', 'interested_product_id'] as $numField) {
            if (isset($data[$numField])) {
                $data[$numField] = is_numeric($data[$numField]) ? (int) $data[$numField] : null;
            }
        }

        return $data;
    }
}
