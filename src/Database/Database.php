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
    private static string $driver = 'mongodb';
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
            'version' => ['2.0.0-IRC', 'Server Infrastructure Version'],
            'stripe_publishable_key' => ['pk_test_sample', 'Stripe Publishable Key'],
            'stripe_secret_key' => ['sk_test_sample', 'Stripe Secret Key'],
            'stripe_webhook_secret' => ['whsec_sample', 'Stripe Webhook Signing Secret']
        ];

        $now = time();
        foreach ($defaults as $key => [$val, $desc]) {
            $coll = self::getCollection("irc_settings");
            if ($coll->countDocuments(["setting_key" => $key]) === 0) {
                $coll->insertOne([
                    "setting_key" => $key,
                    "setting_value" => $val,
                    "description" => $desc,
                    "updated_at" => $now
                ]);
            }
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
        
        $coll = self::getCollection("foreign_services");
        if ($coll->countDocuments([]) === 0) {
            $coll->insertOne(["service_name" => "GEMINI", "host" => "ai.external-domain.org", "api_endpoint" => "https://api.external-domain.org/gemini", "status" => "ACTIVE", "registered_at" => $time, "last_ping" => $time, "metadata" => "Google Gemini Chat Bot"]);
            $coll->insertOne(["service_name" => "CLAUDE", "host" => "ai.external-domain.org", "api_endpoint" => "https://api.external-domain.org/claude", "status" => "ACTIVE", "registered_at" => $time, "last_ping" => $time, "metadata" => "Anthropic Claude Chat Bot"]);
            $coll->insertOne(["service_name" => "CHATGPT", "host" => "ai.external-domain.org", "api_endpoint" => "https://api.external-domain.org/chatgpt", "status" => "ACTIVE", "registered_at" => $time, "last_ping" => $time, "metadata" => "OpenAI ChatGPT Bot"]);
            $coll->insertOne(["service_name" => "COPILOT", "host" => "ai.external-domain.org", "api_endpoint" => "https://api.external-domain.org/copilot", "status" => "ACTIVE", "registered_at" => $time, "last_ping" => $time, "metadata" => "Microsoft Copilot Chat Bot"]);
        }

    }

    public static function resetDatabase(): void
    {
        self::getCollection("nameserv_nicks")->deleteMany([]);
        self::getCollection("chanserv_channels")->deleteMany([]);
        self::getCollection("channel_users")->deleteMany([]);
        self::getCollection("memoserv_memos")->deleteMany([]);
        self::getCollection("hostserv_vhosts")->deleteMany([]);
        self::getCollection("foreign_services")->deleteMany([]);
        self::getCollection("shared_files")->deleteMany([]);
        self::getCollection("subscriptions")->deleteMany([]);
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
    }
}
