<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 200);
            $table->string('normalized_query', 200)->index();
            $table->unsignedInteger('result_count')->default(0);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Add indexes on products table for search performance (safe check)
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->index('model_code', 'products_model_code_index');
            });
        } catch (\Throwable) {
            // Index may already exist
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->index('sku', 'products_sku_index');
            });
        } catch (\Throwable) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_model_code_index');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_sku_index');
            });
        } catch (\Throwable) {}
    }
};
