<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\UserNick;

/**
 * Data Access Repository for registered nicknames (nameserv_nicks).
 */
class UserNickRepository
{
    /**
     * Find user nick record by nickname (case-insensitive).
     */
    public static function findByNickname(string $nickname): ?UserNick
    {
        $row = Database::fetchOne(
            "SELECT nickname, password_hash, email, registered_at, last_seen, is_identified FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
            [':nick' => trim($nickname)]
        );

        return $row !== null ? UserNick::fromArray($row) : null;
    }

    /**
     * Save (insert or update) a user nickname record.
     */
    public static function save(UserNick $userNick): bool
    {
        $exists = self::exists($userNick->getNickname());
        $now = time();

        if ($exists) {
            $stmt = Database::execute(
                "UPDATE nameserv_nicks SET password_hash = :hash, email = :email, last_seen = :last, is_identified = :id WHERE LOWER(nickname) = LOWER(:nick)",
                [
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':last' => $userNick->getLastSeen(),
                    ':id' => $userNick->isIdentified() ? 1 : 0,
                    ':nick' => $userNick->getNickname()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO nameserv_nicks (nickname, password_hash, email, registered_at, last_seen, is_identified) VALUES (:nick, :hash, :email, :reg, :last, :id)",
                [
                    ':nick' => $userNick->getNickname(),
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':reg' => $userNick->getRegisteredAt(),
                    ':last' => $now,
                    ':id' => $userNick->isIdentified() ? 1 : 0
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Update identification status & last seen timestamp.
     */
    public static function updateIdentification(string $nickname, bool $isIdentified, ?int $lastSeen = null): bool
    {
        $time = $lastSeen ?? time();
        $stmt = Database::execute(
            "UPDATE nameserv_nicks SET is_identified = :id, last_seen = :time WHERE LOWER(nickname) = LOWER(:nick)",
            [
                ':id' => $isIdentified ? 1 : 0,
                ':time' => $time,
                ':nick' => trim($nickname)
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a nickname exists.
     */
    public static function exists(string $nickname): bool
    {
        $count = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
            [':nick' => trim($nickname)]
        );

        return $count > 0;
    }
}
