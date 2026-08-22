<?php
declare(strict_types=1);
namespace Fortress\Database;
use Fortress\Models\UserNick;

class UserNickRepository
{
    public static function findByNickname(string $nickname): ?UserNick
    {
        $cleanNick = trim($nickname);
        if ($cleanNick === '') return null;
        $coll = Database::getCollection('nameserv_nicks');
        
        $row = $coll->findOne(['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']]);
        if ($row !== null) return UserNick::fromArray($row);
        
        $atPos = strrpos($cleanNick, '@');
        if ($atPos !== false && $atPos > 0) {
            $base = substr($cleanNick, 0, $atPos);
            if ($base !== '') {
                $row = $coll->findOne(['nickname' => ['$regex' => '^' . preg_quote($base, '/') . '$', '$options' => 'i']]);
                if ($row !== null) return UserNick::fromArray($row);
            }
        }
        return null;
    }

    public static function save(UserNick $userNick): bool
    {
        $coll = Database::getCollection('nameserv_nicks');
        $exists = self::exists($userNick->getNickname());
        $now = time();
        $doc = [
            'nickname' => $userNick->getNickname(),
            'password_hash' => $userNick->getPasswordHash(),
            'email' => $userNick->getEmail(),
            'registered_at' => $userNick->getRegisteredAt(),
            'last_seen' => $now,
            'is_identified' => $userNick->isIdentified() ? 1 : 0,
            'subscription_tier' => $userNick->getSubscriptionTier(),
            'subscription_status' => $userNick->getSubscriptionStatus(),
            'subscription_expires_at' => $userNick->getSubscriptionExpiresAt(),
            'custom_domain' => $userNick->getCustomDomain()
        ];
        
        if ($exists) {
            $coll->updateOne(['nickname' => ['$regex' => '^' . preg_quote($userNick->getNickname(), '/') . '$', '$options' => 'i']], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateDomain(string $nickname, ?string $domain): bool
    {
        $cleanNick = trim($nickname);
        $cleanDomain = $domain !== null ? trim($domain) : null;
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
                ['$set' => ['custom_domain' => $cleanDomain, 'is_identified' => 1, 'last_seen' => time()]]
            );
            return true;
        }
        
        $userNick = new UserNick($baseNick, UserNick::hashPassword('auto_pass_' . bin2hex(random_bytes(4))), null, time(), time(), true, null, 'none', 0, $cleanDomain);
        return self::save($userNick);
    }

    public static function getStandardizedUsername(string $nickname): string
    {
        $user = self::findByNickname($nickname);
        if ($user !== null) return $user->getStandardizedUsername();
        $parsed = UserNick::parseIdent($nickname);
        return $parsed['standardized'];
    }

    public static function updateIdentification(string $nickname, bool $isIdentified, ?int $lastSeen = null): bool
    {
        $time = $lastSeen ?? time();
        Database::getCollection('nameserv_nicks')->updateOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']],
            ['$set' => ['is_identified' => $isIdentified ? 1 : 0, 'last_seen' => $time]]
        );
        return true;
    }

    public static function updateSubscription(string $nickname, ?string $tier, string $status, int $expiresAt): bool
    {
        Database::getCollection('nameserv_nicks')->updateOne(
            ['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']],
            ['$set' => ['subscription_tier' => $tier, 'subscription_status' => strtolower(trim($status)), 'subscription_expires_at' => $expiresAt]]
        );
        return true;
    }

    public static function exists(string $nickname): bool
    {
        return Database::getCollection('nameserv_nicks')->countDocuments(['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']]) > 0;
    }

    public static function findExpired(int $expireSeconds): array
    {
        $expireTime = time() - $expireSeconds;
        $now = time();
        $coll = Database::getCollection('nameserv_nicks');
        // AND NOT (subscription_status IN ('active', 'trialing') AND subscription_expires_at > :now)
        $rows = $coll->find([
            'last_seen' => ['$lt' => $expireTime],
            '$not' => [
                'subscription_status' => ['$in' => ['active', 'trialing']],
                'subscription_expires_at' => ['$gt' => $now]
            ]
        ]);
        $expired = [];
        foreach ($rows as $row) {
            $expired[] = UserNick::fromArray($row);
        }
        return $expired;
    }

    public static function delete(string $nickname): bool
    {
        Database::getCollection('nameserv_nicks')->deleteOne(['nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']]);
        return true;
    }
}
