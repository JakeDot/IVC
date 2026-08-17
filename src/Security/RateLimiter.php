<?php

declare(strict_types=1);

namespace Fortress\Security;

/**
 * Ephemeral In-Memory Rate Limiter (Non-Logging / Privacy-Preserving)
 */
final class RateLimiter
{
    /**
     * @var array<string, array{count: int, reset: int}>
     */
    private static array $buckets = [];

    /**
     * Check if a request key is within allowed limits
     * Uses SHA-256 hashed keys to avoid storing raw client identifiers
     */
    public static function check(string $clientKey, int $maxRequests = 60, int $windowSeconds = 60): bool
    {
        self::gc();

        $hashedKey = hash('sha256', $clientKey);
        $now = time();

        if (!isset(self::$buckets[$hashedKey]) || self::$buckets[$hashedKey]['reset'] <= $now) {
            self::$buckets[$hashedKey] = [
                'count' => 1,
                'reset' => $now + $windowSeconds
            ];
            return true;
        }

        if (self::$buckets[$hashedKey]['count'] >= $maxRequests) {
            return false;
        }

        self::$buckets[$hashedKey]['count']++;
        return true;
    }

    /**
     * Purge expired buckets from memory
     */
    private static function gc(): void
    {
        $now = time();
        if (count(self::$buckets) > 500) {
            foreach (self::$buckets as $key => $bucket) {
                if ($bucket['reset'] <= $now) {
                    unset(self::$buckets[$key]);
                }
            }
        }
    }

    /**
     * Clear all rate limiting memory
     */
    public static function reset(): void
    {
        self::$buckets = [];
    }
}
