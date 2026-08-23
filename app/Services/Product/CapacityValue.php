<?php

namespace App\Services\Product;

final readonly class CapacityValue
{
    public function __construct(
        public string $role,
        public int|float $value,
        public string $unit,
        public bool $sourceNative,
        public bool $derived,
        public ?array $provenance = null,
        public array $allowedClaimScopes = [],
    ) {}

    public function toArray(): array
    {
        return ['semantic_role' => $this->role, 'value' => $this->value, 'unit' => $this->unit, 'source_native' => $this->sourceNative, 'derived' => $this->derived, 'provenance' => $this->provenance, 'allowed_claim_scopes' => $this->allowedClaimScopes];
    }
}
