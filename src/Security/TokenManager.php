<?php

declare(strict_types=1);

namespace Fortress\Security;

/**
 * CSRF Protection & Ephemeral WebRTC Room Session Token Manager
 */
final class TokenManager
{
    private const TOKEN_LIFETIME = 1800; // 30 minutes

    /**
     * Generate secure CSRF token
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_expires']) || $_SESSION['csrf_expires'] < time()) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expires'] = time() + self::TOKEN_LIFETIME;
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Generate secure ephemeral room key
     */
    public static function generateRoomKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
