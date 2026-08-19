<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\Channel;

/**
 * Data Access Repository for registered channels (chanserv_channels).
 */
class ChannelRepository
{
    /**
     * Find channel by channel name (case-insensitive).
     */
    public static function findByChannelName(string $channelName): ?Channel
    {
        $row = Database::fetchOne(
            "SELECT channel_name, owner_nick, topic, passkey, modes, registered_at FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)",
            [':chan' => trim($channelName)]
        );

        return $row !== null ? Channel::fromArray($row) : null;
    }

    /**
     * Save (insert or update) channel record.
     */
    public static function save(Channel $channel): bool
    {
        $exists = self::exists($channel->getChannelName());

        if ($exists) {
            $stmt = Database::execute(
                "UPDATE chanserv_channels SET owner_nick = :owner, topic = :topic, passkey = :pass, modes = :modes WHERE LOWER(channel_name) = LOWER(:chan)",
                [
                    ':owner' => $channel->getOwnerNick(),
                    ':topic' => $channel->getTopic(),
                    ':pass' => $channel->getPasskey(),
                    ':modes' => $channel->getModes(),
                    ':chan' => $channel->getChannelName()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO chanserv_channels (channel_name, owner_nick, topic, passkey, modes, registered_at) VALUES (:chan, :owner, :topic, :pass, :modes, :reg)",
                [
                    ':chan' => $channel->getChannelName(),
                    ':owner' => $channel->getOwnerNick(),
                    ':topic' => $channel->getTopic(),
                    ':pass' => $channel->getPasskey(),
                    ':modes' => $channel->getModes(),
                    ':reg' => $channel->getRegisteredAt()
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Update topic for a channel.
     */
    public static function updateTopic(string $channelName, string $topic): bool
    {
        $stmt = Database::execute(
            "UPDATE chanserv_channels SET topic = :topic WHERE LOWER(channel_name) = LOWER(:chan)",
            [
                ':topic' => $topic,
                ':chan' => trim($channelName)
            ]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a channel is registered.
     */

    /**
     * Update modes for a channel.
     */
    public static function updateModes(string $channelName, string $modes): bool
    {
        $stmt = Database::execute(
            "UPDATE chanserv_channels SET modes = :modes WHERE LOWER(channel_name) = LOWER(:chan)",
            [
                ':modes' => $modes,
                ':chan' => trim($channelName)
            ]
        );

        return $stmt->rowCount() > 0;
    }

    public static function exists(string $channelName): bool
    {
        $count = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)",
            [':chan' => trim($channelName)]
        );

        return $count > 0;
    }

    /**
     * Fetch all registered channels.
     *
     * @return array<int, Channel>
     */
    public static function findAll(): array
    {
        $rows = Database::fetchAll("SELECT channel_name, owner_nick, topic, passkey, modes, registered_at FROM chanserv_channels ORDER BY registered_at DESC");
        $channels = [];
        foreach ($rows as $row) {
            $channels[] = Channel::fromArray($row);
        }

        return $channels;
    }
}
