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
        $cleanNick = trim($nickname);
        if ($cleanNick === '') {
            return null;
        }

        $row = Database::fetchOne(
            "SELECT nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at, custom_domain
             FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
            [':nick' => $cleanNick]
        );

        if ($row !== null) {
            return UserNick::fromArray($row);
        }

        $atPos = strrpos($cleanNick, '@');
        if ($atPos !== false && $atPos > 0) {
            $base = substr($cleanNick, 0, $atPos);
            if ($base !== '') {
                $row = Database::fetchOne(
                    "SELECT nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at, custom_domain
                     FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(:nick)",
                    [':nick' => $base]
                );
                if ($row !== null) {
                    return UserNick::fromArray($row);
                }
            }
        }

        return null;
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
                    subscription_expires_at = :sexp,
                    custom_domain = :cdom
                 WHERE LOWER(nickname) = LOWER(:nick)",
                [
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':last' => $userNick->getLastSeen(),
                    ':id' => $userNick->isIdentified() ? 1 : 0,
                    ':stier' => $userNick->getSubscriptionTier(),
                    ':sstatus' => $userNick->getSubscriptionStatus(),
                    ':sexp' => $userNick->getSubscriptionExpiresAt(),
                    ':cdom' => $userNick->getCustomDomain(),
                    ':nick' => $userNick->getNickname()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO nameserv_nicks (nickname, password_hash, email, registered_at, last_seen, is_identified, subscription_tier, subscription_status, subscription_expires_at, custom_domain)
                 VALUES (:nick, :hash, :email, :reg, :last, :id, :stier, :sstatus, :sexp, :cdom)",
                [
                    ':nick' => $userNick->getNickname(),
                    ':hash' => $userNick->getPasswordHash(),
                    ':email' => $userNick->getEmail(),
                    ':reg' => $userNick->getRegisteredAt(),
                    ':last' => $now,
                    ':id' => $userNick->isIdentified() ? 1 : 0,
                    ':stier' => $userNick->getSubscriptionTier(),
                    ':sstatus' => $userNick->getSubscriptionStatus(),
                    ':sexp' => $userNick->getSubscriptionExpiresAt(),
                    ':cdom' => $userNick->getCustomDomain()
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Update custom domain and identification status.
     */
    public static function updateDomain(string $nickname, ?string $domain): bool
    {
        $cleanNick = trim($nickname);
        $cleanDomain = $domain !== null ? trim($domain) : null;
        $atPos = strrpos($cleanNick, '@');
        $baseNick = ($atPos !== false && $atPos > 0) ? substr($cleanNick, 0, $atPos) : $cleanNick;

        $user = self::findByNickname($cleanNick);
        if ($user !== null) {
            $stmt = Database::execute(
                "UPDATE nameserv_nicks SET custom_domain = :cdom, is_identified = 1, last_seen = :time WHERE LOWER(nickname) = LOWER(:nick) OR LOWER(nickname) = LOWER(:base)",
                [
                    ':cdom' => $cleanDomain,
                    ':time' => time(),
                    ':nick' => $cleanNick,
                    ':base' => $baseNick
                ]
            );
            return $stmt->rowCount() > 0;
        }

        $userNick = new UserNick(
            $baseNick,
            UserNick::hashPassword('auto_pass_' . bin2hex(random_bytes(4))),
            null,
            time(),
            time(),
            true,
            null,
            'none',
            0,
            $cleanDomain
        );
        return self::save($userNick);
    }

    /**
     * Get standardized username string for a nickname.
     */
    public static function getStandardizedUsername(string $nickname): string
    {
        $user = self::findByNickname($nickname);
        if ($user !== null) {
            return $user->getStandardizedUsername();
        }
        $parsed = UserNick::parseIdent($nickname);
        return $parsed['standardized'];
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
