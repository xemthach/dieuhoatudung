<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_indexable' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class, $this->scheme === 'current' ? 'province_id' : 'legacy_province_id');
    }
}
