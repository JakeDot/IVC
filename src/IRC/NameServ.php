<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\UserNickRepository;
use Fortress\Models\UserNick;

/**
 * NAMESERV (Nickname Service) IRC System Bot
 * Handles user nickname registration, identification, subscription, and information queries stored in MySQL.
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

        if (UserNickRepository::exists($nickname)) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' is already registered."];
        }

        $passHash = UserNick::hashPassword($password);
        $userNick = new UserNick($nickname, $passHash, $email, null, null, true);

        if (UserNickRepository::save($userNick)) {
            if ($email !== null) {
                $subject = 'NAMESERV Registration Confirmation';
                $body = "Hello {$nickname},\n\nYour nickname has been successfully registered on the IVC-IRC Network.";
                $headers = "From: noreply@fortress.ivc.local\r\n";
                @mail($email, $subject, $body, $headers);
            }
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
        $userNick = UserNickRepository::findByNickname($nickname);

        if ($userNick === null || !$userNick->verifyPassword($password)) {
            return ['success' => false, 'message' => 'NAMESERV: Password verification failed. Access denied.'];
        }

        UserNickRepository::updateIdentification($userNick->getNickname(), true, time());

        return ['success' => true, 'message' => "NAMESERV: Password accepted. Nickname '{$nickname}' identified."];
    }

    /**
     * Subscribe user nickname to premium plan tier
     */
    public static function subscribe(string $nickname, string $planTier = 'nick_pro'): array
    {
        $nickname = trim($nickname);
        if (!self::isRegistered($nickname)) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' must be registered before subscribing."];
        }

        return PayServ::subscribe($nickname, 'user', $nickname, $planTier);
    }

    /**
     * Get info for a registered nickname
     */
    public static function getInfo(string $nickname): array
    {
        $nickname = trim($nickname);
        $userNick = UserNickRepository::findByNickname($nickname);

        if ($userNick === null) {
            return ['success' => false, 'message' => "NAMESERV: Nickname '{$nickname}' is not registered."];
        }

        $regDate = date('Y-m-d H:i:s', $userNick->getRegisteredAt());
        $lastSeenDate = date('Y-m-d H:i:s', $userNick->getLastSeen());
        $identifiedStr = $userNick->isIdentified() ? 'Yes' : 'No';
        $subStr = $userNick->isPremium() ? "⭐ Active ({$userNick->getSubscriptionTier()})" : 'None (Standard)';

        $msg = "NAMESERV Information for {$userNick->getNickname()}:\n" .
               "• Registered: {$regDate}\n" .
               "• Last Seen: {$lastSeenDate}\n" .
               "• Currently Identified: {$identifiedStr}\n" .
               "• Subscription Status: {$subStr}";

        return ['success' => true, 'message' => $msg, 'data' => $userNick->toArray()];
    }

    /**
     * Check if a nickname is registered
     */
    public static function isRegistered(string $nickname): bool
    {
        return UserNickRepository::exists($nickname);
    }

    /**
     * Check if nickname is identified
     */
    public static function isIdentified(string $nickname): bool
    {
        $userNick = UserNickRepository::findByNickname($nickname);
        return $userNick !== null && $userNick->isIdentified();
    }

    /**
     * Purge expired nicknames and send email notifications (Excludes active paid subscribers).
     */
    public static function purgeExpired(int $expireSeconds): int
    {
        $expiredNicks = UserNickRepository::findExpired($expireSeconds);
        $purgedCount = 0;

        foreach ($expiredNicks as $userNick) {
            $nickname = $userNick->getNickname();
            $email = $userNick->getEmail();

            if ($email !== null) {
                $subject = 'NAMESERV Nickname Expiration';
                $body = "Hello {$nickname},\n\nYour nickname on the IVC-IRC Network has expired due to inactivity and has been removed.";
                $headers = "From: noreply@fortress.ivc.local\r\n";
                @mail($email, $subject, $body, $headers);
            }

            if (UserNickRepository::delete($nickname)) {
                $purgedCount++;
            }
        }

        return $purgedCount;
    }
}
