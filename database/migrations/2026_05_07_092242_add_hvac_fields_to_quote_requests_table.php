<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            // ── Lead classification ──
            $table->string('lead_type', 20)->default('general')->after('id');
            $table->unsignedSmallInteger('intent_score')->default(40)->after('lead_type');

            // ── Denormalized product metadata ──
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_sku', 80)->nullable()->after('product_name');
            $table->string('product_model', 80)->nullable()->after('product_sku');
            $table->string('product_brand', 100)->nullable()->after('product_model');
            $table->string('product_category', 100)->nullable()->after('product_brand');
            $table->unsignedInteger('product_capacity_btu')->nullable()->after('product_category');
            $table->text('product_url')->nullable()->after('product_capacity_btu');
            $table->json('selected_product_snapshot')->nullable()->after('product_url');

            // ── Space details (Step 1 enrichment) ──
            $table->text('usage_description')->nullable()->after('project_type');
            $table->unsignedSmallInteger('number_of_rooms')->default(1)->after('usage_description');

            // ── Environmental conditions (Step 2 enrichment) ──
            $table->decimal('estimated_volume_m3', 10, 2)->nullable()->after('ceiling_height');
            $table->unsignedSmallInteger('number_of_people')->nullable()->after('estimated_volume_m3');
            $table->string('sun_exposure', 30)->nullable()->after('number_of_people');
            $table->string('insulation_quality', 30)->nullable()->after('sun_exposure');
            $table->string('glass_area', 30)->nullable()->after('insulation_quality');
            $table->boolean('open_space')->nullable()->after('glass_area');
            $table->string('current_aircon_status', 40)->nullable()->after('open_space');

            // ── Technical requirements (Step 3 enrichment) ──
            $table->unsignedInteger('calculated_btu')->nullable()->after('preferred_btu');
            $table->string('suggested_capacity_range', 50)->nullable()->after('calculated_btu');
            $table->json('preferred_brands')->nullable()->after('preferred_brand');
            $table->string('power_supply', 20)->nullable()->after('need_three_phase');
            $table->string('installation_type', 30)->nullable()->after('power_supply');
            $table->decimal('pipe_distance_m', 5, 1)->nullable()->after('installation_type');
            $table->string('outdoor_unit_location', 30)->nullable()->after('pipe_distance_m');
            $table->string('drainage_available', 20)->nullable()->after('outdoor_unit_location');
            $table->string('has_existing_piping', 20)->nullable()->after('drainage_available');

            // ── Budget/timeline (Step 4 enrichment) ──
            $table->string('need_installation_service', 30)->nullable()->after('budget_range');
            $table->boolean('need_invoice')->nullable()->after('need_installation_service');
            $table->boolean('need_site_survey')->nullable()->after('need_invoice');
            $table->string('payment_method', 30)->nullable()->after('need_site_survey');

            // ── Contact enrichment (Step 5) ──
            $table->string('province_city', 100)->nullable()->after('address');
            $table->string('district', 100)->nullable()->after('province_city');
            $table->string('preferred_contact_method', 20)->nullable()->after('district');
            $table->string('preferred_contact_time', 30)->nullable()->after('preferred_contact_method');

            // ── UTM tracking ──
            $table->string('utm_source', 100)->nullable()->after('source_page');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->string('utm_term', 100)->nullable()->after('utm_campaign');
            $table->string('utm_content', 100)->nullable()->after('utm_term');
            $table->text('landing_page')->nullable()->after('utm_content');
            $table->text('referrer')->nullable()->after('landing_page');

            // ── Indexes ──
            $table->index('lead_type');
            $table->index('intent_score');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn([
                'lead_type', 'intent_score',
                'product_name', 'product_sku', 'product_model', 'product_brand',
                'product_category', 'product_capacity_btu', 'product_url', 'selected_product_snapshot',
                'usage_description', 'number_of_rooms',
                'estimated_volume_m3', 'number_of_people', 'sun_exposure', 'insulation_quality',
                'glass_area', 'open_space', 'current_aircon_status',
                'calculated_btu', 'suggested_capacity_range', 'preferred_brands',
                'power_supply', 'installation_type', 'pipe_distance_m',
                'outdoor_unit_location', 'drainage_available', 'has_existing_piping',
                'need_installation_service', 'need_invoice', 'need_site_survey', 'payment_method',
                'province_city', 'district', 'preferred_contact_method', 'preferred_contact_time',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'landing_page', 'referrer',
            ]);
        });
    }
};
