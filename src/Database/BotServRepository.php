<?php

declare(strict_types=1);

namespace cx\ivc\Database;

use PDO;

/**
 * Data Access Repository for BotServ assignments.
 */
class BotServRepository
{
    public static function assignBot(string $target, string $botNick, string $serviceName, string $assignedBy): bool
    {
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
    }

    public static function unassignBot(string $target, string $botNick): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM botserv_bots WHERE target = :tgt AND LOWER(bot_nick) = LOWER(:nick)");
        return $stmt->execute([':tgt' => strtoupper($target), ':nick' => $botNick]);
    }

    public static function getAssignedBot(string $target, string $botNick): ?string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT service_name FROM botserv_bots WHERE target = :tgt AND LOWER(bot_nick) = LOWER(:nick)");
        $stmt->execute([':tgt' => strtoupper($target), ':nick' => $botNick]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : null;
    }

    public static function getAllBotsForTarget(string $target): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT bot_nick, service_name FROM botserv_bots WHERE target = :tgt OR target = 'GLOBAL'");
        $stmt->execute([':tgt' => strtoupper($target)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function resolveBotService(string $channel, string $botNick): ?string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT service_name FROM botserv_bots WHERE (target = :chan OR target = 'GLOBAL') AND LOWER(bot_nick) = LOWER(:nick) ORDER BY target DESC LIMIT 1");
        $stmt->execute([':chan' => strtoupper($channel), ':nick' => $botNick]);
        $res = $stmt->fetchColumn();
        return $res !== false ? (string)$res : null;
    }
}
