<?php

namespace App\Services\DataTransfer\Contracts;

interface ImportHandlerInterface
{
    /**
     * Validate a single row. Return array of error messages (empty = valid).
     */
    public function validateRow(array $row, string $mode, string $matchingKey): array;

    /**
     * Find an existing record by matching key. Returns the model or null.
     */
    public function findExisting(array $row, string $matchingKey): mixed;

    /**
     * Import a single row. Returns 'created', 'updated', or 'skipped'.
     */
    public function importRow(array $row, string $mode, string $matchingKey): string;
}
