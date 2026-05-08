<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteSetting;

return new class extends Migration
{
    private array $settings = [
        ['group' => 'product_detail', 'key' => 'review_max_image_size_mb', 'value' => '3', 'type' => 'integer'],
        ['group' => 'product_detail', 'key' => 'review_allowed_image_types', 'value' => 'jpg,jpeg,png,webp', 'type' => 'string'],
    ];

    public function up(): void
    {
        foreach ($this->settings as $s) {
            SiteSetting::updateOrCreate(
                ['group' => $s['group'], 'key' => $s['key']],
                ['value' => $s['value'], 'type' => $s['type']]
            );
        }
    }

    public function down(): void
    {
        foreach ($this->settings as $s) {
            SiteSetting::where('group', $s['group'])->where('key', $s['key'])->delete();
        }
    }
};
