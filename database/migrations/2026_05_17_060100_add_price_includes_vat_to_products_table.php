<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'price_includes_vat')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('price_includes_vat')->default(false)->after('sale_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'price_includes_vat')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('price_includes_vat');
        });
    }
};
