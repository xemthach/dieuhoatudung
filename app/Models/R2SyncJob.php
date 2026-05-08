<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class R2SyncJob extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'dry_run' => 'boolean',
        'old_base_urls' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(R2SyncItem::class);
    }
}
