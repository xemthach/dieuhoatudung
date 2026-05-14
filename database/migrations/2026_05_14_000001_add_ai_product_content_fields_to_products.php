<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ai_status', 30)->default('not_generated')->after('schema_enabled');
            $table->unsignedTinyInteger('ai_score')->default(0)->after('ai_status');
            $table->timestamp('ai_last_run_at')->nullable()->after('ai_score');
            $table->text('ai_error_message')->nullable()->after('ai_last_run_at');
            $table->unsignedInteger('ai_warning_count')->default(0)->after('ai_error_message');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_warning_count');
            $table->string('merchant_title')->nullable()->after('product_type');
            $table->text('merchant_description')->nullable()->after('merchant_title');

            $table->index(['ai_status', 'ai_score']);
            $table->index('ai_last_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['ai_status', 'ai_score']);
            $table->dropIndex(['ai_last_run_at']);
            $table->dropColumn([
                'ai_status',
                'ai_score',
                'ai_last_run_at',
                'ai_error_message',
                'ai_warning_count',
                'ai_generated_at',
                'merchant_title',
                'merchant_description',
            ]);
        });
    }
};
