<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landing_sections')) {
            DB::table('landing_sections')
                ->where('content', 'like', '%BTUCalculatorService%')
                ->update([
                    'content' => DB::raw("REPLACE(content, 'BTUCalculatorService', 'du lieu khao sat thuc te')"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('posts')) {
            foreach (['content', 'excerpt', 'seo_title', 'seo_description', 'og_title', 'og_description'] as $column) {
                if (! Schema::hasColumn('posts', $column)) {
                    continue;
                }

                DB::table('posts')
                    ->where($column, 'like', '%BTUCalculatorService%')
                    ->update([
                        $column => DB::raw("REPLACE({$column}, 'BTUCalculatorService', 'du lieu khao sat thuc te')"),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('products')) {
            foreach (['short_description', 'long_description', 'seo_title', 'seo_description', 'og_title', 'og_description', 'merchant_title', 'merchant_description'] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    continue;
                }

                DB::table('products')
                    ->where($column, 'like', '%BTUCalculatorService%')
                    ->update([
                        $column => DB::raw("REPLACE({$column}, 'BTUCalculatorService', 'du lieu khao sat thuc te')"),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Content cleanup is intentionally not reversed.
    }
};
