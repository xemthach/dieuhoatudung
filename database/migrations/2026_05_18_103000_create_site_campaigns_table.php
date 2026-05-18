<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 40)->default('modal');
            $table->string('status', 20)->default('draft');
            $table->string('placement', 60)->default('all');
            $table->string('device', 20)->default('both');
            $table->json('content_json')->nullable();
            $table->json('design_json')->nullable();
            $table->json('targeting_json')->nullable();
            $table->json('schedule_json')->nullable();
            $table->json('frequency_json')->nullable();
            $table->json('tracking_json')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'placement', 'device', 'priority'], 'site_campaigns_match_idx');
            $table->index(['start_at', 'end_at'], 'site_campaigns_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_campaigns');
    }
};
