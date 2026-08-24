<?php

declare(strict_types=1);

namespace cx\ivc\Security;

/**
 * Ephemeral In-Memory Rate Limiter (Non-Logging / Privacy-Preserving)
 */
final class RateLimiter
{
    /**
     * Path to shared RAM state file for fallback storage
     */
    private static function getStateFilePath(): string
    {
        $baseDir = @is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
        $dir = $baseDir . '/fortress_' . md5(__DIR__);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/fortress_rate_limits.json';
    }

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
        $hashedKey = hash('sha256', $clientKey);
        $now = time();

        if (function_exists('apcu_fetch')) {
            $apcKey = 'rl_' . $hashedKey;
            $bucket = apcu_fetch($apcKey);

            if ($bucket === false || $bucket['reset'] <= $now) {
                apcu_store($apcKey, ['count' => 1, 'reset' => $now + $windowSeconds], $windowSeconds);
                return true;
            }

            if ($bucket['count'] >= $maxRequests) {
                return false;
            }

            $bucket['count']++;
            apcu_store($apcKey, $bucket, $bucket['reset'] - $now);
            return true;
        }

        return self::checkFallback($hashedKey, $maxRequests, $windowSeconds, $now);
    }

    /**
     * Fallback file-based locking logic
     */
    private static function checkFallback(string $hashedKey, int $maxRequests, int $windowSeconds, int $now): bool
    {
        $filePath = self::getStateFilePath();
        $fp = @fopen($filePath, 'c+');
        if (!$fp) {
            // If we can't open the file, fallback to the array
            return self::checkMemory($hashedKey, $maxRequests, $windowSeconds, $now);
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        $buckets = $content ? json_decode($content, true) : [];
        if (!is_array($buckets)) {
            $buckets = [];
        }

        // gc
        if (count($buckets) > 500) {
            foreach ($buckets as $key => $bucket) {
                if ($bucket['reset'] <= $now) {
                    unset($buckets[$key]);
                }
            }
        }

        $allowed = true;
        if (!isset($buckets[$hashedKey]) || $buckets[$hashedKey]['reset'] <= $now) {
            $buckets[$hashedKey] = [
                'count' => 1,
                'reset' => $now + $windowSeconds
            ];
        } elseif ($buckets[$hashedKey]['count'] >= $maxRequests) {
            $allowed = false;
        } else {
            $buckets[$hashedKey]['count']++;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($buckets, JSON_THROW_ON_ERROR));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $allowed;
    }

    private static function checkMemory(string $hashedKey, int $maxRequests, int $windowSeconds, int $now): bool
    {
        self::gc();

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

        if (function_exists('apcu_delete') && class_exists('APCUIterator')) {
            $iterator = new \APCUIterator('/^rl_/');
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        }

        $filePath = self::getStateFilePath();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
