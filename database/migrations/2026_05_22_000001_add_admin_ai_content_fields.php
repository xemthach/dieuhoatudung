<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_categories', 'og_title')) {
                $table->string('og_title')->nullable()->after('seo_description');
            }

            if (! Schema::hasColumn('product_categories', 'og_description')) {
                $table->string('og_description')->nullable()->after('og_title');
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'og_title')) {
                $table->string('og_title')->nullable()->after('seo_description');
            }

            if (! Schema::hasColumn('brands', 'og_description')) {
                $table->string('og_description')->nullable()->after('og_title');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }

            if (! Schema::hasColumn('promotions', 'cta_content')) {
                $table->string('cta_content', 500)->nullable()->after('content');
            }

            if (! Schema::hasColumn('promotions', 'banner_copy')) {
                $table->string('banner_copy', 500)->nullable()->after('cta_content');
            }

            if (! Schema::hasColumn('promotions', 'placement')) {
                $table->string('placement')->nullable()->after('banner_copy');
            }

            if (! Schema::hasColumn('promotions', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('end_at');
            }

            if (! Schema::hasColumn('promotions', 'seo_description')) {
                $table->string('seo_description')->nullable()->after('seo_title');
            }

            if (! Schema::hasColumn('promotions', 'og_title')) {
                $table->string('og_title')->nullable()->after('seo_description');
            }

            if (! Schema::hasColumn('promotions', 'og_description')) {
                $table->string('og_description')->nullable()->after('og_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            foreach (['content', 'cta_content', 'banner_copy', 'placement', 'seo_title', 'seo_description', 'og_title', 'og_description'] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('brands', function (Blueprint $table) {
            foreach (['og_title', 'og_description'] as $column) {
                if (Schema::hasColumn('brands', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_categories', function (Blueprint $table) {
            foreach (['og_title', 'og_description'] as $column) {
                if (Schema::hasColumn('product_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
