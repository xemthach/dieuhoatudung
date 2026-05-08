<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('project_type')->nullable()->after('client_name');
            $table->string('area_m2')->nullable()->after('area');
            $table->string('ceiling_height')->nullable()->after('area_m2');
            $table->json('product_ids')->nullable()->after('product_id');
            $table->integer('total_units')->nullable()->after('product_ids');
            $table->string('installation_time')->nullable()->after('result');
            $table->date('completion_date')->nullable()->after('installation_time');
            $table->text('testimonial')->nullable()->after('gallery_json');
            $table->string('og_image')->nullable()->after('robots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn([
                'project_type',
                'area_m2',
                'ceiling_height',
                'product_ids',
                'total_units',
                'installation_time',
                'completion_date',
                'testimonial',
                'og_image',
            ]);
        });
    }
};
