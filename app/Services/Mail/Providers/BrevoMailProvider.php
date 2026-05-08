<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Http;

class BrevoMailProvider implements MailProviderInterface
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingService->get('mail.brevo_api_key'));
    }

    public function testConnection(): array
    {
        return $this->sendTestEmail($this->settingService->get('mail.mail_test_recipient', 'test@example.com'));
    }

    public function sendTestEmail(string $to): array
    {
        return $this->send([
            'to' => $to,
            'subject' => 'Test Brevo API Email',
            'html' => '<p>Đây là email test cấu hình Brevo API.</p>',
            'text' => 'Đây là email test cấu hình Brevo API.',
        ]);
    }

    public function send(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Thiếu cấu hình Brevo API Key.'];
        }

        $apiKey = $this->settingService->get('mail.brevo_api_key');
        $fromEmail = $payload['from_email'] ?? $this->settingService->get('mail.brevo_sender_email') ?: $this->settingService->get('mail.mail_from_address') ?: config('mail.from.address', '');
        $fromName = $payload['from_name'] ?? $this->settingService->get('mail.brevo_sender_name') ?: $this->settingService->get('mail.mail_from_name', setting('general.site_name', ''));
        $replyTo = $payload['reply_to'] ?? $this->settingService->get('mail.mail_reply_to');

        $data = [
            'sender' => ['name' => $fromName, 'email' => $fromEmail],
            'to' => [['email' => $payload['to']]],
            'subject' => $payload['subject'],
        ];

        if (!empty($payload['html'])) {
            $data['htmlContent'] = $payload['html'];
        }
        if (!empty($payload['text'])) {
            $data['textContent'] = $payload['text'];
        }
        if (!empty($replyTo)) {
            $data['replyTo'] = ['email' => $replyTo];
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $data);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Đã gửi mail qua Brevo API.',
                'status_code' => $response->status(),
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'message' => 'Lỗi Brevo API: ' . $response->body(),
            'status_code' => $response->status(),
            'response' => $response->json()
        ];
    }
}
