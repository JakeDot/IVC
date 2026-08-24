<?php

declare(strict_types=1);

namespace cx\ivc\IRC;

use cx\ivc\Database\Database;
use PDO;

/**
 * MEMOSERV (Memo Service) IRC System Bot
 * Handles sending, reading, listing, and deleting memos stored in MySQL/SQLite database.
 */
class MemoServ
{
    public const SERVICE_NAME = 'MEMOSERV';

    /**
     * Send a memo to a target user nickname
     */
    public static function send(string $senderNick, string $recipientNick, string $message): array
    {
        $senderNick = trim($senderNick);
        $recipientNick = trim($recipientNick);
        $message = trim($message);

        if (empty($senderNick) || empty($recipientNick) || empty($message)) {
            return ['success' => false, 'message' => 'MEMOSERV: Sender, recipient, and message content are required. Usage: /msg MEMOSERV SEND <nick> <message>'];
        }

        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("INSERT INTO memoserv_memos (sender_nick, recipient_nick, message, sent_at, is_read) VALUES (:sender, :recipient, :msg, :time, 0)");
        $success = $stmt->execute([
            ':sender' => $senderNick,
            ':recipient' => $recipientNick,
            ':msg' => $message,
            ':time' => $now
        ]);

        if ($success) {
            return [
                'success' => true,
                'message' => "MEMOSERV: Memo successfully sent to '{$recipientNick}'."
            ];
        }

        return ['success' => false, 'message' => 'MEMOSERV: Failed to send memo due to database error.'];
    }

    /**
     * List memos for a user nickname
     */
    public static function listMemos(string $recipientNick): array
    {
        $recipientNick = trim($recipientNick);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, sender_nick, sent_at, is_read FROM memoserv_memos WHERE LOWER(recipient_nick) = LOWER(:nick) ORDER BY id ASC");
        $stmt->execute([':nick' => $recipientNick]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return ['success' => true, 'message' => "MEMOSERV: You have no memos.", 'memos' => []];
        }

        $lines = ["MEMOSERV Memo List for {$recipientNick}:"];
        foreach ($rows as $index => $row) {
            $num = $index + 1;
            $status = $row['is_read'] ? '[Read]' : '[NEW]';
            $date = date('Y-m-d H:i', (int)$row['sent_at']);
            $lines[] = "  #{$num} (ID: {$row['id']}) {$status} From: {$row['sender_nick']} ({$date})";
        }

        return [
            'success' => true,
            'message' => implode("\n", $lines),
            'memos' => $rows
        ];
    }

    /**
     * Read a memo by list index (1-based) or ID for a user
     */
    public static function read(string $recipientNick, int $memoNum): array
    {
        $recipientNick = trim($recipientNick);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, sender_nick, message, sent_at, is_read FROM memoserv_memos WHERE LOWER(recipient_nick) = LOWER(:nick) ORDER BY id ASC");
        $stmt->execute([':nick' => $recipientNick]);
        $memos = $stmt->fetchAll();

        if (empty($memos)) {
            return ['success' => false, 'message' => "MEMOSERV: You have no memos."];
        }

        $target = null;
        if (isset($memos[$memoNum - 1])) {
            $target = $memos[$memoNum - 1];
        } else {
            foreach ($memos as $m) {
                if ((int)$m['id'] === $memoNum) {
                    $target = $m;
                    break;
                }
            }
        }

        if (!$target) {
            return ['success' => false, 'message' => "MEMOSERV: Memo #{$memoNum} not found."];
        }

        // Mark as read
        $update = $pdo->prepare("UPDATE memoserv_memos SET is_read = 1 WHERE id = :id");
        $update->execute([':id' => $target['id']]);

        $date = date('Y-m-d H:i:s', (int)$target['sent_at']);
        $msg = "MEMOSERV Memo from {$target['sender_nick']} ({$date}):\n{$target['message']}";

        return ['success' => true, 'message' => $msg, 'memo' => $target];
    }

    /**
     * Delete a memo by list index (1-based) or ID for a user
     */
    public static function delete(string $recipientNick, int $memoNum): array
    {
        $recipientNick = trim($recipientNick);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM memoserv_memos WHERE LOWER(recipient_nick) = LOWER(:nick) ORDER BY id ASC");
        $stmt->execute([':nick' => $recipientNick]);
        $memos = $stmt->fetchAll();

        if (empty($memos)) {
            return ['success' => false, 'message' => "MEMOSERV: You have no memos."];
        }

        $targetId = null;
        if (isset($memos[$memoNum - 1])) {
            $targetId = (int)$memos[$memoNum - 1]['id'];
        } else {
            foreach ($memos as $m) {
                if ((int)$m['id'] === $memoNum) {
                    $targetId = (int)$m['id'];
                    break;
                }
            }
        }

        if ($targetId === null) {
            return ['success' => false, 'message' => "MEMOSERV: Memo #{$memoNum} not found."];
        }

        $del = $pdo->prepare("DELETE FROM memoserv_memos WHERE id = :id AND LOWER(recipient_nick) = LOWER(:nick)");
        $del->execute([':id' => $targetId, ':nick' => $recipientNick]);

        return ['success' => true, 'message' => "MEMOSERV: Memo #{$memoNum} deleted."];
    }

    /**
     * Get unread memo count for user
     */
    public static function getUnreadCount(string $recipientNick): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM memoserv_memos WHERE LOWER(recipient_nick) = LOWER(:nick) AND is_read = 0");
        $stmt->execute([':nick' => trim($recipientNick)]);
        return (int)$stmt->fetchColumn();
    }
}
