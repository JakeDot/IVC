<?php

declare(strict_types=1);

namespace Fortress\Services;

use Fortress\IRC\ServServ;
use Fortress\IRC\IrcServices;
use Fortress\Database\UserNickRepository;

/**
 * Service Connector for WhatsApp API integration.
 * Routes incoming WhatsApp messages/events into the IRC network
 * and handles indentifying users under $whatsapp.net.
 */
class WhatsAppService
{
    /**
     * Identifies a WhatsApp user and formats their IRC nickname based on the requirements.
     * Uses username@whatsapp.net or +phonenumber@whatsapp.net if username is absent.
     *
     * @param string|null $username The WhatsApp username (if available)
     * @param string $phoneNumber The WhatsApp phone number
     * @return string The standardized IRC ident format for the WhatsApp user
     */
    public static function identifyUser(?string $username, string $phoneNumber): string
    {
        $domain = '$whatsapp.net';
        $user = !empty($username) ? $username : $phoneNumber;

        // Strip out spaces or invalid characters for the username part if needed
        $user = preg_replace('/\s+/', '_', $user);

        return "{$user}@{$domain}";
    }

    /**
     * Handle incoming webhook payloads from WhatsApp.
     *
     * @param array $payload The JSON decoded payload from WhatsApp API
     * @return array Response status
     */
    public static function handleWebhook(array $payload): array
    {
        // Typically WhatsApp webhooks have a specific structure, e.g. entry -> changes -> value -> messages
        if (!isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            return ['success' => false, 'message' => 'Invalid or unsupported webhook payload structure.'];
        }

        $messageData = $payload['entry'][0]['changes'][0]['value']['messages'][0];
        $contactData = $payload['entry'][0]['changes'][0]['value']['contacts'][0] ?? [];

        $phoneNumber = $messageData['from'] ?? '';
        $username = $contactData['profile']['name'] ?? null;

        if (empty($phoneNumber)) {
            return ['success' => false, 'message' => 'Sender phone number not found in payload.'];
        }

        // Identify the user
        $ircIdent = self::identifyUser($username, $phoneNumber);

        $text = $messageData['text']['body'] ?? '';

        if (empty($text)) {
            return ['success' => false, 'message' => 'No text content found in WhatsApp message.'];
        }

        // Route message to IVC. If it's a command starting with '/', processCommand handles it.
        // Otherwise, it gets broadcast natively to the channel target.
        // Default target is a unique channel based on sender phone number.
        $targetChannel = '#' . ($phoneNumber ?: 'whatsapp-bridge');

        $response = IrcServices::processCommand($ircIdent, $targetChannel, $text);

        return [
            'success' => true,
            'message' => "WhatsApp message from {$ircIdent} processed.",
            'ident' => $ircIdent,
            'text' => $text,
            'response' => $response
        ];
    }
}
