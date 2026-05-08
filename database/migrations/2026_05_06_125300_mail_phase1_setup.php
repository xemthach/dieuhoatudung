<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * [Mail Phase 1 — F1-1 + F1-3]
     * 1. Thêm event_key và template_key vào mail_logs.
     * 2. Bật mail_enabled = '1' trong site_settings.
     * 3. Seed 9 mail_notify group settings.
     */
    public function up(): void
    {
        // ── F1-3: Thêm columns vào mail_logs ──────────────────────────
        Schema::table('mail_logs', function (Blueprint $table) {
            $table->string('event_key')->nullable()->after('provider')->comment('e.g. lead.admin, quote.admin, review.admin');
            $table->string('template_key')->nullable()->after('event_key')->comment('e.g. lead_admin_notification');
        });

        // ── F1-1: Bật mail_enabled = '1' ─────────────────────────────
        DB::table('site_settings')
            ->where('group', 'mail')
            ->where('key', 'mail_enabled')
            ->update(['value' => '1']);

        // ── F1-5: Seed mail_notify group settings ─────────────────────
        $notifySettings = [
            // Admin notifications
            ['key' => 'lead_admin',        'value' => '1', 'label' => 'Thông báo admin khi có lead mới'],
            ['key' => 'quote_admin',       'value' => '1', 'label' => 'Thông báo admin khi có báo giá mới'],
            ['key' => 'review_admin',      'value' => '1', 'label' => 'Thông báo admin khi có đánh giá mới'],
            ['key' => 'question_admin',    'value' => '1', 'label' => 'Thông báo admin khi có câu hỏi mới'],
            ['key' => 'system_admin',      'value' => '0', 'label' => 'Thông báo admin khi có lỗi hệ thống'],
            // Customer notifications
            ['key' => 'lead_customer',     'value' => '0', 'label' => 'Gửi xác nhận cho khách sau khi lead'],
            ['key' => 'quote_customer',    'value' => '0', 'label' => 'Gửi xác nhận cho khách sau khi báo giá'],
            ['key' => 'review_customer',   'value' => '0', 'label' => 'Gửi email cho khách khi review được duyệt'],
            ['key' => 'question_customer', 'value' => '0', 'label' => 'Gửi email cho khách khi câu hỏi được trả lời'],
        ];

        foreach ($notifySettings as $s) {
            DB::table('site_settings')->insertOrIgnore([
                'group'        => 'mail_notify',
                'key'          => $s['key'],
                'value'        => $s['value'],
                'type'         => 'boolean',
                'is_encrypted' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // ── Admin notify email riêng cho từng module ──────────────────
        // Dùng lead_notify_email làm fallback chung, thêm override riêng
        $emailSettings = [
            ['key' => 'quote_notify_email',    'value' => '', 'type' => 'text'],
            ['key' => 'review_notify_email',   'value' => '', 'type' => 'text'],
            ['key' => 'question_notify_email', 'value' => '', 'type' => 'text'],
        ];

        foreach ($emailSettings as $s) {
            DB::table('site_settings')->insertOrIgnore([
                'group'        => 'mail_notify',
                'key'          => $s['key'],
                'value'        => $s['value'],
                'type'         => $s['type'],
                'is_encrypted' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('mail_logs', function (Blueprint $table) {
            $table->dropColumn(['event_key', 'template_key']);
        });

        DB::table('site_settings')
            ->where('group', 'mail')
            ->where('key', 'mail_enabled')
            ->update(['value' => '']);

        DB::table('site_settings')
            ->where('group', 'mail_notify')
            ->delete();
    }
};
