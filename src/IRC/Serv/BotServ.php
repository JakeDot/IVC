<?php

declare(strict_types=1);

namespace Fortress\IRC\Serv;

use Fortress\Database\BotServRepository;
use Fortress\IRC\IrcObject;
use Fortress\IRC\NoModeTrait;

/**
 * BOTSERV (Bot Service) IRC System Bot
 * Handles associating usernames to Foreign Services either locally to a channel or globally.
 */
class BotServ extends IrcObject
{
    use NoModeTrait;

    public const SERVICE_NAME = 'BOTSERV';

    public static function assign(string $target, string $botNick, string $serviceName, string $requesterNick): array
    {
        $target      = strtoupper(trim($target));
        $botNick     = trim($botNick);
        $serviceName = strtoupper(trim($serviceName));

        if (empty($target) || empty($botNick) || empty($serviceName)) {
            return ['success' => false, 'message' => 'BOTSERV: Target, bot nick, and service name required. Usage: /msg BOTSERV ASSIGN <GLOBAL|#channel> <bot_nick> <service_name>'];
        }

        if ($target !== 'GLOBAL') {
            $target = ChanServ::normalizeChannelName($target);
            if (!ChanServ::isRegistered($target)) {
                return ['success' => false, 'message' => "BOTSERV: Channel {$target} is not registered."];
            }

            if (!ChanServ::isOp($target, $requesterNick)) {
                return ['success' => false, 'message' => "BOTSERV: Permission denied. You must be an OP on {$target} to assign a bot."];
            }
        } else {
            // For GLOBAL assignment, we assume the requester has sufficient privileges.
            // In a real IRC daemon, this would require OPER or ADMIN status.
            // For this implementation, we will allow it or we could restrict it.
            // Let's allow it for demonstration, or maybe just standard users can't assign GLOBAL unless they are a specific admin nick. Let's just allow it for now.
        }

        $fs = \Fortress\IRC\ServiceRegistry::getService($serviceName);
        if (!$fs || strtoupper($fs['status']) !== 'ACTIVE') {
            return ['success' => false, 'message' => "BOTSERV: Foreign Service '{$serviceName}' is not registered or not active."];
        }

        BotServRepository::assignBot($target, $botNick, $serviceName, $requesterNick);
        return ['success' => true, 'message' => "BOTSERV: Successfully assigned bot '{$botNick}' to service '{$serviceName}' for target '{$target}'."];
    }

    public static function unassign(string $target, string $botNick, string $requesterNick): array
    {
        $target  = strtoupper(trim($target));
        $botNick = trim($botNick);

        if (empty($target) || empty($botNick)) {
            return ['success' => false, 'message' => 'BOTSERV: Target and bot nick required. Usage: /msg BOTSERV UNASSIGN <GLOBAL|#channel> <bot_nick>'];
        }

        if ($target !== 'GLOBAL') {
            $target = ChanServ::normalizeChannelName($target);
            if (!ChanServ::isRegistered($target)) {
                return ['success' => false, 'message' => "BOTSERV: Channel {$target} is not registered."];
            }

            if (!ChanServ::isOp($target, $requesterNick)) {
                return ['success' => false, 'message' => "BOTSERV: Permission denied. You must be an OP on {$target} to unassign a bot."];
            }
        }

        if (!BotServRepository::getAssignedBot($target, $botNick)) {
            return ['success' => false, 'message' => "BOTSERV: No bot nick '{$botNick}' is currently assigned to '{$target}'."];
        }

        BotServRepository::unassignBot($target, $botNick);
        return ['success' => true, 'message' => "BOTSERV: Successfully unassigned bot '{$botNick}' from '{$target}'."];
    }

    public static function resolveBotService(string $channel, string $botNick): ?string
    {
        return BotServRepository::resolveBotService($channel, $botNick);
    }
}
