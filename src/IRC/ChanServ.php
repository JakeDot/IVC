<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\ChannelRepository;
use Fortress\Database\ChannelUserRepository;
use Fortress\Models\Channel;
use Fortress\Models\ChannelUser;

/**
 * CHANSERV (Channel Service) IRC System Bot
 * Handles channel registration, operator management, topic control, passkeys, and channel modes.
 */
class ChanServ
{
    public const SERVICE_NAME = 'CHANSERV';

    /**
     * Normalize channel name (ensure leading #)
     */
    public static function normalizeChannelName(string $channel): string
    {
        $channel = trim($channel);
        if ($channel === '') {
            return '';
        }
        if (!str_starts_with($channel, '#') && !str_starts_with($channel, '&')) {
            $channel = '#' . $channel;
        }
        return $channel;
    }

    /**
     * Register a channel
     */
    public static function register(string $channel, string $ownerNick, ?string $passkey = null): array
    {
        return Channel::register($channel, $ownerNick, $passkey);
    }

    /**
     * Assign OP role to user in channel
     */
    public static function op(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        return Channel::op($channel, $targetNick, $requesterNick);
    }

    /**
     * Remove OP role from user in channel
     */
    public static function deop(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        return Channel::deop($channel, $targetNick, $requesterNick);
    }

    /**
     * Set topic for channel
     */
    public static function setTopic(string $channel, string $topic, string $requesterNick = ''): array
    {
        return Channel::setTopicCommand($channel, $topic, $requesterNick);
    }

    /**
     * Get channel info
     */
    public static function getInfo(string $channel): array
    {
        $channel = self::normalizeChannelName($channel);
        $chanModel = ChannelRepository::findByChannelName($channel);

        if ($chanModel === null) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is not registered."];
        }

        $ops = self::getOperators($channel);
        $opsList = !empty($ops) ? implode(', ', $ops) : 'None';
        $topicStr = $chanModel->getTopic() ?? '(No topic set)';
        $regDate = date('Y-m-d H:i:s', $chanModel->getRegisteredAt());

        $msg = "CHANSERV Info for {$chanModel->getChannelName()}:\n" .
               "• Owner: {$chanModel->getOwnerNick()}\n" .
               "• Registered: {$regDate}\n" .
               "• Modes: {$chanModel->getModes()}\n" .
               "• Topic: {$topicStr}\n" .
               "• Operators: {$opsList}";

        return ['success' => true, 'message' => $msg, 'data' => $chanModel->toArray()];
    }

    /**
     * Check if channel is registered
     */
    public static function isRegistered(string $channel): bool
    {
        return Channel::isRegistered($channel);
    }

    /**
     * Set user role in channel
     */
    public static function setRole(string $channel, string $nickname, string $role): void
    {
        Channel::setRole($channel, $nickname, $role);
    }

    /**
     * Check if user is OP in channel
     */
    public static function isOp(string $channel, string $nickname): bool
    {
        return Channel::isOp($channel, $nickname);
    }

    /**
     * Get list of operators in a channel
     *
     * @return array<int, string>
     */
    public static function getOperators(string $channel): array
    {
        return Channel::getOperators($channel);
    }

    /**
     * List registered channels
     *
     * @return array<int, array{channel_name: string, owner_nick: string, topic: string|null, registered_at: int}>
     */
    public static function listChannels(): array
    {
        $channels = ChannelRepository::findAll();
        $list = [];

        foreach ($channels as $c) {
            $list[] = [
                'channel_name' => $c->getChannelName(),
                'owner_nick' => $c->getOwnerNick(),
                'topic' => $c->getTopic(),
                'registered_at' => $c->getRegisteredAt()
            ];
        }

        return $list;
    }
}
