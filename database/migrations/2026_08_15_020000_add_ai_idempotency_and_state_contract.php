<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_product_jobs')) {
            Schema::table('ai_product_jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_product_jobs', 'canonical_status')) {
                    $table->string('canonical_status', 30)->default('QUEUED')->after('status');
                }
                if (! Schema::hasColumn('ai_product_jobs', 'status_reason')) {
                    $table->string('status_reason', 120)->nullable()->after('canonical_status');
                }
                if (! Schema::hasColumn('ai_product_jobs', 'state_changed_at')) {
                    $table->timestamp('state_changed_at')->nullable()->after('status_reason');
                }
                if (! Schema::hasColumn('ai_product_jobs', 'prompt_version')) {
                    $table->string('prompt_version', 100)->nullable()->after('config_json');
                }
            });

            $this->backfillLegacyState('ai_product_jobs');
        }

        if (Schema::hasTable('ai_product_job_items')) {
            Schema::table('ai_product_job_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_product_job_items', 'canonical_status')) {
                    $table->string('canonical_status', 30)->default('QUEUED')->after('status');
                }
                if (! Schema::hasColumn('ai_product_job_items', 'status_reason')) {
                    $table->string('status_reason', 120)->nullable()->after('canonical_status');
                }
                if (! Schema::hasColumn('ai_product_job_items', 'state_changed_at')) {
                    $table->timestamp('state_changed_at')->nullable()->after('status_reason');
                }
                if (! Schema::hasColumn('ai_product_job_items', 'idempotency_key')) {
                    $table->string('idempotency_key', 64)->nullable()->after('ai_product_job_id');
                }
                if (! Schema::hasColumn('ai_product_job_items', 'technical_context_hash')) {
                    $table->string('technical_context_hash', 64)->nullable()->after('idempotency_key');
                }
                if (! Schema::hasColumn('ai_product_job_items', 'prompt_version')) {
                    $table->string('prompt_version', 100)->nullable()->after('technical_context_hash');
                }
            });

            $this->backfillLegacyState('ai_product_job_items');

            $indexes = collect(Schema::getIndexes('ai_product_job_items'))->pluck('name')->all();
            if (! in_array('ai_product_item_idempotency_uq', $indexes, true)) {
                Schema::table('ai_product_job_items', function (Blueprint $table): void {
                    $table->unique('idempotency_key', 'ai_product_item_idempotency_uq');
                });
            }
        }
    }

    private function backfillLegacyState(string $table): void
    {
        if (! Schema::hasColumn($table, 'canonical_status') || ! Schema::hasColumn($table, 'status_reason')) {
            return;
        }

        $mapping = [
            'queued' => 'QUEUED',
            'pending' => 'QUEUED',
            'draft' => 'QUEUED',
            'processing' => 'RUNNING',
            'needs_review' => 'REVIEW_REQUIRED',
            'completed' => 'DONE',
            'completed_verified' => 'DONE',
            'completed_with_warnings' => 'REVIEW_REQUIRED',
            'completed_with_errors' => 'FAILED',
            'failed' => 'FAILED',
            'blocked' => 'BLOCKED',
            'stuck' => 'BLOCKED',
            'cancelled' => 'CANCELLED',
        ];

        foreach ($mapping as $legacy => $canonical) {
            DB::table($table)
                ->where('status', $legacy)
                ->where(function ($query): void {
                    $query->whereNull('status_reason')->orWhere('status_reason', '');
                })
                ->update([
                    'canonical_status' => $canonical,
                    'status_reason' => 'LEGACY_PRE_GOVERNANCE',
                    'state_changed_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_product_job_items')) {
            Schema::table('ai_product_job_items', function (Blueprint $table): void {
                $table->dropUnique('ai_product_item_idempotency_uq');
                foreach (['canonical_status', 'status_reason', 'state_changed_at', 'idempotency_key', 'technical_context_hash', 'prompt_version'] as $column) {
                    if (Schema::hasColumn('ai_product_job_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('ai_product_jobs')) {
            Schema::table('ai_product_jobs', function (Blueprint $table): void {
                foreach (['canonical_status', 'status_reason', 'state_changed_at', 'prompt_version'] as $column) {
                    if (Schema::hasColumn('ai_product_jobs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
