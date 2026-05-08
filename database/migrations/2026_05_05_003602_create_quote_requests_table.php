<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();

            // Step 1
            $table->string('project_type', 50)->nullable();

            // Step 2
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->decimal('ceiling_height', 4, 2)->nullable();

            // Step 3
            $table->unsignedInteger('preferred_btu')->nullable();
            $table->boolean('need_inverter')->default(false);
            $table->boolean('need_three_phase')->default(false);
            $table->string('preferred_brand', 100)->nullable();
            $table->string('installation_time', 50)->nullable();

            // Step 4
            $table->string('budget_range', 50)->nullable();

            // Step 5 — contact
            $table->string('full_name', 100);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('message')->nullable();

            // Meta
            $table->string('source_page', 255)->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->json('recommended_product_ids')->nullable();

            // Admin
            $table->enum('status', ['new', 'contacted', 'quoted', 'won', 'lost'])->default('new');
            $table->text('admin_note')->nullable();

            // Tracking
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes for filters
            $table->index('status');
            $table->index('project_type');
            $table->index('budget_range');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
