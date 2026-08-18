<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\UserNickRepository;
use Fortress\Models\UserNick;

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
        return UserNick::register($nickname, $password, $email);
    }

    /**
     * Identify a nickname with password
     */
    public static function identify(string $nickname, string $password): array
    {
        return UserNick::identify($nickname, $password);
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

        $msg = "NAMESERV Information for {$userNick->getNickname()}:\n" .
               "• Registered: {$regDate}\n" .
               "• Last Seen: {$lastSeenDate}\n" .
               "• Currently Identified: {$identifiedStr}";

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
}
