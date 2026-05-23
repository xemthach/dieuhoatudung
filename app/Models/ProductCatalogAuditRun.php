<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCatalogAuditRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'filters_json' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductCatalogAuditItem::class, 'audit_run_id');
    }
}
