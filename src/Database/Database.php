<?php

declare(strict_types=1);

namespace Fortress\Database;

use PDO;
use PDOException;

/**
 * Database connection & schema initializer for IVC IRC Infrastructure.
 * Supports MySQL via PDO with an automatic SQLite fallback when MySQL is unreachable.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $dbName = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('DB_DATABASE') ?: 'ivc_irc';
        $user = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USERNAME') ?: 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASSWORD') ?: '';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            self::$driver = 'mysql';
            self::$pdo = $pdo;
        } catch (PDOException $e) {
            // Fallback to SQLite if MySQL connection fails or server is unavailable
            $sqlitePath = (is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir()) . '/ivc_irc_fallback.sqlite';
            $dsn = "sqlite:{$sqlitePath}";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$driver = 'sqlite';
            self::$pdo = $pdo;
        }

        self::initializeSchema();
        return self::$pdo;
    }

    public static function getDriver(): string
    {
        return self::$driver;
    }

    public static function setPdo(?PDO $pdo, string $driver = 'mysql'): void
    {
        self::$pdo = $pdo;
        self::$driver = $driver;
        if ($pdo !== null) {
            self::initializeSchema();
        }
    }

    private static function initializeSchema(): void
    {
        if (self::$pdo === null) return;

        $isMysql = self::$driver === 'mysql';
        $autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $queries = [
            // Table for serverwide IRC settings
            "CREATE TABLE IF NOT EXISTS irc_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TEXT NOT NULL,
                description TEXT NULL,
                updated_at INT NOT NULL
            );",

            // Table for NAMESERV registered nicknames
            "CREATE TABLE IF NOT EXISTS nameserv_nicks (
                nickname VARCHAR(64) PRIMARY KEY,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(128) NULL,
                registered_at INT NOT NULL,
                last_seen INT NOT NULL,
                is_identified TINYINT DEFAULT 0
            );",

            // Table for CHANSERV registered channels
            "CREATE TABLE IF NOT EXISTS chanserv_channels (
                channel_name VARCHAR(64) PRIMARY KEY,
                owner_nick VARCHAR(64) NOT NULL,
                topic TEXT NULL,
                passkey VARCHAR(128) NULL,
                modes VARCHAR(32) DEFAULT '+t',
                registered_at INT NOT NULL
            );",

            // Table for channel access / roles (OP, VOICE, MEMBER)
            "CREATE TABLE IF NOT EXISTS channel_users (
                id {$autoInc},
                channel_name VARCHAR(64) NOT NULL,
                nickname VARCHAR(64) NOT NULL,
                role VARCHAR(16) DEFAULT 'MEMBER',
                added_at INT NOT NULL
            );"
        ];

        foreach ($queries as $sql) {
            self::$pdo->exec($sql);
        }

        // Initialize default serverwide settings if not present
        self::seedDefaultSettings();
    }

    private static function seedDefaultSettings(): void
    {
        $defaults = [
            'network_name' => ['IVC-IRC Network', 'Name of the IRC Network'],
            'server_name' => ['fortress.ivc.local', 'Primary IRC Server Domain'],
            'motd' => ['Welcome to IVC WebRTC IRC Network! Secure, Anonymous & Encrypted.', 'Message of the day'],
            'max_channels_per_user' => ['10', 'Maximum channels a user can register'],
            'allow_anonymous' => ['1', 'Allow anonymous client connections'],
            'version' => ['2.0.0-IRC', 'Server Infrastructure Version']
        ];

        $stmtCheck = self::$pdo->prepare("SELECT COUNT(*) FROM irc_settings WHERE setting_key = :key");
        $stmtInsert = self::$pdo->prepare("INSERT INTO irc_settings (setting_key, setting_value, description, updated_at) VALUES (:key, :val, :desc, :time)");

        $now = time();
        foreach ($defaults as $key => [$val, $desc]) {
            $stmtCheck->execute([':key' => $key]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtInsert->execute([
                    ':key' => $key,
                    ':val' => $val,
                    ':desc' => $desc,
                    ':time' => $now
                ]);
            }
        }
    }

    public static function resetDatabase(): void
    {
        if (self::$pdo === null) {
            self::getConnection();
        }
        self::$pdo->exec("DELETE FROM irc_settings;");
        self::$pdo->exec("DELETE FROM nameserv_nicks;");
        self::$pdo->exec("DELETE FROM chanserv_channels;");
        self::$pdo->exec("DELETE FROM channel_users;");
        self::seedDefaultSettings();
    }
}
