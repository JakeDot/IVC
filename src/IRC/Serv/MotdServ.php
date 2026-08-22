<?php

declare(strict_types=1);

namespace Fortress\IRC\Serv;

use Fortress\IRC\IrcObject;
use Fortress\IRC\NoModeTrait;
use Fortress\IRC\Objects\Network;

/**
 * MOTDSERV (Message of the Day Service) IRC System Bot
 * Allows viewing and updating the serverwide Message of the Day (MOTD) stored as a Network property.
 */
class MotdServ extends IrcObject
{
    use NoModeTrait;

    public const SERVICE_NAME = 'MOTDSERV';

    /**
     * Get current serverwide MOTD
     */
    public static function getMotd(): string
    {
        return Network::get('§motd', 'Welcome to IVC WebRTC IRC Network!');
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

        $res = Network::set('§motd', $newMotd, $requesterNick);
        if ($res['success']) {
            return [
                'success' => true,
                'message' => "MOTDSERV: Serverwide MOTD updated to: \"{$newMotd}\"",
                'motd'    => $newMotd
            ];
        }

        return ['success' => false, 'message' => 'MOTDSERV: Failed to update MOTD — ' . $res['message']];
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
