<?php

namespace App\Services\Mail;

use App\Models\MailLog;
use App\Services\Settings\SettingService;
use App\Services\Mail\Contracts\MailProviderInterface;
use App\Services\Mail\Providers\SmtpMailProvider;
use App\Services\Mail\Providers\BrevoMailProvider;
use App\Services\Mail\Providers\TestmailProvider;
use App\Services\Mail\Providers\MailgunMailProvider;
use App\Services\Mail\Providers\SendGridMailProvider;
use Illuminate\Support\Facades\Log;

class MailProviderService
{
    private SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get the currently active mail provider based on settings.
     */
    public function getProvider(?string $providerName = null): ?MailProviderInterface
    {
        $name = $providerName ?: $this->settingService->get('mail.mail_provider', 'smtp');

        return match ($name) {
            'smtp' => new SmtpMailProvider($this->settingService),
            'brevo' => new BrevoMailProvider($this->settingService),
            'testmail' => new TestmailProvider($this->settingService),
            'mailgun' => new MailgunMailProvider($this->settingService),
            'sendgrid' => new SendGridMailProvider($this->settingService),
            default => null,
        };
    }

    /**
     * Send an email and log it.
     *
     * @param array  $payload      ['to','subject','html','text','from_email','from_name','reply_to']
     * @param string $eventKey     e.g. 'lead.admin', 'quote.admin', 'review.admin'
     * @param string $templateKey  e.g. 'lead_admin_notification'
     * @param string|null $relatedType
     * @param int|null    $relatedId
     */
    public function send(
        array $payload,
        string $eventKey = '',
        string $templateKey = '',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): array {
        $isEnabled = (bool) $this->settingService->get('mail.mail_enabled', false);

        if (!$isEnabled) {
            // F1-2: Log skipped so admin can see in MailLog
            $this->logMail(
                provider: 'none',
                to: $payload['to'] ?? '',
                subject: $payload['subject'] ?? '',
                status: 'skipped',
                eventKey: $eventKey,
                templateKey: $templateKey,
                errorMessage: 'mail_enabled = false',
                relatedType: $relatedType,
                relatedId: $relatedId
            );
            return [
                'success' => false,
                'message' => 'Hệ thống gửi mail đang bị tắt (mail_enabled = false).',
            ];
        }

        // Check per-event setting (mail_notify.{eventKey without dot-suffix})
        if ($eventKey) {
            $notifyKey = str_replace('.', '_', $eventKey);
            $notifyEnabled = (bool) $this->settingService->get("mail_notify.{$notifyKey}", true);
            if (!$notifyEnabled) {
                $this->logMail(
                    provider: 'none',
                    to: $payload['to'] ?? '',
                    subject: $payload['subject'] ?? '',
                    status: 'skipped',
                    eventKey: $eventKey,
                    templateKey: $templateKey,
                    errorMessage: "mail_notify.{$notifyKey} = false",
                    relatedType: $relatedType,
                    relatedId: $relatedId
                );
                return [
                    'success' => false,
                    'message' => "Sự kiện mail {$eventKey} đang bị tắt.",
                ];
            }
        }

        $providerName = $this->settingService->get('mail.mail_provider', 'smtp');
        $provider = $this->getProvider($providerName);

        if (!$provider || !$provider->isConfigured()) {
            $msg = 'Provider ('.$providerName.') chưa được cấu hình đầy đủ.';
            $this->logMail(
                provider: $providerName,
                to: $payload['to'] ?? '',
                subject: $payload['subject'] ?? '',
                status: 'failed',
                eventKey: $eventKey,
                templateKey: $templateKey,
                errorMessage: $msg,
                relatedType: $relatedType,
                relatedId: $relatedId
            );
            return ['success' => false, 'message' => $msg];
        }

        try {
            $result = $provider->send($payload);

            $this->logMail(
                provider: $providerName,
                to: $payload['to'] ?? '',
                subject: $payload['subject'] ?? '',
                status: $result['success'] ? 'sent' : 'failed',
                statusCode: $result['status_code'] ?? null,
                responseExcerpt: $result['response'] ?? null,
                eventKey: $eventKey,
                templateKey: $templateKey,
                errorMessage: $result['success'] ? null : $result['message'],
                relatedType: $relatedType,
                relatedId: $relatedId
            );

            return $result;
        } catch (\Exception $e) {
            $this->logMail(
                provider: $providerName,
                to: $payload['to'] ?? '',
                subject: $payload['subject'] ?? '',
                status: 'failed',
                eventKey: $eventKey,
                templateKey: $templateKey,
                errorMessage: $e->getMessage(),
                relatedType: $relatedType,
                relatedId: $relatedId
            );

            return [
                'success' => false,
                'message' => 'Lỗi hệ thống khi gửi mail: ' . $e->getMessage()
            ];
        }
    }

    private function logMail(
        string $provider,
        string $to,
        string $subject,
        string $status,
        $statusCode = null,
        $responseExcerpt = null,
        string $eventKey = '',
        string $templateKey = '',
        ?string $errorMessage = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        try {
            MailLog::create([
                'provider'         => $provider,
                'event_key'        => $eventKey ?: null,
                'template_key'     => $templateKey ?: null,
                'to_email'         => $to,
                'subject'          => $subject,
                'status'           => $status,
                'status_code'      => $statusCode,
                'response_excerpt' => is_array($responseExcerpt) ? json_encode($responseExcerpt) : $responseExcerpt,
                'error_message'    => $errorMessage,
                'related_type'     => $relatedType,
                'related_id'       => $relatedId,
                'sent_at'          => $status === 'sent' ? now() : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log mail: ' . $e->getMessage());
        }
    }
}
