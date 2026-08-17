<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * CHANSERV (Channel Service) IRC System Bot
 * Handles channel registration, operator management, topic control, passkeys, and channel modes.
 */
class ChanServ
{
    public const SERVICE_NAME = 'CHANSERV';

    /**
     * Normalize channel name (ensure leading #)
     */
    public static function normalizeChannelName(string $channel): string
    {
        $channel = trim($channel);
        if ($channel === '') {
            return '';
        }
        if (!str_starts_with($channel, '#') && !str_starts_with($channel, '&')) {
            $channel = '#' . $channel;
        }
        return $channel;
    }

    /**
     * Register a channel
     */
    public static function register(string $channel, string $ownerNick, ?string $passkey = null): array
    {
        $channel = self::normalizeChannelName($channel);
        $ownerNick = trim($ownerNick);

        if (empty($channel) || empty($ownerNick)) {
            return ['success' => false, 'message' => 'CHANSERV: Valid channel name and owner nickname are required.'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)");
        $stmt->execute([':chan' => $channel]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is already registered."];
        }

        $now = time();
        $insert = $pdo->prepare("INSERT INTO chanserv_channels (channel_name, owner_nick, passkey, modes, registered_at) VALUES (:chan, :owner, :pass, '+t', :reg)");
        $success = $insert->execute([
            ':chan' => $channel,
            ':owner' => $ownerNick,
            ':pass' => $passkey,
            ':reg' => $now
        ]);

        if ($success) {
            // Assign OP role to channel owner
            self::setRole($channel, $ownerNick, 'OP');
            return ['success' => true, 'message' => "CHANSERV: Channel '{$channel}' successfully registered to owner '{$ownerNick}'."];
        }

        return ['success' => false, 'message' => 'CHANSERV: Channel registration failed.'];
    }

    /**
     * Assign OP role to user in channel
     */
    public static function op(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant OP status."];
        }

        self::setRole($channel, $targetNick, 'OP');
        return ['success' => true, 'message' => "CHANSERV: Granted OP status (+o) to '{$targetNick}' in {$channel}."];
    }

    /**
     * Remove OP role from user in channel
     */
    public static function deop(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove OP status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed OP status (-o) from '{$targetNick}' in {$channel}."];
    }

    /**
     * Set topic for channel
     */
    public static function setTopic(string $channel, string $topic, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);

        if (!empty($requesterNick) && self::isRegistered($channel) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. Only channel operators can set the topic for {$channel}."];
        }

        $pdo = Database::getConnection();
        if (self::isRegistered($channel)) {
            $stmt = $pdo->prepare("UPDATE chanserv_channels SET topic = :topic WHERE LOWER(channel_name) = LOWER(:chan)");
            $stmt->execute([':topic' => $topic, ':chan' => $channel]);
        }

        return ['success' => true, 'message' => "CHANSERV: Topic for {$channel} updated to: \"{$topic}\"", 'topic' => $topic];
    }

    /**
     * Get channel info
     */
    public static function getInfo(string $channel): array
    {
        $channel = self::normalizeChannelName($channel);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT channel_name, owner_nick, topic, modes, registered_at FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)");
        $stmt->execute([':chan' => $channel]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is not registered."];
        }

        $ops = self::getOperators($channel);
        $opsList = !empty($ops) ? implode(', ', $ops) : 'None';
        $topicStr = $row['topic'] ?? '(No topic set)';
        $regDate = date('Y-m-d H:i:s', (int)$row['registered_at']);

        $msg = "CHANSERV Info for {$row['channel_name']}:\n" .
               "• Owner: {$row['owner_nick']}\n" .
               "• Registered: {$regDate}\n" .
               "• Modes: {$row['modes']}\n" .
               "• Topic: {$topicStr}\n" .
               "• Operators: {$opsList}";

        return ['success' => true, 'message' => $msg, 'data' => $row];
    }

    /**
     * Check if channel is registered
     */
    public static function isRegistered(string $channel): bool
    {
        $channel = self::normalizeChannelName($channel);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)");
        $stmt->execute([':chan' => $channel]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Set user role in channel
     */
    public static function setRole(string $channel, string $nickname, string $role): void
    {
        $channel = self::normalizeChannelName($channel);
        $nickname = trim($nickname);
        $role = strtoupper(trim($role));
        $now = time();

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND LOWER(nickname) = LOWER(:nick)");
        $stmt->execute([':chan' => $channel, ':nick' => $nickname]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            $update = $pdo->prepare("UPDATE channel_users SET role = :role, added_at = :time WHERE LOWER(channel_name) = LOWER(:chan) AND LOWER(nickname) = LOWER(:nick)");
            $update->execute([':role' => $role, ':time' => $now, ':chan' => $channel, ':nick' => $nickname]);
        } else {
            $insert = $pdo->prepare("INSERT INTO channel_users (channel_name, nickname, role, added_at) VALUES (:chan, :nick, :role, :time)");
            $insert->execute([':chan' => $channel, ':nick' => $nickname, ':role' => $role, ':time' => $now]);
        }
    }

    /**
     * Check if user is OP in channel
     */
    public static function isOp(string $channel, string $nickname): bool
    {
        $channel = self::normalizeChannelName($channel);
        $nickname = trim($nickname);
        $pdo = Database::getConnection();

        // Check if owner
        $stmtOwner = $pdo->prepare("SELECT owner_nick FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)");
        $stmtOwner->execute([':chan' => $channel]);
        $owner = $stmtOwner->fetchColumn();
        if ($owner && strcasecmp((string)$owner, $nickname) === 0) {
            return true;
        }

        // Check role table
        $stmtRole = $pdo->prepare("SELECT role FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND LOWER(nickname) = LOWER(:nick)");
        $stmtRole->execute([':chan' => $channel, ':nick' => $nickname]);
        $role = $stmtRole->fetchColumn();

        return $role && strtoupper((string)$role) === 'OP';
    }

    /**
     * Get list of operators in a channel
     *
     * @return array<int, string>
     */
    public static function getOperators(string $channel): array
    {
        $channel = self::normalizeChannelName($channel);
        $pdo = Database::getConnection();

        $ops = [];
        $stmtOwner = $pdo->prepare("SELECT owner_nick FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)");
        $stmtOwner->execute([':chan' => $channel]);
        $owner = $stmtOwner->fetchColumn();
        if ($owner) {
            $ops[] = (string)$owner;
        }

        $stmtRoles = $pdo->prepare("SELECT nickname FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND UPPER(role) = 'OP'");
        $stmtRoles->execute([':chan' => $channel]);
        while ($nick = $stmtRoles->fetchColumn()) {
            if (!in_array($nick, $ops, true)) {
                $ops[] = (string)$nick;
            }
        }

        return $ops;
    }

    /**
     * List registered channels
     *
     * @return array<int, array{channel_name: string, owner_nick: string, topic: string|null, registered_at: int}>
     */
    public static function listChannels(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT channel_name, owner_nick, topic, registered_at FROM chanserv_channels ORDER BY registered_at DESC");
        return $stmt->fetchAll();
    }
}
