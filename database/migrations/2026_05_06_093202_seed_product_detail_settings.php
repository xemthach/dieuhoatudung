<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteSetting;

return new class extends Migration
{
    private array $settings = [
        // Collapsible Description
        ['group' => 'product_detail', 'key' => 'enable_collapsible_description', 'value' => '1', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'description_collapsed_height', 'value' => '420', 'type' => 'integer'],
        ['group' => 'product_detail', 'key' => 'show_read_more_button', 'value' => '1', 'type' => 'boolean'],

        // Reviews
        ['group' => 'product_detail', 'key' => 'enable_reviews', 'value' => '1', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'review_auto_approve', 'value' => '0', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'review_require_phone', 'value' => '0', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'review_allow_images', 'value' => '1', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'review_max_images', 'value' => '3', 'type' => 'integer'],
        ['group' => 'product_detail', 'key' => 'review_show_verified_badge', 'value' => '1', 'type' => 'boolean'],

        // Questions
        ['group' => 'product_detail', 'key' => 'enable_questions', 'value' => '1', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'question_auto_approve', 'value' => '0', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'question_require_phone', 'value' => '0', 'type' => 'boolean'],
        ['group' => 'product_detail', 'key' => 'question_show_only_answered', 'value' => '0', 'type' => 'boolean'],
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
        SiteSetting::where('group', 'product_detail')->delete();
    }
};
