<?php

declare(strict_types=1);

namespace Fortress\IRC\Serv;

use Fortress\Database\UserNickRepository;
use Fortress\IRC\IrcObject;
use Fortress\Models\UserNick;

/**
 * NAMESERV (Nickname Service) IRC System Bot
 * Handles user nickname registration, identification, subscription, and information queries stored in MySQL.
 */
class NameServ extends IrcObject
{
    public const SERVICE_NAME = 'NAMESERV';

    protected static function isAuthorizedToSetModes(string $target, string $requesterNick): bool {
        return ltrim($target, '@') === $requesterNick; // Only the user can set their own modes
    }
    
    protected static function isTargetRegistered(string $target): bool {
        return self::isRegistered(ltrim($target, '@'));
    }
    
    protected static function getModesFromDb(string $target): ?string {
        $cleanNick = ltrim($target, '@');
        $mUser = \Fortress\Database\Database::getCollection('nameserv_nicks')->findOne(['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']]);
        return $mUser['modes'] ?? null;
    }
    
    protected static function updateModesInDb(string $target, string $modes): void {
        $cleanNick = ltrim($target, '@');
        \Fortress\Database\Database::getCollection('nameserv_nicks')->updateOne(
            ['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']],
            ['$set' => ['modes' => $modes]]
        );
    }
    
    protected static function createAndSaveDefault(string $target, string $modes, string $requesterNick): void
    {
        // We do not auto-create unregistered users via mode commands
        self::updateModesInDb($target, $modes);
    }
    
    protected static function getTargetNameForMessage(string $target): string {
        return $target;
    }

    /**
     * Register a nickname
     */
    public static function register(string $nickname, string $password, ?string $email = null): array
    {
        return UserNick::register($nickname, $password, $email);
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
        return UserNick::identify($nickname, $password);
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
     * Set custom domain (§domain) for a nickname
     */
    public static function setDomain(string $nickname, string $domain): array
    {
        $nickname = trim($nickname);
        $domain   = trim($domain);

        if (empty($nickname)) {
            return ['success' => false, 'message' => 'NICKSERV: Nickname is required.'];
        }

        if (empty($domain)) {
            return ['success' => false, 'message' => 'NICKSERV: Domain value cannot be empty.'];
        }

        // Clean up domain if formatted with § or leading/trailing slashes
        $domain = ltrim($domain, '§$@');

        $atPos    = strrpos($nickname, '@');
        $baseUser = ($atPos !== false && $atPos > 0) ? substr($nickname, 0, $atPos) : $nickname;
        if ($baseUser === '') {
            $baseUser = $nickname;
        }

        UserNickRepository::updateDomain($baseUser, $domain);
        $standardized = "{$baseUser}@{$domain}";

        return [
            'success'       => true,
            'message'       => "NICKSERV: Property §domain set to '{$domain}' for user '{$baseUser}'. Standardized username: {$standardized}",
            'user'          => $baseUser,
            'domain'        => $domain,
            'standardized'  => $standardized
        ];
    }

    /**
     * Auto-identify a user string (user@domain.tld or raw nick or IP)
     */
    public static function autoIdent(string $identString): array
    {
        $parsed = UserNick::parseIdent($identString);
        $user   = $parsed['user'];
        $domain = $parsed['domain'];

        if ($domain !== '<anonymous>' && $domain !== '') {
            UserNickRepository::updateDomain($user, $domain);
        }

        return $parsed;
    }

    /**
     * Get info for a registered nickname
     */
    public static function getInfo(string $nickname): array
    {
        $nickname = trim($nickname);
        $userNick = UserNickRepository::findByNickname($nickname);

        if ($userNick === null) {
            $parsed = UserNick::parseIdent($nickname);
            return [
                'success' => false,
                'message' => "NAMESERV: Nickname '{$nickname}' is not registered. (Ident: {$parsed['standardized']})",
                'data'    => [
                    'nickname'             => $parsed['user'],
                    'domain'               => $parsed['domain'],
                    'standardized_username' => $parsed['standardized'],
                    'is_identified'        => 0
                ]
            ];
        }

        $regDate      = date('Y-m-d H:i:s', $userNick->getRegisteredAt());
        $lastSeenDate = date('Y-m-d H:i:s', $userNick->getLastSeen());
        $identifiedStr = $userNick->isIdentified() ? 'Yes' : 'No';
        $subStr       = $userNick->isPremium() ? "⭐ Active ({$userNick->getSubscriptionTier()})" : 'None (Standard)';
        $domainStr    = $userNick->getDomain();
        $stdUser      = $userNick->getStandardizedUsername();

        $msg = "NAMESERV Information for {$userNick->getNickname()}:\n" .
               "• Standardized Username: {$stdUser}\n" .
               "• Domain: {$domainStr} (§domain: {$domainStr})\n" .
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
        $purgedCount  = 0;

        foreach ($expiredNicks as $userNick) {
            $nickname = $userNick->getNickname();
            $email    = $userNick->getEmail();

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
