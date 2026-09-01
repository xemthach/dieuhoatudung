<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_product_jobs', function (Blueprint $table): void {
            $table->timestamp('cancel_requested_at')->nullable()->after('finished_at');
            $table->foreignId('cancel_requested_by')->nullable()->after('cancel_requested_at')->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancel_requested_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
        });

        Schema::table('ai_product_job_items', function (Blueprint $table): void {
            $table->uuid('dispatch_uuid')->nullable()->after('idempotency_key')->unique('ai_product_item_dispatch_uuid_uq');
            $table->timestamp('cancel_requested_at')->nullable()->after('finished_at');
            $table->foreignId('cancel_requested_by')->nullable()->after('cancel_requested_at')->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancel_requested_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->index(['product_id', 'canonical_status'], 'ai_product_item_product_canonical_idx');
        });

        Schema::table('ai_product_drafts', function (Blueprint $table): void {
            $table->index(['product_id', 'approval_status', 'applied_at'], 'ai_product_draft_actionable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_drafts', function (Blueprint $table): void {
            $table->dropIndex('ai_product_draft_actionable_idx');
        });

        Schema::table('ai_product_job_items', function (Blueprint $table): void {
            $table->dropIndex('ai_product_item_product_canonical_idx');
            $table->dropUnique('ai_product_item_dispatch_uuid_uq');
            $table->dropConstrainedForeignId('cancel_requested_by');
            $table->dropColumn(['dispatch_uuid', 'cancel_requested_at', 'cancel_reason', 'cancelled_at']);
        });

        Schema::table('ai_product_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancel_requested_by');
            $table->dropColumn(['cancel_requested_at', 'cancel_reason', 'cancelled_at']);
        });
    }
};
