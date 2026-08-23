<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ai_bulk_field_operations') || Schema::hasColumn('ai_bulk_field_operations', 'idempotency_key')) return;
        Schema::table('ai_bulk_field_operations', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->nullable()->unique('ai_bulk_field_op_idempotency_uq');
            $table->string('provider', 80)->nullable();
            $table->string('model', 160)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_bulk_field_operations')) return;
        Schema::table('ai_bulk_field_operations', function (Blueprint $table): void {
            foreach (['idempotency_key', 'provider', 'model', 'input_tokens', 'output_tokens', 'latency_ms'] as $column) {
                if (Schema::hasColumn('ai_bulk_field_operations', $column)) $table->dropColumn($column);
            }
        });
    }
};
