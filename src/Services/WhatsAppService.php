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

        // We can process the message as an IRC command if it starts with / or just a standard message.
        // Or if they are interacting with the WhatsApp bridge (e.g. creating a chat).

        // Dispatch to IrcServices (simulating a standard IRC client or bridged client sending a message)
        // Here we just log or echo it as a proof of concept, or use IrcServices::processCommand

        // Example: if they send "/create chat #mygroup", route it to CHANSERV or ServServ
        if (str_starts_with($text, '/')) {
            // It's a command
            // We would need to set up context for IrcServices to parse this
            // $response = IrcServices::processCommand($text, $ircIdent, '#whatsapp-bridge', null);
            // return ['success' => true, 'response' => $response];
        }

        return [
            'success' => true,
            'message' => "WhatsApp message from {$ircIdent} processed.",
            'ident' => $ircIdent,
            'text' => $text
        ];
    }
}
