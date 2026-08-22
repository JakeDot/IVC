<?php
<<<<<<< HEAD

=======
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
declare(strict_types=1);

namespace Fortress\Database;

<<<<<<< HEAD
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
=======
class MongoStatement
{
    private array $results;
    private int $affectedRows;
    private int $cursor = 0;

    public function __construct(array $results = [], int $affectedRows = 0)
    {
        $this->results = array_values($results);
        $this->affectedRows = $affectedRows;
    }

    public function execute(array $params = []): bool
    {
        return true;
    }

    public function fetch(int $mode = 2): mixed
    {
        if ($this->cursor < count($this->results)) {
            $row = $this->results[$this->cursor++];
            return is_array($row) ? $row : false;
        }
        return false;
    }

    public function fetchAll(int $mode = 2): array
    {
        return $this->results;
    }

    public function fetchColumn(int $columnNumber = 0): mixed
    {
        $row = $this->fetch();
        if ($row && is_array($row)) {
            $vals = array_values($row);
            return $vals[$columnNumber] ?? false;
        }
        return false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

class MongoPrepared
{
    private string $sql;
    private ?MongoStatement $stmt = null;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $this->stmt = Database::execute($this->sql, $params);
        return true;
    }

    public function fetch(int $mode = 2): mixed
    {
        if ($this->stmt === null) {
            $this->stmt = Database::execute($this->sql, []);
        }
        return $this->stmt->fetch($mode);
    }

    public function fetchAll(int $mode = 2): array
    {
        if ($this->stmt === null) {
            $this->stmt = Database::execute($this->sql, []);
        }
        return $this->stmt->fetchAll($mode);
    }

    public function fetchColumn(int $col = 0): mixed
    {
        if ($this->stmt === null) {
            $this->stmt = Database::execute($this->sql, []);
        }
        return $this->stmt->fetchColumn($col);
    }

    public function rowCount(): int
    {
        return $this->stmt ? $this->stmt->rowCount() : 0;
    }
}

class MongoConnection
{
    public function prepare(string $sql): MongoPrepared
    {
        return new MongoPrepared($sql);
    }

    public function query(string $sql): MongoStatement
    {
        return Database::execute($sql);
    }

    public function exec(string $sql): int
    {
        $stmt = Database::execute($sql);
        return $stmt->rowCount();
    }

    public function lastInsertId(?string $name = null): string
    {
        return '';
    }
}

class Database
{
    private static array $collections = [];
    private static ?MongoConnection $connection = null;

    public static function getDriver(): string
    {
        return 'mongodb';
    }

    public static function setDriver(string $driver): void
    {
    }

    public static function initialize(): void
    {
    }

    public static function resetDatabase(): void
    {
    }

    public static function getConnection(): MongoConnection
    {
        if (self::$connection === null) {
            self::$connection = new MongoConnection();
        }
        return self::$connection;
    }

    public static function getCollection(string $collectionName): MongoCollection
    {
        if (!isset(self::$collections[$collectionName])) {
            self::$collections[$collectionName] = new MongoCollection($collectionName);
        }
        return self::$collections[$collectionName];
    }

