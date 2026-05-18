<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_campaign_id')->constrained('site_campaigns')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('page_url', 1000)->nullable();
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('device', 20)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['site_campaign_id', 'event_type', 'created_at'], 'site_campaign_events_campaign_type_idx');
            $table->index(['entity_type', 'entity_id'], 'site_campaign_events_entity_idx');
            $table->index('session_id', 'site_campaign_events_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_campaign_events');
    }
};
