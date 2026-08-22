<?php

declare(strict_types=1);

namespace Fortress\Database;

use PDO;
use PDOException;

/**
 * Optional PostgreSQL Connector Module
 * Provides PostgreSQL connection management, pooling configuration, and migration support.
 * Note: PostgreSQL driver is currently optional / disabled by default in favor of the unified MongoDB store.
 */
class PostgresConnector
{
    private static ?PDO $connection = null;
    private static bool $enabled = false;
    private static array $config = [
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'fortress_irc',
        'username' => 'postgres',
        'password' => '',
        'sslmode' => 'prefer'
    ];

    /**
     * Enable or disable the optional PostgreSQL connector module.
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if PostgreSQL connector module is active.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Configure connection parameters.
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
        self::$connection = null; // reset instance on reconfigure
    }

    /**
     * Obtain a PDO connection to PostgreSQL if enabled and available.
     */
    public static function getConnection(): ?PDO
    {
        if (!self::$enabled) {
            return null;
        }

        if (self::$connection !== null) {
            return self::$connection;
        }

        if (!extension_loaded('pdo_pgsql')) {
            return null;
        }

        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
                self::$config['host'],
                (int)self::$config['port'],
                self::$config['database'],
                self::$config['sslmode']
            );

            self::$connection = new PDO($dsn, self::$config['username'], self::$config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$connection;
        } catch (PDOException $e) {
            error_log("PostgresConnector Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test connection status.
     *
     * @return array{available: bool, enabled: bool, error: ?string}
     */
    public static function testConnection(): array
    {
        if (!self::$enabled) {
            return [
                'available' => false,
                'enabled' => false,
                'error' => 'PostgreSQL connector is currently disabled (MongoDB active).'
            ];
        }

        $conn = self::getConnection();
        return [
            'available' => $conn !== null,
            'enabled' => true,
            'error' => $conn === null ? 'Could not connect to PostgreSQL instance.' : null
        ];
    }
}
