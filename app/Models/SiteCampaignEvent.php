<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCampaignEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_campaign_id',
        'event_type',
        'page_url',
        'entity_type',
        'entity_id',
        'device',
        'session_id',
        'ip_hash',
        'user_agent_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SiteCampaign::class, 'site_campaign_id');
    }
}
