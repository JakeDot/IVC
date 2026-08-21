<?php

declare(strict_types=1);

namespace cx\ivc\Security;

/**
 * Enterprise IT Security Headers & Non-Logging Enforcement
 */
final class SecurityHeaders
{
    /**
     * Apply strict security HTTP response headers and custom protocol Status header
     *
     * @param int $httpStatus Standard HTTP status code (default 200)
     * @param string $appModes Extracted application modes/permissions mapped to user/object
     */
    public static function apply(int $httpStatus = 200, string $appModes = ''): void
    {
        if (headers_sent()) {
            return;
        }

        // Custom IVC Protocol Status Header: X-IVC-Status:<httpstatus>+modes:<appstatus>
        // Use an X- header to avoid breaking the FastCGI HTTP Status Line (RFC 7230)
        $statusStr = "X-IVC-Status: {$httpStatus}";
        // Custom IVC Protocol Status Header: Status:<httpstatus>+modes:<appstatus>
        $statusStr = "Status: {$httpStatus}";
        if ($appModes !== '') {
            $statusStr .= "+modes:{$appModes}";
        }
        header($statusStr);

        // Prevent caching of any sensitive WebRTC signaling data (Non-Logging / Privacy directive)
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "media-src 'self' blob:",
            "connect-src 'self' wss: ws:",
            "img-src 'self' data:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        // Hardening Headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(self), microphone=(self), display-capture=(self)');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Permitted-Cross-Domain-Policies: none');
    }
}
