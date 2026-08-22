<?php

declare(strict_types=1);

namespace Fortress\Database;

use PDO;

/**
 * Data Access Repository for BotServ assignments.
 */
class BotServRepository
{
    public static function assignBot(string $target, string $botNick, string $serviceName, string $assignedBy): bool
    {
<<<<<<< HEAD
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO botserv_bots (target, bot_nick, service_name, assigned_by, assigned_at)
            VALUES (:tgt, :nick, :srv, :by, :time)
            ON DUPLICATE KEY UPDATE service_name = :srv, assigned_by = :by, assigned_at = :time
        ");

        if (Database::getDriver() === 'sqlite') {
            $stmt = $db->prepare("
                INSERT INTO botserv_bots (target, bot_nick, service_name, assigned_by, assigned_at)
                VALUES (:tgt, :nick, :srv, :by, :time)
                ON CONFLICT(target, bot_nick) DO UPDATE SET service_name = :srv, assigned_by = :by, assigned_at = :time
            ");
        }

        return $stmt->execute([
            ':tgt' => strtoupper($target),
            ':nick' => $botNick,
            ':srv' => $serviceName,
            ':by' => $assignedBy,
            ':time' => time()
        ]);
=======
        $coll = Database::getCollection('botserv_bots');
        $doc = [
            'target' => strtoupper($target),
            'bot_nick' => $botNick,
            'service_name' => $serviceName,
            'assigned_by' => $assignedBy,
            'assigned_at' => time()
        ];
        
        // upsert
        $coll->updateOne(
            [
                'target' => strtoupper($target),
                'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
            ],
            ['$set' => $doc],
            ['upsert' => true]
        );
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function unassignBot(string $target, string $botNick): bool
    {
<<<<<<< HEAD
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM botserv_bots WHERE target = :tgt AND LOWER(bot_nick) = LOWER(:nick)");
        return $stmt->execute([':tgt' => strtoupper($target), ':nick' => $botNick]);
=======
        $coll = Database::getCollection('botserv_bots');
        $coll->deleteOne([
            'target' => strtoupper($target),
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function getAssignedBot(string $target, string $botNick): ?string
    {
<<<<<<< HEAD
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT service_name FROM botserv_bots WHERE target = :tgt AND LOWER(bot_nick) = LOWER(:nick)");
        $stmt->execute([':tgt' => strtoupper($target), ':nick' => $botNick]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : null;
=======
        $coll = Database::getCollection('botserv_bots');
        $row = $coll->findOne([
            'target' => strtoupper($target),
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        return $row !== null ? (string)$row['service_name'] : null;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function getAllBotsForTarget(string $target): array
    {
<<<<<<< HEAD
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT bot_nick, service_name FROM botserv_bots WHERE target = :tgt OR target = 'GLOBAL'");
        $stmt->execute([':tgt' => strtoupper($target)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
=======
        $coll = Database::getCollection('botserv_bots');
        $rows = $coll->find([
            '$or' => [
                ['target' => strtoupper($target)],
                ['target' => 'GLOBAL']
            ]
        ]);
        return $rows;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function resolveBotService(string $channel, string $botNick): ?string
    {
<<<<<<< HEAD
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT service_name FROM botserv_bots WHERE (target = :chan OR target = 'GLOBAL') AND LOWER(bot_nick) = LOWER(:nick) ORDER BY target DESC LIMIT 1");
        $stmt->execute([':chan' => strtoupper($channel), ':nick' => $botNick]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : null;
=======
        $coll = Database::getCollection('botserv_bots');
        $row = $coll->findOne([
            '$or' => [
                ['target' => strtoupper($channel)],
                ['target' => 'GLOBAL']
            ],
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        return $row !== null ? (string)$row['service_name'] : null;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }
}
