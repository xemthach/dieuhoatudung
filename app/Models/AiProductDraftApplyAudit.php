<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProductDraftApplyAudit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields_applied' => 'array', 'approved_at' => 'datetime'];
    }
}
