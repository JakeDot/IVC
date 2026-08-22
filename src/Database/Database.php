<?php
declare(strict_types=1);

namespace Fortress\Database;

use Throwable;


class MockPDOStatement {
    private $sql;
    private $results = [];
    private $index = 0;
    
    public function __construct($sql) { $this->sql = $sql; }
    
    public function execute($params = []) {
        if (strtoupper(substr(trim($this->sql), 0, 6)) === 'SELECT' || strtoupper(substr(trim($this->sql), 0, 4)) === 'PRAG') {
            $this->results = Database::fetchAll($this->sql, $params ?? []);
            $this->index = 0;
            return true;
        } else {
            $this->rowCountValue = Database::execute($this->sql, $params ?? []);
            return true;
        }
    }
    
    
    public $rowCountValue = 0;
    public function rowCount() { return $this->rowCountValue; }

    public function fetch() {
        if (!isset($this->results[$this->index])) return false;
        return $this->results[$this->index++];
    }
    
    public function fetchAll() {
        return $this->results;
    }
    
    public function fetchColumn() {
        $row = $this->fetch();
        return $row ? array_values((array)$row)[0] : false;
    }
}

class MockExecutionResult
{
    private int $count;

    public function __construct(int $count)
    {
        $this->count = $count;
    }

    public function rowCount(): int
    {
        return $this->count;
    }

    public function getChanges(): int
    {
        return $this->count;
    }

    public function __toString(): string
    {
        return (string)$this->count;
    }
}

class MockPDO {
    public function prepare($sql) { return new MockPDOStatement($sql); }
    public function exec($sql) { return Database::execute($sql); }
    public function query($sql) { 
        $stmt = new MockPDOStatement($sql);
        $stmt->execute();
        return $stmt;
    }
    public function beginTransaction() { return Database::beginTransaction(); }
    public function commit() { return Database::commit(); }
    public function rollBack() { return Database::rollBack(); }
    public function inTransaction() { return Database::inTransaction(); }
}

class Database
{
    private static $js = null;
    private static string $driver = 'sqlite';
    private static array $collections = [];

    public static function getDriver(): string
    {
        return self::$driver;
    }

    public static function setDriver(string $driver): void
    {
        self::$driver = strtolower(trim($driver));
    }

    public static function getCollection(string $name): MongoCollection
    {
        if (!isset(self::$collections[$name])) {
            self::$collections[$name] = new MongoCollection($name);
        }
        return self::$collections[$name];
    }
    
    private static $mockPdo = null;
    public static function getConnection() {
        if (self::$driver === 'postgres' || self::$driver === 'pgsql') {
            $pg = PostgresConnector::getConnection();
            if ($pg !== null) {
                return $pg;
            }
        }
        if (self::$mockPdo === null) self::$mockPdo = new MockPDO();
        return self::$mockPdo;
    }


    private static function initJs()
    {
        if (self::$js === null) {
            self::$js = new \vrzno();
        }
    }

    public static function execute(string $sql, array $params = []): MockExecutionResult
    {
        self::initJs();
        $count = (int)self::$js->executeSql($sql, json_encode($params));
        return new MockExecutionResult($count);
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        self::initJs();
        $json = (string)self::$js->fetchAllSql($sql, json_encode($params));
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = self::fetchAll($sql, $params);
        return empty($rows) ? null : $rows[0];
    }

    public static function fetchColumn(string $sql, array $params = [])
    {
        $row = self::fetchOne($sql, $params);
        return $row ? array_values($row)[0] : false;
    }

    public static function beginTransaction(): bool
    {
        self::initJs();
        self::$js->executeSql("BEGIN TRANSACTION", (object)[]);
        return true;
    }

    public static function commit(): bool
    {
        self::initJs();
        self::$js->executeSql("COMMIT", (object)[]);
        return true;
    }

    public static function rollBack(): bool
    {
        self::initJs();
        self::$js->executeSql("ROLLBACK", (object)[]);
        return true;
    }

