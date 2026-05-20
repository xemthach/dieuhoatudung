<?php

namespace App\Services\Bulk;

use Illuminate\Database\Eloquent\Builder;

class BulkSelectionResult
{
    public function __construct(
        public readonly ?string $scope,
        public readonly array $ids,
        public readonly Builder $query,
        public readonly array $filters,
        public readonly int $total_count,
        public readonly int $selected_count,
        public readonly int $current_page_count,
        public readonly int $filter_count,
        public readonly string $summary_text,
        public readonly bool $is_valid,
        public readonly array $errors = [],
    ) {}

    public function firstIds(int $limit = 5): array
    {
        return array_slice($this->ids, 0, $limit);
    }
}
