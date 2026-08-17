<?php

declare(strict_types=1);

namespace Fortress\Security;

/**
 * Input Sanitization and Validation for WebRTC SDP & ICE Payloads
 */
final class Sanitizer
{
    /**
     * Sanitize Room ID (alphanumeric, dash, underscore, 3-64 chars)
     */
    public static function sanitizeRoomId(string $roomId): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($roomId));
        if ($clean === null || strlen($clean) < 3) {
            return 'room-' . bin2hex(random_bytes(4));
        }
        return substr($clean, 0, 64);
    }

    /**
     * Validate and sanitize client ID
     */
    public static function sanitizeClientId(string $clientId): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($clientId));
        if ($clean === null || strlen($clean) < 4) {
            return 'peer-' . bin2hex(random_bytes(8));
        }
        return substr($clean, 0, 64);
    }

    /**
     * Sanitize and validate JSON SDP/ICE signal payload
     *
     * @param string $rawJson
     * @return array<string, mixed>|null
     */
    public static function validateSignalPayload(string $rawJson): ?array
    {
        if (strlen($rawJson) > 65536) {
            // Payload too large
            return null;
        }

        try {
            /** @var mixed $data */
            $data = json_decode($rawJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        if (!is_array($data) || !isset($data['type']) || !is_string($data['type'])) {
            return null;
        }

        $type = filter_var($data['type'], FILTER_SANITIZE_SPECIAL_CHARS);
        $allowedTypes = ['join', 'leave', 'offer', 'answer', 'ice-candidate', 'ping'];

        if (!in_array($type, $allowedTypes, true)) {
            return null;
        }

        return $data;
    }
}
