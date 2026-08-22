<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * HOSTSERV (Virtual Host Service) IRC System Bot
 * Handles user virtual host (vhost) requests, assignments, activation, and status queries.
 */
class HostServ
{
    public const SERVICE_NAME = 'HOSTSERV';

    /**
     * Request or set a virtual host (vhost) for a user
     */
    public static function requestVhost(string $nickname, string $vhost): array
    {
        $nickname = trim($nickname);
        $vhost = trim($vhost);

        if (empty($nickname) || empty($vhost)) {
            return ['success' => false, 'message' => 'HOSTSERV: Nickname and virtual host string are required. Usage: /msg HOSTSERV REQUEST <vhost>'];
        }

        // Basic sanitize vhost format
        if (!preg_match('/^[a-zA-Z0-9\.\-\_\/]+$/', $vhost)) {
            return ['success' => false, 'message' => 'HOSTSERV: Invalid vhost format. Only alphanumeric chars, dots, dashes, slashes, and underscores allowed.'];
        }

        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostserv_vhosts WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            $update = $pdo->prepare("UPDATE hostserv_vhosts SET vhost = :vhost, status = 'ACTIVE', assigned_at = :time WHERE LOWER(nickname) = LOWER(:nick)");
            $update->execute([':vhost' => $vhost, ':time' => $now, ':nick' => $nickname]);
        } else {
            $insert = $pdo->prepare("INSERT INTO hostserv_vhosts (nickname, vhost, status, assigned_at) VALUES (:nick, :vhost, 'ACTIVE', :time)");
            $insert->execute([':nick' => $nickname, ':vhost' => $vhost, ':time' => $now]);
        }

        return [
            'success' => true,
            'message' => "HOSTSERV: Virtual host '{$vhost}' assigned and activated for nickname '{$nickname}'."
        ];
    }

    /**
     * Set vhost status (ON or OFF)
     */
    public static function setVhostStatus(string $nickname, bool $enabled): array
    {
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT vhost FROM hostserv_vhosts WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        $vhost = $stmt->fetchColumn();

        if (!$vhost) {
            return ['success' => false, 'message' => "HOSTSERV: No virtual host found for nickname '{$nickname}'."];
        }

        $status = $enabled ? 'ACTIVE' : 'OFF';
        $update = $pdo->prepare("UPDATE hostserv_vhosts SET status = :status WHERE LOWER(nickname) = LOWER(:nick)");
        $update->execute([':status' => $status, ':nick' => $nickname]);

        $stateMsg = $enabled ? "activated" : "deactivated";
        return [
            'success' => true,
            'message' => "HOSTSERV: Virtual host '{$vhost}' for '{$nickname}' has been {$stateMsg}."
        ];
    }

    /**
     * Get vhost info for a user
     */
    public static function getVhostInfo(string $nickname): array
    {
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT nickname, vhost, status, assigned_at FROM hostserv_vhosts WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => "HOSTSERV: No virtual host registered for '{$nickname}'."];
        }

        $date = date('Y-m-d H:i:s', (int)$row['assigned_at']);
        $msg = "HOSTSERV Vhost Info for {$row['nickname']}:\n" .
               "• Virtual Host: {$row['vhost']}\n" .
               "• Status: {$row['status']}\n" .
               "• Assigned: {$date}";

        return ['success' => true, 'message' => $msg, 'data' => $row];
    }

    /**
     * Return user active vhost or null
     */
    public static function getActiveVhost(string $nickname): ?string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT vhost FROM hostserv_vhosts WHERE LOWER(nickname) = LOWER(:nick) AND status = 'ACTIVE'");
        $stmt->execute([':nick' => trim($nickname)]);
        $res = $stmt->fetchColumn();
        return $res ? (string)$res : null;
    }
}
