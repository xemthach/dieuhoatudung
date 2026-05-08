<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // ── Lead classification ──
            $table->string('lead_type', 20)->default('general')->after('id')
                  ->comment('product|consultation|general');

            // ── Denormalized product metadata ──
            $table->string('product_name')->nullable()->after('interested_product_id');
            $table->string('product_sku', 50)->nullable()->after('product_name');
            $table->string('product_url')->nullable()->after('product_sku');
            $table->string('brand_name', 100)->nullable()->after('product_url');
            $table->string('category_name', 100)->nullable()->after('brand_name');
            $table->unsignedInteger('capacity_btu')->nullable()->after('category_name');

            // ── Scoring ──
            $table->unsignedSmallInteger('intent_score')->default(40)->after('capacity_btu');

            // ── CRM fields ──
            $table->string('usage_type', 50)->nullable()->after('area')
                  ->comment('nha_hang, van_phong, showroom, etc');
            $table->string('region', 100)->nullable()->after('usage_type');

            // ── Link to quote_request ──
            $table->unsignedBigInteger('quote_request_id')->nullable()->after('source_page');
            $table->foreign('quote_request_id')
                  ->references('id')->on('quote_requests')
                  ->nullOnDelete();

            // ── Indexes for CRM queries ──
            $table->index('lead_type');
            $table->index('intent_score');
            $table->index(['lead_type', 'intent_score']);
            $table->index('created_at');
        });

        // ── Backfill existing leads ──
        // Quote-derived leads
        \Illuminate\Support\Facades\DB::statement("
            UPDATE leads SET lead_type = 'product', intent_score = 100
            WHERE interested_product_id IS NOT NULL
        ");

        // BTU calculator leads
        \Illuminate\Support\Facades\DB::statement("
            UPDATE leads SET lead_type = 'consultation', intent_score = 70
            WHERE need_type = 'btu_calculator'
        ");

        // Quote request leads (without product)
        \Illuminate\Support\Facades\DB::statement("
            UPDATE leads SET lead_type = 'general', intent_score = 40
            WHERE lead_type = 'general' AND need_type = 'quote_request'
        ");

        // Backfill product metadata from products table
        \Illuminate\Support\Facades\DB::statement("
            UPDATE leads l
            INNER JOIN products p ON l.interested_product_id = p.id
            SET l.product_name = p.name,
                l.product_sku = p.sku,
                l.capacity_btu = p.btu
            WHERE l.interested_product_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['quote_request_id']);
            $table->dropIndex(['lead_type', 'intent_score']);
            $table->dropIndex(['lead_type']);
            $table->dropIndex(['intent_score']);
            $table->dropColumn([
                'lead_type', 'product_name', 'product_sku', 'product_url',
                'brand_name', 'category_name', 'capacity_btu', 'intent_score',
                'usage_type', 'region', 'quote_request_id',
            ]);
        });
    }
};
