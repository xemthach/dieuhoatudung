<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Http;

class MailgunMailProvider implements MailProviderInterface
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingService->get('mail.mailgun_api_key')) &&
               !empty($this->settingService->get('mail.mailgun_domain')) &&
               !empty($this->settingService->get('mail.mailgun_endpoint'));
    }

    public function testConnection(): array
    {
        return $this->sendTestEmail($this->settingService->get('mail.mail_test_recipient', 'test@example.com'));
    }

    public function sendTestEmail(string $to): array
    {
        return $this->send([
            'to' => $to,
            'subject' => 'Test Mailgun API Email',
            'html' => '<p>Đây là email test cấu hình Mailgun API.</p>',
            'text' => 'Đây là email test cấu hình Mailgun API.',
        ]);
    }

    public function send(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Thiếu cấu hình Mailgun (API Key, Domain hoặc Endpoint).'];
        }

        $apiKey = $this->settingService->get('mail.mailgun_api_key');
        $domain = $this->settingService->get('mail.mailgun_domain');
        $endpoint = rtrim($this->settingService->get('mail.mailgun_endpoint', 'api.mailgun.net'), '/');
        
        $fromEmail = $payload['from_email'] ?? $this->settingService->get('mail.mailgun_from_address') ?: $this->settingService->get('mail.mail_from_address') ?: config('mail.from.address', '');
        $fromName = $payload['from_name'] ?? $this->settingService->get('mail.mail_from_name', setting('general.site_name', ''));
        $replyTo = $payload['reply_to'] ?? $this->settingService->get('mail.mail_reply_to');

        $data = [
            'from' => "$fromName <$fromEmail>",
            'to' => $payload['to'],
            'subject' => $payload['subject'],
        ];

        if (!empty($payload['html'])) {
            $data['html'] = $payload['html'];
        }
        if (!empty($payload['text'])) {
            $data['text'] = $payload['text'];
        }
        if (!empty($replyTo)) {
            $data['h:Reply-To'] = $replyTo;
        }

        $response = Http::withBasicAuth('api', $apiKey)
            ->asMultipart()
            ->post("https://{$endpoint}/v3/{$domain}/messages", $data);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Đã gửi mail qua Mailgun API.',
                'status_code' => $response->status(),
                'response' => $response->json()
            ];
        }

        return [
            'success' => false,
            'message' => 'Lỗi Mailgun API: ' . $response->body(),
            'status_code' => $response->status(),
            'response' => $response->json()
        ];
    }
}