    public static function inTransaction(): bool
    {
        // Simple mock
        return false;
    }

    public static function initialize(): void
    {
        self::initializeSchema();
    }

    private static function initializeSchema(): void
    {
        self::initJs();
        $autoInc = 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $queries = [
            "CREATE TABLE IF NOT EXISTS irc_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TEXT NOT NULL,
                description TEXT NULL,
                updated_at INT NOT NULL
            );",
            "CREATE TABLE IF NOT EXISTS nameserv_nicks (
                nickname VARCHAR(64) PRIMARY KEY,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(128) NULL,
                registered_at INT NOT NULL,
                last_seen INT NOT NULL,
                is_identified TINYINT DEFAULT 0,
                role VARCHAR(16) DEFAULT 'USER',
                subscription_tier VARCHAR(64) NULL,
                subscription_status VARCHAR(32) DEFAULT 'none',
                subscription_expires_at INT DEFAULT 0,
                custom_domain VARCHAR(128) NULL
            );",
            "CREATE TABLE IF NOT EXISTS chanserv_channels (
                channel_name VARCHAR(64) PRIMARY KEY,
                owner_nick VARCHAR(64) NOT NULL,
                registered_at INT NOT NULL,
                topic TEXT NULL,
                modes VARCHAR(32) DEFAULT '+nt',
                passkey VARCHAR(64) NULL,
                subscription_tier VARCHAR(64) NULL,
                subscription_status VARCHAR(32) DEFAULT 'none',
                subscription_expires_at INT DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS botserv_bots (
                target VARCHAR(64) NOT NULL,
                bot_nick VARCHAR(64) NOT NULL,
                service_name VARCHAR(64) NOT NULL,
                assigned_by VARCHAR(64) NOT NULL,
                assigned_at INT NOT NULL,
                PRIMARY KEY (target, bot_nick)
            );",
            "CREATE TABLE IF NOT EXISTS channel_users (
                id {$autoInc},
                channel_name VARCHAR(64) NOT NULL,
                nickname VARCHAR(64) NOT NULL,
                role VARCHAR(16) DEFAULT 'MEMBER',
                added_at INT NOT NULL
            );",
            "CREATE TABLE IF NOT EXISTS memoserv_memos (
                id {$autoInc},
                sender_nick VARCHAR(64) NOT NULL,
                recipient_nick VARCHAR(64) NOT NULL,
                message TEXT NOT NULL,
                sent_at INT NOT NULL,
                is_read TINYINT DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS hostserv_vhosts (
                nickname VARCHAR(64) PRIMARY KEY,
                vhost VARCHAR(128) NOT NULL,
                status VARCHAR(32) DEFAULT 'ACTIVE',
                assigned_at INT NOT NULL
            );",
            "CREATE TABLE IF NOT EXISTS foreign_services (
                service_name VARCHAR(64) PRIMARY KEY,
                host VARCHAR(128) NOT NULL,
                api_endpoint VARCHAR(255) NOT NULL,
                status VARCHAR(32) DEFAULT 'ACTIVE',
                registered_at INT NOT NULL,
                last_ping INT NOT NULL,
                metadata TEXT NULL
            );",
            "CREATE TABLE IF NOT EXISTS shared_files (
                id VARCHAR(64) PRIMARY KEY,
                channel_name VARCHAR(64) NOT NULL,
                sharer_client_id VARCHAR(64) NOT NULL,
                encrypted_metadata TEXT NOT NULL,
                cloud_link TEXT NULL,
                created_at INT NOT NULL
            );",
            "CREATE TABLE IF NOT EXISTS subscriptions (
                id VARCHAR(64) PRIMARY KEY,
                target_type VARCHAR(16) NOT NULL,
                target_name VARCHAR(64) NOT NULL,
                subscriber_nick VARCHAR(64) NOT NULL,
                plan_id VARCHAR(64) NOT NULL,
                stripe_customer_id VARCHAR(128) NULL,
                stripe_subscription_id VARCHAR(128) NULL,
                stripe_checkout_session_id VARCHAR(128) NULL,
                status VARCHAR(32) DEFAULT 'active',
                price_cents INT DEFAULT 499,
                currency VARCHAR(10) DEFAULT 'usd',
                expires_at INT NOT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL
            );"
        ];

        foreach ($queries as $sql) {
            self::$js->executeSql($sql, (object)[]);
        }

        self::ensureColumnsExist();
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
    }

    private static function ensureColumnsExist(): void
    {
        $columns = [
            'nameserv_nicks' => [
                'subscription_tier' => 'VARCHAR(64) NULL',
                'subscription_status' => "VARCHAR(32) DEFAULT 'none'",
                'subscription_expires_at' => 'INT DEFAULT 0',
                'custom_domain' => 'VARCHAR(128) NULL'
            ],
            'chanserv_channels' => [
                'subscription_tier' => 'VARCHAR(64) NULL',
                'subscription_status' => "VARCHAR(32) DEFAULT 'none'",
                'subscription_expires_at' => 'INT DEFAULT 0'
            ]
        ];

        foreach ($columns as $table => $cols) {
            foreach ($cols as $colName => $colDef) {
                try {
                    self::$js->executeSql("ALTER TABLE {$table} ADD COLUMN {$colName} {$colDef}", (object)[]);
                } catch (Throwable $e) {
                }
            }
        }
    }

    private static function seedDefaultSettings(): void
    {
        $defaults = [
            'network_name' => ['IVC-IRC Network', 'Name of the IRC Network'],
            'server_name' => ['fortress.ivc.local', 'Primary IRC Server Domain'],
            'motd' => ['Welcome to IVC WebRTC IRC Network! Secure, Anonymous & Encrypted.', 'Message of the day'],
            'max_channels_per_user' => ['10', 'Maximum channels a user can register'],
            'allow_anonymous' => ['1', 'Allow anonymous client connections'],
            'version' => ['2.0.0-IRC', 'Server Infrastructure Version'],
            'stripe_publishable_key' => ['pk_test_sample', 'Stripe Publishable Key'],
            'stripe_secret_key' => ['sk_test_sample', 'Stripe Secret Key'],
            'stripe_webhook_secret' => ['whsec_sample', 'Stripe Webhook Signing Secret']
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
        $time = time();
        $query = "INSERT OR IGNORE INTO foreign_services (service_name, host, api_endpoint, status, registered_at, last_ping, metadata) VALUES
            ('GEMINI', 'ai.external-domain.org', 'https://api.external-domain.org/gemini', 'ACTIVE', $time, $time, 'Google Gemini Chat Bot'),
            ('CLAUDE', 'ai.external-domain.org', 'https://api.external-domain.org/claude', 'ACTIVE', $time, $time, 'Anthropic Claude Chat Bot'),
            ('CHATGPT', 'ai.external-domain.org', 'https://api.external-domain.org/chatgpt', 'ACTIVE', $time, $time, 'OpenAI ChatGPT Bot'),
            ('COPILOT', 'ai.external-domain.org', 'https://api.external-domain.org/copilot', 'ACTIVE', $time, $time, 'Microsoft Copilot Chat Bot')";
        self::$js->executeSql($query, (object)[]);
    }

    public static function resetDatabase(): void
    {
        self::initJs();
        self::$js->executeSql("DELETE FROM nameserv_nicks;", (object)[]);
        self::$js->executeSql("DELETE FROM chanserv_channels;", (object)[]);
        self::$js->executeSql("DELETE FROM channel_users;", (object)[]);
        self::$js->executeSql("DELETE FROM memoserv_memos;", (object)[]);
        self::$js->executeSql("DELETE FROM hostserv_vhosts;", (object)[]);
        self::$js->executeSql("DELETE FROM foreign_services;", (object)[]);
        self::$js->executeSql("DELETE FROM shared_files;", (object)[]);
        self::$js->executeSql("DELETE FROM subscriptions;", (object)[]);
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
    }
}
