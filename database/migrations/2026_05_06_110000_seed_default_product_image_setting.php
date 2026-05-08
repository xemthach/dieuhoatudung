<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteSetting;

return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::updateOrCreate(
            ['group' => 'product_detail', 'key' => 'default_product_image'],
            ['value' => '', 'type' => 'string']
        );
    }

    public function down(): void
    {
        SiteSetting::where('group', 'product_detail')->where('key', 'default_product_image')->delete();
    }
};
