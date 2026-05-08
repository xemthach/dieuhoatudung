<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

class SmtpMailProvider implements MailProviderInterface
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function isConfigured(): bool
    {
        return !empty($this->settingService->get('mail.smtp_host')) &&
               !empty($this->settingService->get('mail.smtp_port'));
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Thiếu cấu hình SMTP Host hoặc Port.'];
        }

        try {
            $this->configureRuntimeMailer();

            // Laravel 10+ uses Symfony Mailer
            $transport = Mail::mailer('dynamic_smtp')->getSymfonyTransport();

            return ['success' => true, 'message' => 'Kết nối SMTP thành công.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Lỗi kết nối SMTP: ' . $e->getMessage()];
        }
    }

    public function sendTestEmail(string $to): array
    {
        return $this->send([
            'to' => $to,
            'subject' => 'Test SMTP Email',
            'html' => '<p>Đây là email test cấu hình SMTP.</p>',
            'text' => 'Đây là email test cấu hình SMTP.',
        ]);
    }

    public function send(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Thiếu cấu hình SMTP Host hoặc Port.'];
        }

        try {
            $this->configureRuntimeMailer();

            $fromEmail = $payload['from_email'] ?? $this->settingService->get('mail.mail_from_address') ?: config('mail.from.address', '');
            $fromName = $payload['from_name'] ?? $this->settingService->get('mail.mail_from_name', setting('general.site_name', ''));
            $replyTo = $payload['reply_to'] ?? $this->settingService->get('mail.mail_reply_to');

            Mail::mailer('dynamic_smtp')->html($payload['html'] ?? '', function ($message) use ($payload, $fromEmail, $fromName, $replyTo) {
                $message->to($payload['to'])
                        ->subject($payload['subject'])
                        ->from($fromEmail, $fromName);
                
                if ($replyTo) {
                    $message->replyTo($replyTo);
                }

                if (!empty($payload['text'])) {
                    $message->text($payload['text']);
                }
            });

            return [
                'success' => true,
                'message' => 'Đã gửi mail qua SMTP.',
                'status_code' => 200,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi SMTP: ' . $e->getMessage()
            ];
        }
    }

    private function configureRuntimeMailer(): void
    {
        Config::set('mail.mailers.dynamic_smtp', [
            'transport' => 'smtp',
            'host' => $this->settingService->get('mail.smtp_host'),
            'port' => $this->settingService->get('mail.smtp_port', 587),
            'encryption' => $this->settingService->get('mail.smtp_encryption', 'tls') === 'none' ? null : $this->settingService->get('mail.smtp_encryption', 'tls'),
            'username' => $this->settingService->get('mail.smtp_username'),
            'password' => $this->settingService->get('mail.smtp_password'),
            'timeout' => $this->settingService->get('mail.smtp_timeout', null),
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ]);
    }
}
