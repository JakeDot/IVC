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
            "SELECT nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at
             FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
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
                "UPDATE nameserv_nicks SET
                    password_hash = :hash,
                    email = :email,
                    last_seen = :last,
                    is_identified = :id,
                    subscription_tier = :stier,
                    subscription_status = :sstatus,
                    subscription_expires_at = :sexp
                 WHERE LOWER(nickname) = LOWER(:nick)",
                [
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':last' => $userNick->getLastSeen(),
                    ':id' => $userNick->isIdentified() ? 1 : 0,
                    ':stier' => $userNick->getSubscriptionTier(),
                    ':sstatus' => $userNick->getSubscriptionStatus(),
                    ':sexp' => $userNick->getSubscriptionExpiresAt(),
                    ':nick' => $userNick->getNickname()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO nameserv_nicks (nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at)
                 VALUES (:nick, :hash, :email, :reg, :last, :id, :stier, :sstatus, :sexp)",
                [
                    ':nick' => $userNick->getNickname(),
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':reg' => $userNick->getRegisteredAt(),
                    ':last' => $now,
                    ':id' => $userNick->isIdentified() ? 1 : 0,
                    ':stier' => $userNick->getSubscriptionTier(),
                    ':sstatus' => $userNick->getSubscriptionStatus(),
                    ':sexp' => $userNick->getSubscriptionExpiresAt()
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
     * Update nickname subscription details.
     */
    public static function updateSubscription(string $nickname, ?string $tier, string $status, int $expiresAt): bool
    {
        $stmt = Database::execute(
            "UPDATE nameserv_nicks SET subscription_tier = :tier, subscription_status = :status, subscription_expires_at = :exp WHERE LOWER(nickname) = LOWER(:nick)",
            [
                ':tier' => $tier,
                ':status' => strtolower(trim($status)),
                ':exp' => $expiresAt,
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

    /**
     * Find user nick records that have expired (last seen older than $expireSeconds) and are NOT active paid subscribers.
     *
     * @return UserNick[]
     */
    public static function findExpired(int $expireSeconds): array
    {
        $expireTime = time() - $expireSeconds;
        $now = time();
        $rows = Database::fetchAll(
            "SELECT nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at
             FROM nameserv_nicks
             WHERE last_seen < :expireTime
               AND NOT (subscription_status IN ('active', 'trialing') AND subscription_expires_at > :now)",
            [
                ':expireTime' => $expireTime,
                ':now' => $now
            ]
        );

        $expired = [];
        foreach ($rows as $row) {
            $expired[] = UserNick::fromArray($row);
        }

        return $expired;
    }

    /**
     * Delete user nick record by nickname.
     */
    public static function delete(string $nickname): bool
    {
        $stmt = Database::execute(
            "DELETE FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
            [':nick' => trim($nickname)]
        );

        return $stmt->rowCount() > 0;
    }
}