    private static function normalizeParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $k => $v) {
            $cleanKey = ltrim((string)$k, ':@$');
            $normalized[$cleanKey] = $v;
        }
        return $normalized;
    }

    public static function execute(string $sql, array $params = []): MongoStatement
    {
        $sqlClean = trim(preg_replace('/\s+/', ' ', $sql));
        $p = self::normalizeParams($params);

        // SELECT
        if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+([a-zA-Z0-9_]+)(.*)$/i', $sqlClean, $m)) {
            $selectCols = trim($m[1]);
            $table = trim($m[2]);
            $rest = trim($m[3]);

            $whereClause = '';
            $orderBy = '';
            $limit = 0;

            if (preg_match('/\bWHERE\s+(.+)$/i', $rest, $wm)) {
                $whereClause = trim($wm[1]);
                if (preg_match('/^(.*?)\s+ORDER\s+BY\s+(.+)$/i', $whereClause, $obm)) {
                    $whereClause = trim($obm[1]);
                    $orderBy = trim($obm[2]);
                }
                if (preg_match('/^(.*?)\s+LIMIT\s+(\d+)$/i', $whereClause, $lm)) {
                    $whereClause = trim($lm[1]);
                    $limit = (int)$lm[2];
                }
            } elseif (preg_match('/\bORDER\s+BY\s+(.+)$/i', $rest, $om)) {
                $orderBy = trim($om[1]);
                if (preg_match('/^(.*?)\s+LIMIT\s+(\d+)$/i', $orderBy, $lm)) {
                    $orderBy = trim($lm[1]);
                    $limit = (int)$lm[2];
                }
            } elseif (preg_match('/\bLIMIT\s+(\d+)$/i', $rest, $lm)) {
                $limit = (int)$lm[1];
            }
            if ($orderBy !== '' && preg_match('/^(.*?)\s+LIMIT\s+(\d+)$/i', $orderBy, $lm)) {
                $orderBy = trim($lm[1]);
                $limit = (int)$lm[2];
            }

            $coll = self::getCollection($table);
            $docs = $coll->find();

            // Filter
            if ($whereClause !== '') {
                $docs = array_values(array_filter($docs, function($doc) use ($whereClause, $p) {
                    return self::evalWhere($doc, $whereClause, $p);
                }));
            }

            // ORDER BY
            if ($orderBy !== '') {
                $orderParts = explode(',', $orderBy);
                usort($docs, function($a, $b) use ($orderParts) {
                    foreach ($orderParts as $part) {
                        $part = trim($part);
                        $dir = 1;
                        if (preg_match('/^([a-zA-Z0-9_]+)\s+(ASC|DESC)$/i', $part, $om)) {
                            $field = $om[1];
                            $dir = strtoupper($om[2]) === 'DESC' ? -1 : 1;
                        } else {
                            $field = $part;
                        }
                        $va = $a[$field] ?? null;
                        $vb = $b[$field] ?? null;
                        if ($va != $vb) {
                            return ($va > $vb ? 1 : -1) * $dir;
                        }
                    }
                    return 0;
                });
            }

            if ($limit > 0) {
                $docs = array_slice($docs, 0, $limit);
            }

            // Project columns
            if (stripos($selectCols, 'COUNT(*)') !== false) {
                return new MongoStatement([['COUNT(*)' => count($docs), 'count' => count($docs)]], count($docs));
            }

            if ($selectCols !== '*') {
                $colNames = array_map('trim', explode(',', $selectCols));
                $projected = [];
                foreach ($docs as $d) {
                    $row = [];
                    foreach ($colNames as $colExpr) {
                        if (preg_match('/^[\'"](.+?)[\'"]\s+AS\s+([a-zA-Z0-9_]+)$/i', $colExpr, $cm)) {
                            $row[$cm[2]] = $cm[1];
                        } elseif (preg_match('/^([a-zA-Z0-9_]+)\s+AS\s+([a-zA-Z0-9_]+)$/i', $colExpr, $cm)) {
                            $row[$cm[2]] = $d[$cm[1]] ?? null;
                        } else {
                            $colClean = trim($colExpr);
                            $row[$colClean] = $d[$colClean] ?? null;
                        }
                    }
                    $projected[] = $row;
                }
                return new MongoStatement($projected, count($projected));
            }

            return new MongoStatement($docs, count($docs));
        }

        // INSERT
        if (preg_match('/^INSERT\s+INTO\s+([a-zA-Z0-9_]+)\s*\((.+?)\)\s*VALUES\s*\((.+?)\)(.*)$/i', $sqlClean, $m)) {
            $table = trim($m[1]);
            $cols = array_map('trim', explode(',', $m[2]));
            $valTokens = array_map('trim', explode(',', $m[3]));
            $extra = isset($m[4]) ? trim($m[4]) : '';

            $doc = [];
            for ($i = 0; $i < count($cols); $i++) {
                $colName = $cols[$i];
                $token = $valTokens[$i] ?? null;
                if ($token !== null && str_starts_with($token, ':')) {
                    $pKey = ltrim($token, ':');
                    $doc[$colName] = $p[$pKey] ?? null;
                } elseif ($token !== null && preg_match('/^[\'"](.*)[\'"]$/', $token, $sm)) {
                    $doc[$colName] = $sm[1];
                } elseif ($token !== null && is_numeric($token)) {
                    $doc[$colName] = str_contains($token, '.') ? (float)$token : (int)$token;
                } else {
                    $doc[$colName] = null;
                }
            }

            $coll = self::getCollection($table);

            if (stripos($extra, 'ON DUPLICATE KEY UPDATE') !== false || stripos($extra, 'ON CONFLICT') !== false) {
                $filter = [];
                if (isset($doc['target']) && isset($doc['bot_nick'])) {
                    $filter = ['target' => $doc['target'], 'bot_nick' => $doc['bot_nick']];
                } elseif (isset($doc['channel_name']) && isset($doc['nickname'])) {
                    $filter = ['channel_name' => $doc['channel_name'], 'nickname' => $doc['nickname']];
                } elseif (isset($doc['channel_name'])) {
                    $filter = ['channel_name' => $doc['channel_name']];
                } elseif (isset($doc['nickname'])) {
                    $filter = ['nickname' => $doc['nickname']];
                } elseif (isset($doc['setting_key'])) {
                    $filter = ['setting_key' => $doc['setting_key']];
                } elseif (isset($doc['service_name'])) {
                    $filter = ['service_name' => $doc['service_name']];
                } elseif (isset($doc['id'])) {
                    $filter = ['id' => $doc['id']];
                }
                if (!empty($filter)) {
                    $coll->updateOne($filter, ['$set' => $doc], ['upsert' => true]);
                    return new MongoStatement([], 1);
                }
            }

            $coll->insertOne($doc);
            return new MongoStatement([], 1);
        }

        // UPDATE
        if (preg_match('/^UPDATE\s+([a-zA-Z0-9_]+)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+))?$/i', $sqlClean, $m)) {
            $table = trim($m[1]);
            $setClause = trim($m[2]);
            $whereClause = isset($m[3]) ? trim($m[3]) : '';

            $setPairs = explode(',', $setClause);
            $updateFields = [];
            foreach ($setPairs as $pair) {
                $parts = explode('=', $pair, 2);
                if (count($parts) === 2) {
                    $col = trim($parts[0]);
                    $valToken = trim($parts[1]);
                    if (str_starts_with($valToken, ':')) {
                        $pKey = ltrim($valToken, ':');
                        $updateFields[$col] = $p[$pKey] ?? null;
                    } elseif (preg_match('/^[\'"](.*)[\'"]$/', $valToken, $sm)) {
                        $updateFields[$col] = $sm[1];
                    } elseif (is_numeric($valToken)) {
                        $updateFields[$col] = str_contains($valToken, '.') ? (float)$valToken : (int)$valToken;
                    }
                }
            }

            $coll = self::getCollection($table);
            $docs = $coll->find();
            $matchedCount = 0;

            foreach ($docs as $d) {
                if ($whereClause === '' || self::evalWhere($d, $whereClause, $p)) {
                    $matchedCount++;
                    $idFilter = [];
                    if (isset($d['id'])) {
                        $idFilter['id'] = $d['id'];
                    } elseif (isset($d['channel_name']) && isset($d['nickname'])) {
                        $idFilter['channel_name'] = $d['channel_name'];
                        $idFilter['nickname'] = $d['nickname'];
                    } elseif (isset($d['channel_name'])) {
                        $idFilter['channel_name'] = $d['channel_name'];
                    } elseif (isset($d['nickname'])) {
                        $idFilter['nickname'] = $d['nickname'];
                    } elseif (isset($d['setting_key'])) {
                        $idFilter['setting_key'] = $d['setting_key'];
                    } elseif (isset($d['target']) && isset($d['bot_nick'])) {
                        $idFilter['target'] = $d['target'];
                        $idFilter['bot_nick'] = $d['bot_nick'];
                    }
                    if (!empty($idFilter)) {
                        $coll->updateOne($idFilter, ['$set' => $updateFields]);
                    }
                }
            }

            return new MongoStatement([], $matchedCount);
        }

        // DELETE
        if (preg_match('/^DELETE\s+FROM\s+([a-zA-Z0-9_]+)(?:\s+WHERE\s+(.+))?$/i', $sqlClean, $m)) {
            $table = trim($m[1]);
            $whereClause = isset($m[2]) ? trim($m[2]) : '';

            $coll = self::getCollection($table);
            if ($whereClause === '') {
                $cnt = $coll->countDocuments();
                $coll->deleteMany([]);
                return new MongoStatement([], $cnt);
            }

            $docs = $coll->find();
            $deletedCount = 0;
            foreach ($docs as $d) {
                if (self::evalWhere($d, $whereClause, $p)) {
                    $deletedCount++;
                    $idFilter = [];
                    if (isset($d['id'])) {
                        $idFilter['id'] = $d['id'];
                    } elseif (isset($d['channel_name']) && isset($d['nickname'])) {
                        $idFilter['channel_name'] = $d['channel_name'];
                        $idFilter['nickname'] = $d['nickname'];
                    } elseif (isset($d['channel_name'])) {
                        $idFilter['channel_name'] = $d['channel_name'];
                    } elseif (isset($d['nickname'])) {
                        $idFilter['nickname'] = $d['nickname'];
                    } elseif (isset($d['setting_key'])) {
                        $idFilter['setting_key'] = $d['setting_key'];
                    } elseif (isset($d['target']) && isset($d['bot_nick'])) {
                        $idFilter['target'] = $d['target'];
                        $idFilter['bot_nick'] = $d['bot_nick'];
                    }
                    if (!empty($idFilter)) {
                        $coll->deleteOne($idFilter);
                    }
                }
            }

            return new MongoStatement([], $deletedCount);
        }

        return new MongoStatement([], 0);
    }

    private static function evalWhere(array $doc, string $whereClause, array $params): bool
    {
        if (preg_match('/\bOR\b/i', $whereClause) && !preg_match('/^\(/', trim($whereClause))) {
            $orParts = preg_split('/\bOR\b/i', $whereClause);
            foreach ($orParts as $orPart) {
                if (self::evalWhere($doc, trim($orPart), $params)) {
                    return true;
                }
            }
            return false;
        }

        if (preg_match('/\bAND\b/i', $whereClause)) {
            $andParts = preg_split('/\bAND\b/i', $whereClause);
            foreach ($andParts as $andPart) {
                if (!self::evalWhere($doc, trim($andPart), $params)) {
                    return false;
                }
            }
            return true;
        }

        $clause = trim($whereClause);
        while (str_starts_with($clause, '(') && str_ends_with($clause, ')') && substr_count($clause, '(') === substr_count($clause, ')')) {
            // Check if outer parentheses actually wrap the whole expression
            $depth = 0;
            $wrapsAll = true;
            for ($i = 0; $i < strlen($clause) - 1; $i++) {
                if ($clause[$i] === '(') $depth++;
                elseif ($clause[$i] === ')') $depth--;
                if ($depth === 0) {
                    $wrapsAll = false;
                    break;
                }
            }
            if ($wrapsAll) {
                $clause = trim(substr($clause, 1, -1));
            } else {
                break;
            }
        }

        if (preg_match('/^(?:LOWER|UPPER)\s*\(\s*([a-zA-Z0-9_]+)\s*\)\s*=\s*(?:LOWER|UPPER)\s*\(\s*:([a-zA-Z0-9_]+)\s*\)$/i', $clause, $m)) {
            $col = $m[1];
            $pKey = $m[2];
            $val1 = strtolower((string)($doc[$col] ?? ''));
            $val2 = strtolower((string)($params[$pKey] ?? ''));
            return $val1 === $val2;
        }

        if (preg_match('/^(?:LOWER|UPPER)\s*\(\s*([a-zA-Z0-9_]+)\s*\)\s*=\s*:([a-zA-Z0-9_]+)$/i', $clause, $m)) {
            $col = $m[1];
            $pKey = $m[2];
            $val1 = strtolower((string)($doc[$col] ?? ''));
            $val2 = strtolower((string)($params[$pKey] ?? ''));
            return $val1 === $val2;
        }

        if (preg_match('/^(?:LOWER|UPPER)\s*\(\s*([a-zA-Z0-9_]+)\s*\)\s*=\s*[\'"](.*)[\'"]$/i', $clause, $m)) {
            $col = $m[1];
            $target = $m[2];
            $val1 = strtolower((string)($doc[$col] ?? ''));
            return $val1 === strtolower($target);
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*=\s*:([a-zA-Z0-9_]+)$/i', $clause, $m)) {
            $col = $m[1];
            $pKey = $m[2];
            $val1 = $doc[$col] ?? null;
            $val2 = $params[$pKey] ?? null;
            if ($val1 === null && $val2 === null) return true;
            if ($val1 === null || $val2 === null) return false;
            return (string)$val1 == (string)$val2;
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*=\s*[\'"](.*)[\'"]$/i', $clause, $m)) {
            $col = $m[1];
            $target = $m[2];
            return strtolower((string)($doc[$col] ?? '')) === strtolower($target);
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*=\s*(\d+)$/i', $clause, $m)) {
            $col = $m[1];
            $target = (int)$m[2];
            return (int)($doc[$col] ?? 0) === $target;
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*IN\s*\((.+?)\)$/i', $clause, $m)) {
            $col = $m[1];
            $items = array_map(function($i) {
                return strtolower(trim($i, " '\t\n\r\0\x0B\""));
            }, explode(',', $m[2]));
            $val = strtolower((string)($doc[$col] ?? ''));
            return in_array($val, $items, true);
        }

        return false;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $res = $stmt->fetch();
        return is_array($res) ? $res : null;
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }

