<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Settings\SettingService;

class TestmailProvider implements MailProviderInterface
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingService->get('mail.testmail_namespace')) &&
               !empty($this->settingService->get('mail.testmail_recipient'));
    }

    public function testConnection(): array
    {
        return [
            'success' => true,
            'message' => 'Lưu ý: testmail.app dùng làm địa chỉ nhận test, không phải provider gửi transactional mail. Không thể gửi test.'
        ];
    }

    public function sendTestEmail(string $to): array
    {
        return [
            'success' => false,
            'message' => 'testmail.app chỉ nhận email, không hỗ trợ gửi email trực tiếp qua API từ hệ thống này. Vui lòng đổi Provider sang SMTP hoặc Brevo và gửi tới địa chỉ testmail.'
        ];
    }

    public function send(array $payload): array
    {
        return [
            'success' => false,
            'message' => 'Không thể gửi email bằng Testmail.app. Vui lòng cấu hình SMTP, Brevo, Mailgun, hoặc SendGrid làm Mail Provider chính.'
        ];
    }
}
