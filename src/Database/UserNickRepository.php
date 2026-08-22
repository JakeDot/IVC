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
<<<<<<< HEAD
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
=======
        $cleanNick = trim($nickname);
        if ($cleanNick === '') {
            return null;
        }

        $coll = Database::getCollection('nameserv_nicks');
        $row = $coll->findOne(['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']]);

        if ($row !== null) {
            return UserNick::fromArray($row);
        }

        $atPos = strrpos($cleanNick, '@');
        if ($atPos !== false && $atPos > 0) {
            $base = substr($cleanNick, 0, $atPos);
            if ($base !== '') {
                $row = $coll->findOne(['nickname' => ['$regex' => '^' . preg_quote($base, '/') . '$', '$options' => 'i']]);
                if ($row !== null) {
                    return UserNick::fromArray($row);
                }
            }
        }
        return null;
    }

    public static function save(UserNick $userNick): bool
    {
        $exists = self::exists($userNick->getNickname());
        $coll = Database::getCollection('nameserv_nicks');
        $now = time();

        $doc = [
            'nickname' => $userNick->getNickname(),
            'password_hash' => $userNick->getPasswordHash(),
            'email' => $userNick->getEmail(),
            'last_seen' => $now,
            'is_identified' => $userNick->isIdentified() ? 1 : 0,
            'subscription_tier' => $userNick->getSubscriptionTier(),
            'subscription_status' => $userNick->getSubscriptionStatus(),
            'subscription_expires_at' => $userNick->getSubscriptionExpiresAt(),
            'vhost' => $userNick->getVhost(),
            'custom_domain' => $userNick->getVhost()
        ];

        if ($exists) {
            $coll->updateOne(
                ['nickname' => ['$regex' => '^' . preg_quote($userNick->getNickname(), '/') . '$', '$options' => 'i']],
                ['$set' => $doc]
            );
        } else {
            $doc['registered_at'] = $userNick->getRegisteredAt();
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateDomain(string $nickname, ?string $domain): bool
    {
        return self::updateVhost($nickname, $domain);
    }

    public static function updateVhost(string $nickname, ?string $vhost): bool
    {
        $cleanNick = trim($nickname);
        $cleanVhost = $vhost !== null ? trim($vhost) : null;
        $atPos = strrpos($cleanNick, '@');
        $baseNick = ($atPos !== false && $atPos > 0) ? substr($cleanNick, 0, $atPos) : $cleanNick;

        $user = self::findByNickname($cleanNick);
        $coll = Database::getCollection('nameserv_nicks');
        
        if ($user !== null) {
            $coll->updateMany(
                ['$or' => [
                    ['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']],
                    ['nickname' => ['$regex' => '^' . preg_quote($baseNick, '/') . '$', '$options' => 'i']]
                ]],
                ['$set' => [
                    'vhost' => $cleanVhost,
                    'custom_domain' => $cleanVhost,
                    'is_identified' => 1,
                    'last_seen' => time()
                ]]
            );
            return true;
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
            $cleanVhost
        );
        return self::save($userNick);
    }

    public static function getStandardizedUsername(string $nickname): string
    {
        $user = self::findByNickname($nickname);
        if ($user !== null) {
            return $user->getStandardizedUsername();
        }
        $parsed = UserNick::parseIdent($nickname);
        return $parsed['standardized'];
    }

    public static function updateIdentification(string $nickname, bool $isIdentified, ?int $lastSeen = null): bool
    {
        $time = $lastSeen ?? time();
        $coll = Database::getCollection('nameserv_nicks');
        $coll->updateOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']],
            ['$set' => ['is_identified' => $isIdentified ? 1 : 0, 'last_seen' => $time]]
        );
        return true;
    }

    public static function updateSubscription(string $nickname, ?string $tier, string $status, int $expiresAt): bool
    {
        $coll = Database::getCollection('nameserv_nicks');
        $coll->updateOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']],
            ['$set' => [
                'subscription_tier' => $tier,
                'subscription_status' => strtolower(trim($status)),
                'subscription_expires_at' => $expiresAt
            ]]
        );
        return true;
    }

    public static function exists(string $nickname): bool
    {
        $coll = Database::getCollection('nameserv_nicks');
        $row = $coll->findOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']]
        );
        return $row !== null;
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function findExpired(int $expireSeconds): array
    {
        $expireTime = time() - $expireSeconds;
        $now = time();
<<<<<<< HEAD
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
=======
        
        $coll = Database::getCollection('nameserv_nicks');
        $rows = $coll->find([
            'last_seen' => ['$lt' => $expireTime],
            '$not' => [
                'subscription_status' => ['$in' => ['active', 'trialing']],
                'subscription_expires_at' => ['$gt' => $now]
            ]
        ]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

        $expired = [];
        foreach ($rows as $row) {
            $expired[] = UserNick::fromArray($row);
        }
<<<<<<< HEAD

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
=======
        return $expired;
    }

    public static function delete(string $nickname): bool
    {
        $coll = Database::getCollection('nameserv_nicks');
        $coll->deleteOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']]
        );
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }
}
