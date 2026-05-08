<?php

namespace App\Services\Mail;

use App\Models\MailTemplate;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Log;

/**
 * MailDispatchService
 *
 * Centralised mail dispatcher.
 * Controllers call sendEvent(); this service resolves the template,
 * renders variables, determines recipients, checks toggles,
 * and delegates to MailProviderService.
 *
 * Usage:
 *   app(MailDispatchService::class)->sendEvent(
 *       event:       'quote_admin',
 *       vars:        ['customer_name' => 'Nguyễn Văn A', ...],
 *       adminEmail:  setting('lead.lead_notify_email'),
 *       relatedType: 'QuoteRequest',
 *       relatedId:   $quote->id
 *   );
 */
class MailDispatchService
{
    /** Maps event key → template key */
    private const EVENT_TEMPLATE_MAP = [
        'lead_admin'        => 'lead_admin_notification',
        'lead_customer'     => 'lead_customer_confirmation',
        'quote_admin'       => 'quote_admin_notification',
        'quote_customer'    => 'quote_customer_confirmation',
        'review_admin'      => 'review_admin_notification',
        'review_customer'   => 'review_approved_customer',
        'question_admin'    => 'question_admin_notification',
        'question_customer' => 'question_answered_customer',
        'system_alert'      => 'system_alert',
    ];

    public function __construct(
        private readonly MailProviderService    $mailService,
        private readonly SettingService         $settings,
        private readonly MailTemplateRenderer   $renderer
    ) {}

    /**
     * Send an admin-facing event notification.
     *
     * @param string      $event       One of the keys in EVENT_TEMPLATE_MAP
     * @param array       $vars        Template variables to substitute
     * @param string      $adminEmail  Recipient override; falls back to lead_notify_email
     * @param string|null $relatedType Polymorphic model type for MailLog
     * @param int|null    $relatedId   Polymorphic model id for MailLog
     */
    public function sendEvent(
        string  $event,
        array   $vars = [],
        string  $adminEmail = '',
        ?string $relatedType = null,
        ?int    $relatedId = null
    ): array {
        $templateKey = self::EVENT_TEMPLATE_MAP[$event] ?? $event;

        // Enrich common variables from settings
        $vars = array_merge($this->commonVars(), $vars);

        // Resolve template
        $template = MailTemplate::findActive($templateKey);

        if ($template) {
            $subject  = $this->renderer->renderSubject($template, $vars);
            $html     = $this->renderer->renderHtml($template, $vars);
            $textBody = $this->renderer->renderText($template, $vars);
        } else {
            // Fallback: generate minimal inline notification
            Log::warning("MailDispatchService: template '{$templateKey}' not found, using fallback.");
            [$subject, $html, $textBody] = $this->fallbackContent($event, $vars);
        }

        if (empty($adminEmail)) {
            $adminEmail = $this->settings->get('lead.lead_notify_email', '');
        }

        if (empty($adminEmail)) {
            Log::warning("MailDispatchService: no recipient for event '{$event}'.");
            return ['success' => false, 'message' => 'No recipient configured'];
        }

        return $this->mailService->send(
            payload: [
                'to'      => $adminEmail,
                'subject' => $subject,
                'html'    => $html,
                'text'    => $textBody,
            ],
            eventKey:    $event,
            templateKey: $templateKey,
            relatedType: $relatedType,
            relatedId:   $relatedId
        );
    }

    /**
     * Send a customer-facing event notification.
     *
     * @param string $event         One of the _customer event keys
     * @param string $customerEmail Recipient email
     * @param array  $vars          Template variables
     */
    public function sendCustomerEvent(
        string  $event,
        string  $customerEmail,
        array   $vars = [],
        ?string $relatedType = null,
        ?int    $relatedId = null
    ): array {
        if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid or missing customer email'];
        }

        $templateKey = self::EVENT_TEMPLATE_MAP[$event] ?? $event;
        $vars        = array_merge($this->commonVars(), $vars);
        $template    = MailTemplate::findActive($templateKey);

        if ($template) {
            $subject  = $this->renderer->renderSubject($template, $vars);
            $html     = $this->renderer->renderHtml($template, $vars);
            $textBody = $this->renderer->renderText($template, $vars);
        } else {
            Log::warning("MailDispatchService: template '{$templateKey}' not found for customer event.");
            [$subject, $html, $textBody] = $this->fallbackContent($event, $vars);
        }

        return $this->mailService->send(
            payload: [
                'to'      => $customerEmail,
                'subject' => $subject,
                'html'    => $html,
                'text'    => $textBody,
            ],
            eventKey:    $event,
            templateKey: $templateKey,
            relatedType: $relatedType,
            relatedId:   $relatedId
        );
    }

    /**
     * Render a template with variables WITHOUT sending (for preview).
     */
    public function renderTemplate(string $templateKey, array $vars = []): array
    {
        $vars     = array_merge($this->commonVars(), $vars);
        $template = MailTemplate::findActive($templateKey);

        if (!$template) {
            return ['subject' => '(template not found)', 'html' => '', 'text' => ''];
        }

        return [
            'subject' => $template->renderSubject($vars),
            'html'    => $template->renderHtml($vars),
            'text'    => $template->renderText($vars),
        ];
    }

    /**
     * Common site-level variables available in all templates.
     */
    private function commonVars(): array
    {
        return [
            'site_name'   => $this->settings->get('general.site_name', config('app.name')),
            'hotline'     => $this->settings->get('contact.hotline', ''),
            'website_url' => config('app.url'),
            'admin_url'   => config('app.url') . '/admin',
        ];
    }

    /**
     * Minimal fallback email when no DB template exists yet.
     */
    private function fallbackContent(string $event, array $vars): array
    {
        $siteName = $vars['site_name'] ?? config('app.name');
        $subject  = "[{$siteName}] Thông báo: {$event}";
        $rows     = '';
        foreach ($vars as $k => $v) {
            if (in_array($k, ['site_name', 'hotline', 'website_url', 'admin_url'])) continue;
            if ($v === null || $v === '' || $v === '—') continue; // Skip empty values
            $rows .= "<tr><td style='background:#f8fafc;width:140px;padding:8px'><strong>{$k}</strong></td>"
                   . "<td style='padding:8px'>" . e((string)$v) . "</td></tr>";
        }
        $html = "
<div style='font-family:sans-serif;max-width:560px;margin:0 auto'>
<h2 style='color:#1a56db'>{$subject}</h2>
<table cellpadding='0' cellspacing='0' style='width:100%;border-collapse:collapse'>{$rows}</table>
<p style='color:#9ca3af;font-size:12px;margin-top:24px'>{$siteName}</p>
</div>";
        return [$subject, $html, null];
    }
}
