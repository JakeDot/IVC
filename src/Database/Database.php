<?php

declare(strict_types=1);

namespace Fortress\Database;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Database connection, prepared statement helper & schema manager for IVC IRC Infrastructure.
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
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            self::$driver = 'mysql';
            self::$pdo = $pdo;
        } catch (PDOException $e) {
            // Fallback to SQLite if MySQL connection fails or server is unavailable
            $dataDir = __DIR__ . '/../../data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0750, true);
            }
            $sqlitePath = $dataDir . '/ivc_irc_fallback.sqlite';
            $dsn = "sqlite:{$sqlitePath}";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
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

    /**
     * Prepare and execute a SQL query with bound parameters.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return PDOStatement
     * @throws PDOException
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array using a prepared statement.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return is_array($result) ? $result : null;
    }

    /**
     * Fetch all rows as associative arrays using a prepared statement.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single column scalar value using a prepared statement.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @param int $column
     * @return mixed
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = self::execute($sql, $params);
        $value = $stmt->fetchColumn($column);
        return $value !== false ? $value : null;
    }

    /**
     * Begin a PDO transaction.
     */
    public static function beginTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() || $pdo->beginTransaction();
    }

    /**
     * Commit active PDO transaction.
     */
    public static function commit(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->commit() : false;
    }

    /**
     * Rollback active PDO transaction.
     */
    public static function rollBack(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->rollBack() : false;
    }

    /**
     * Check if currently in transaction.
     */
    public static function inTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction();
    }

    /**
     * Execute a callback within a PDO transaction with automatic rollback on exception.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
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

            // Table for BOTSERV channel/global bot assignments
            "CREATE TABLE IF NOT EXISTS botserv_bots (
                target VARCHAR(64) NOT NULL,
                bot_nick VARCHAR(64) NOT NULL,
                service_name VARCHAR(64) NOT NULL,
                assigned_by VARCHAR(64) NOT NULL,
                assigned_at INT NOT NULL,
                PRIMARY KEY (target, bot_nick)
            );",

            // Table for channel access / roles (OP, VOICE, MEMBER)
            "CREATE TABLE IF NOT EXISTS channel_users (
                id {$autoInc},
                channel_name VARCHAR(64) NOT NULL,
                nickname VARCHAR(64) NOT NULL,
                role VARCHAR(16) DEFAULT 'MEMBER',
                added_at INT NOT NULL
            );",

            // Table for MEMOSERV stored memos
            "CREATE TABLE IF NOT EXISTS memoserv_memos (
                id {$autoInc},
                sender_nick VARCHAR(64) NOT NULL,
                recipient_nick VARCHAR(64) NOT NULL,
                message TEXT NOT NULL,
                sent_at INT NOT NULL,
                is_read TINYINT DEFAULT 0
            );",

            // Table for HOSTSERV assigned virtual hosts
            "CREATE TABLE IF NOT EXISTS hostserv_vhosts (
                nickname VARCHAR(64) PRIMARY KEY,
                vhost VARCHAR(128) NOT NULL,
                status VARCHAR(32) DEFAULT 'ACTIVE',
                assigned_at INT NOT NULL
            );",

            // Table for foreign services operating under different hosts
            "CREATE TABLE IF NOT EXISTS foreign_services (
                service_name VARCHAR(64) PRIMARY KEY,
                host VARCHAR(128) NOT NULL,
                api_endpoint VARCHAR(255) NOT NULL,
                status VARCHAR(32) DEFAULT 'ACTIVE',
                registered_at INT NOT NULL,
                last_ping INT NOT NULL,
                metadata TEXT NULL
            );",

            // Table for channel shared files with E2EE metadata
            "CREATE TABLE IF NOT EXISTS shared_files (
                id VARCHAR(64) PRIMARY KEY,
                channel_name VARCHAR(64) NOT NULL,
                sharer_client_id VARCHAR(64) NOT NULL,
                encrypted_metadata TEXT NOT NULL,
                cloud_link TEXT NULL,
                created_at INT NOT NULL
            );"
        ];

        foreach ($queries as $sql) {
            self::$pdo->exec($sql);
        }

        // Initialize default serverwide settings if not present
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
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

        $now = time();
        foreach ($defaults as $key => [$val, $desc]) {
            $count = (int)self::fetchColumn("SELECT COUNT(*) FROM irc_settings WHERE setting_key = :key", [':key' => $key]);
            if ($count === 0) {
                self::execute(
                    "INSERT INTO irc_settings (setting_key, setting_value, description, updated_at) VALUES (:key, :val, :desc, :time)",
                    [
                        ':key' => $key,
                        ':val' => $val,
                        ':desc' => $desc,
                        ':time' => $now
                    ]
                );
            }
        }
    }

    private static function registerDefaultForeignServices(): void
    {
        $db = self::getConnection();
        $time = time();
        $query = self::$driver === 'sqlite' ?
            "INSERT OR IGNORE INTO foreign_services (service_name, host, api_endpoint, status, registered_at, last_ping, metadata) VALUES
            ('GEMINI', 'ai.external-domain.org', 'https://api.external-domain.org/gemini', 'ACTIVE', $time, $time, 'Google Gemini Chat Bot'),
            ('CLAUDE', 'ai.external-domain.org', 'https://api.external-domain.org/claude', 'ACTIVE', $time, $time, 'Anthropic Claude Chat Bot'),
            ('CHATGPT', 'ai.external-domain.org', 'https://api.external-domain.org/chatgpt', 'ACTIVE', $time, $time, 'OpenAI ChatGPT Bot'),
            ('COPILOT', 'ai.external-domain.org', 'https://api.external-domain.org/copilot', 'ACTIVE', $time, $time, 'Microsoft Copilot Chat Bot')" :
            "INSERT IGNORE INTO foreign_services (service_name, host, api_endpoint, status, registered_at, last_ping, metadata) VALUES
            ('GEMINI', 'ai.external-domain.org', 'https://api.external-domain.org/gemini', 'ACTIVE', $time, $time, 'Google Gemini Chat Bot'),
            ('CLAUDE', 'ai.external-domain.org', 'https://api.external-domain.org/claude', 'ACTIVE', $time, $time, 'Anthropic Claude Chat Bot'),
            ('CHATGPT', 'ai.external-domain.org', 'https://api.external-domain.org/chatgpt', 'ACTIVE', $time, $time, 'OpenAI ChatGPT Bot'),
            ('COPILOT', 'ai.external-domain.org', 'https://api.external-domain.org/copilot', 'ACTIVE', $time, $time, 'Microsoft Copilot Chat Bot')";
        $db->exec($query);
    }

    public static function resetDatabase(): void
    {
        if (self::$pdo === null) {
            self::getConnection();
        }

        self::$pdo->exec("DELETE FROM nameserv_nicks;");
        self::$pdo->exec("DELETE FROM chanserv_channels;");
        self::$pdo->exec("DELETE FROM channel_users;");
        self::$pdo->exec("DELETE FROM memoserv_memos;");
        self::$pdo->exec("DELETE FROM hostserv_vhosts;");
        self::$pdo->exec("DELETE FROM foreign_services;");
        self::$pdo->exec("DELETE FROM shared_files;");
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
    }
}
