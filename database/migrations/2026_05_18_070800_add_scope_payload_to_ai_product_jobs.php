<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_product_jobs', function (Blueprint $table) {
            $table->json('selected_product_ids_json')->nullable()->after('config_json');
            $table->json('current_page_ids_json')->nullable()->after('selected_product_ids_json');
            $table->json('filter_json')->nullable()->after('current_page_ids_json');
            $table->boolean('confirm_filter_scope')->default(false)->after('filter_json');
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'selected_product_ids_json',
                'current_page_ids_json',
                'filter_json',
                'confirm_filter_scope',
            ]);
        });
    }
};
