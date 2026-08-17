<?php

declare(strict_types=1);

namespace Fortress\Security;

/**
 * Input Sanitization and Validation for WebRTC SDP & ICE Payloads and IRC #room identifiers
 */
final class Sanitizer
{
    /**
     * Sanitize IRC Room ID (starts with #, alphanumeric, dash, underscore, max 64 chars)
     */
    public static function sanitizeRoomId(string $roomId): string
    {
        $trimmed = trim($roomId);
        if ($trimmed === '') {
            return '#room-' . bin2hex(random_bytes(4));
        }

        // Retain # or & prefix if present
        $hasHash = str_starts_with($trimmed, '#') || str_starts_with($trimmed, '&');

        $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '', $trimmed);
        if ($clean === null || strlen($clean) < 2) {
            return '#room-' . bin2hex(random_bytes(4));
        }

        $clean = substr($clean, 0, 63);
        return '#' . ltrim($clean, '#&');
    }

    /**
     * Validate and sanitize client ID / nickname
     */
    public static function sanitizeClientId(string $clientId): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($clientId));
        if ($clean === null || strlen($clean) < 2) {
            return 'peer-' . bin2hex(random_bytes(8));
        }
        return substr($clean, 0, 64);
    }

    /**
     * Sanitize and validate JSON SDP/ICE/Chat signal payload
     *
     * @param string $rawJson
     * @return array<string, mixed>|null
     */
    public static function validateSignalPayload(string $rawJson): ?array
    {
        if (strlen($rawJson) > 65536) {
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
        $allowedTypes = ['join', 'leave', 'offer', 'answer', 'ice-candidate', 'ping', 'chat', 'command'];

        if (!in_array($type, $allowedTypes, true)) {
            return null;
        }

        return $data;
    }
}
