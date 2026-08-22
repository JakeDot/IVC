<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * MOTDSERV (Message of the Day Service) IRC System Bot
 * Allows viewing and updating the serverwide Message of the Day (MOTD) stored in MySQL database.
 */
class MotdServ
{
    public const SERVICE_NAME = 'MOTDSERV';

    /**
     * Get current serverwide MOTD
     */
    public static function getMotd(): string
    {
        return SettingsManager::getSetting('motd', 'Welcome to IVC WebRTC IRC Network!');
    }

    /**
     * Set new serverwide MOTD
     */
    public static function setMotd(string $newMotd, string $requesterNick = ''): array
    {
        $newMotd = trim($newMotd);
        if ($newMotd === '') {
            return ['success' => false, 'message' => 'MOTDSERV: MOTD content cannot be empty.'];
        }

        $success = SettingsManager::setSetting('motd', $newMotd, 'Serverwide Message of the Day');
        if ($success) {
            return [
                'success' => true,
                'message' => "MOTDSERV: Serverwide MOTD updated to: \"{$newMotd}\"",
                'motd' => $newMotd
            ];
        }

        return ['success' => false, 'message' => 'MOTDSERV: Failed to update MOTD in database.'];
    }

    /**
     * Get MOTD info formatted
     */
    public static function getInfo(): array
    {
        $motd = self::getMotd();
        return [
            'success' => true,
            'message' => "MOTDSERV Message of the Day:\n-----------------------------------\n{$motd}\n-----------------------------------"
        ];
    }
}
