<?php

namespace App\Http\Controllers;

use App\Models\SiteCampaign;
use App\Models\SiteCampaignEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SiteCampaignEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('site_campaign_events')) {
            return response()->json(['ok' => false], 503);
        }

        $validated = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:site_campaigns,id'],
            'event_type' => ['required', 'string', 'in:impression,click_primary,click_secondary,close,conversion'],
            'page_url' => ['nullable', 'string', 'max:1000'],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'entity_id' => ['nullable', 'integer'],
            'device' => ['nullable', 'string', 'in:desktop,mobile'],
            'session_id' => ['nullable', 'string', 'max:120'],
        ]);

        $campaign = SiteCampaign::active()->whereKey($validated['campaign_id'])->first();
        if (! $campaign) {
            return response()->json(['ok' => false], 404);
        }

        SiteCampaignEvent::create([
            'site_campaign_id' => $campaign->id,
            'event_type' => $validated['event_type'],
            'page_url' => $validated['page_url'] ?? $request->fullUrl(),
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => $validated['entity_id'] ?? null,
            'device' => $validated['device'] ?? null,
            'session_id' => $validated['session_id'] ?? $request->session()->getId(),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', mb_substr($request->userAgent(), 0, 500)) : null,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