<<<<<<< HEAD
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
                is_identified TINYINT DEFAULT 0,
                subscription_tier VARCHAR(64) NULL,
                subscription_status VARCHAR(32) DEFAULT 'none',
                subscription_expires_at INT DEFAULT 0
            );",

            // Table for CHANSERV registered channels
            "CREATE TABLE IF NOT EXISTS chanserv_channels (
                channel_name VARCHAR(64) PRIMARY KEY,
                owner_nick VARCHAR(64) NOT NULL,
                topic TEXT NULL,
                passkey VARCHAR(128) NULL,
                modes VARCHAR(32) DEFAULT '+t',
                registered_at INT NOT NULL,
                subscription_tier VARCHAR(64) NULL,
                subscription_status VARCHAR(32) DEFAULT 'none',
                subscription_expires_at INT DEFAULT 0
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
            );",

            // Table for paid subscriptions (nick, channel, server)
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
            self::$pdo->exec($sql);
        }

        self::ensureColumnsExist();

        // Initialize default serverwide settings if not present
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
    }

    private static function ensureColumnsExist(): void
    {
        if (self::$pdo === null) return;

        $columns = [
            'nameserv_nicks' => [
                'subscription_tier' => 'VARCHAR(64) NULL',
                'subscription_status' => "VARCHAR(32) DEFAULT 'none'",
                'subscription_expires_at' => 'INT DEFAULT 0'
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
                    self::$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$colName} {$colDef};");
                } catch (Throwable $e) {
                    // Column already exists or table handles it natively
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
        self::$pdo->exec("DELETE FROM subscriptions;");
        self::seedDefaultSettings();
        self::registerDefaultForeignServices();
=======
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchColumn();
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }
}
