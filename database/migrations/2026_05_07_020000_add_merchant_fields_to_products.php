<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Google Merchant Center required/recommended fields
            $table->string('condition')->default('new')->after('stock_status');
            $table->string('gtin')->nullable()->after('condition');
            $table->boolean('identifier_exists')->default(false)->after('gtin');
            $table->string('google_product_category')->nullable()->after('identifier_exists');
            $table->string('product_type')->nullable()->after('google_product_category');
            $table->string('shipping_weight')->nullable()->after('product_type');
            $table->string('shipping_label')->nullable()->after('shipping_weight');
            $table->string('custom_label_0')->nullable()->after('shipping_label');
            $table->string('custom_label_1')->nullable()->after('custom_label_0');
            $table->string('custom_label_2')->nullable()->after('custom_label_1');
            $table->string('custom_label_3')->nullable()->after('custom_label_2');
            $table->string('custom_label_4')->nullable()->after('custom_label_3');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'condition', 'gtin', 'identifier_exists',
                'google_product_category', 'product_type',
                'shipping_weight', 'shipping_label',
                'custom_label_0', 'custom_label_1', 'custom_label_2',
                'custom_label_3', 'custom_label_4',
            ]);
        });
    }
};
