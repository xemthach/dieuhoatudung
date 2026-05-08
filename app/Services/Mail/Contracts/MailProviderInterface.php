<?php

namespace App\Services\Mail\Contracts;

interface MailProviderInterface
{
    /**
     * Check if the provider has enough configuration to operate.
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Test the connection to the provider.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Send a test email using this provider.
     *
     * @param string $to
     * @return array ['success' => bool, 'message' => string, 'status_code' => string|int|null]
     */
    public function sendTestEmail(string $to): array;

    /**
     * Send a transactional email.
     *
     * @param array $payload ['to' => string, 'subject' => string, 'html' => string, 'text' => string|null, 'from_name' => string|null, 'from_email' => string|null, 'reply_to' => string|null]
     * @return array ['success' => bool, 'message' => string, 'status_code' => string|int|null]
     */
    public function send(array $payload): array;
}
