<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportGovernanceAudit extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['changed_at' => 'datetime', 'context_json' => 'array']; }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
