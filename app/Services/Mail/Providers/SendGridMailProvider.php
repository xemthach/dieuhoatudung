<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Http;

class SendGridMailProvider implements MailProviderInterface
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingService->get('mail.sendgrid_api_key'));
    }

    public function testConnection(): array
    {
        return $this->sendTestEmail($this->settingService->get('mail.mail_test_recipient', 'test@example.com'));
    }

    public function sendTestEmail(string $to): array
    {
        return $this->send([
            'to' => $to,
            'subject' => 'Test SendGrid API Email',
            'html' => '<p>Đây là email test cấu hình SendGrid API.</p>',
            'text' => 'Đây là email test cấu hình SendGrid API.',
        ]);
    }

    public function send(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Thiếu cấu hình SendGrid API Key.'];
        }

        $apiKey = $this->settingService->get('mail.sendgrid_api_key');
        $fromEmail = $payload['from_email'] ?? $this->settingService->get('mail.sendgrid_from_address') ?: $this->settingService->get('mail.mail_from_address') ?: config('mail.from.address', '');
        $fromName = $payload['from_name'] ?? $this->settingService->get('mail.sendgrid_from_name') ?: $this->settingService->get('mail.mail_from_name', setting('general.site_name', ''));
        $replyTo = $payload['reply_to'] ?? $this->settingService->get('mail.mail_reply_to');

        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $payload['to']]],
                    'subject' => $payload['subject'],
                ]
            ],
            'from' => ['email' => $fromEmail, 'name' => $fromName],
            'content' => [],
        ];

        if (!empty($payload['text'])) {
            $data['content'][] = ['type' => 'text/plain', 'value' => $payload['text']];
        }
        if (!empty($payload['html'])) {
            $data['content'][] = ['type' => 'text/html', 'value' => $payload['html']];
        }
        if (!empty($replyTo)) {
            $data['reply_to'] = ['email' => $replyTo];
        }

        $response = Http::withToken($apiKey)
            ->post('https://api.sendgrid.com/v3/mail/send', $data);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Đã gửi mail qua SendGrid API.',
                'status_code' => $response->status(),
                'response' => $response->json() // SendGrid often returns empty body for 202
            ];
        }

        return [
            'success' => false,
            'message' => 'Lỗi SendGrid API: ' . $response->body(),
            'status_code' => $response->status(),
            'response' => $response->json()
        ];
    }
}
