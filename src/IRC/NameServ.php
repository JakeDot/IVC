<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * NAMESERV (Nickname Service) IRC System Bot
 * Handles user nickname registration, identification, and information queries stored in MySQL.
 */
class NameServ
{
    public const SERVICE_NAME = 'NAMESERV';

    /**
     * Register a nickname
     */
    public static function register(string $nickname, string $password, ?string $email = null): array
    {
        $nickname = trim($nickname);
        if (empty($nickname) || empty($password)) {
            return ['success' => false, 'message' => 'NAMESERV: Nickname and password are required.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' is already registered."];
        }

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $now = time();

        $insert = $pdo->prepare("INSERT INTO nameserv_nicks (nickname, password_hash, email, registered_at, last_seen, is_identified) VALUES (:nick, :hash, :email, :reg, :last, 1)");
        $success = $insert->execute([
            ':nick' => $nickname,
            ':hash' => $passHash,
            ':email' => $email,
            ':reg' => $now,
            ':last' => $now
        ]);

        if ($success) {
            return ['success' => true, 'message' => "NAMESERV: Nickname '{$nickname}' successfully registered and identified."];
        }

        return ['success' => false, 'message' => "NAMESERV: Registration failed due to database error."];
    }

    /**
     * Identify a nickname with password
     */
    public static function identify(string $nickname, string $password): array
    {
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT password_hash FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, (string)$hash)) {
            return ['success' => false, 'message' => 'NAMESERV: Password verification failed. Access denied.'];
        }

        $now = time();
        $update = $pdo->prepare("UPDATE nameserv_nicks SET is_identified = 1, last_seen = :time WHERE LOWER(nickname) = LOWER(:nick)");
        $update->execute([':time' => $now, ':nick' => $nickname]);

        return ['success' => true, 'message' => "NAMESERV: Password accepted. Nickname '{$nickname}' identified."];
    }

    /**
     * Get info for a registered nickname
     */
    public static function getInfo(string $nickname): array
    {
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT nickname, email, registered_at, last_seen, is_identified FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => $nickname]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' is not registered."];
        }

        $regDate = date('Y-m-d H:i:s', (int)$row['registered_at']);
        $lastSeenDate = date('Y-m-d H:i:s', (int)$row['last_seen']);
        $identifiedStr = $row['is_identified'] ? 'Yes' : 'No';

        $msg = "NAMESERV Information for {$row['nickname']}:\n" .
               "• Registered: {$regDate}\n" .
               "• Last Seen: {$lastSeenDate}\n" .
               "• Currently Identified: {$identifiedStr}";

        return ['success' => true, 'message' => $msg, 'data' => $row];
    }

    /**
     * Check if a nickname is registered
     */
    public static function isRegistered(string $nickname): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => trim($nickname)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if nickname is identified
     */
    public static function isIdentified(string $nickname): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT is_identified FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':nick' => trim($nickname)]);
        return (bool)$stmt->fetchColumn();
    }
}
