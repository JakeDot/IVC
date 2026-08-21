<?php

declare(strict_types=1);

namespace cx\ivc\Database;

use cx\ivc\Models\ChannelUser;

/**
 * Data Access Repository for channel access / user roles (channel_users).
 */
class ChannelUserRepository
{
    /**
     * Find channel user role entry.
     */
    public static function findByChannelAndNick(string $channelName, string $nickname): ?ChannelUser
    {
        $row = Database::fetchOne(
            "SELECT id, channel_name, nickname, role, added_at FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND LOWER(nickname) = LOWER(:nick)",
            [
                ':chan' => trim($channelName),
                ':nick' => trim($nickname)
            ]
        );

        return $row !== null ? ChannelUser::fromArray($row) : null;
    }

    /**
     * Save (insert or update) channel user role.
     */
    public static function saveRole(ChannelUser $channelUser): bool
    {
        $existing = self::findByChannelAndNick($channelUser->getChannelName(), $channelUser->getNickname());
        $now = time();

        if ($existing !== null) {
            $stmt = Database::execute(
                "UPDATE channel_users SET role = :role, added_at = :time WHERE LOWER(channel_name) = LOWER(:chan) AND LOWER(nickname) = LOWER(:nick)",
                [
                    ':role' => $channelUser->getRole(),
                    ':time' => $now,
                    ':chan' => $channelUser->getChannelName(),
                    ':nick' => $channelUser->getNickname()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO channel_users (channel_name, nickname, role, added_at) VALUES (:chan, :nick, :role, :time)",
                [
                    ':chan' => $channelUser->getChannelName(),
                    ':nick' => $channelUser->getNickname(),
                    ':role' => $channelUser->getRole(),
                    ':time' => $now
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Get operator list for a channel.
     *
     * @return array<int, string>
     */
    public static function getOperators(string $channelName): array
    {
        $ops = [];
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null) {
            $ops[] = $channel->getOwnerNick();
        }

        $rows = Database::fetchAll(
            "SELECT nickname FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND UPPER(role) = 'OP'",
            [':chan' => trim($channelName)]
        );

        foreach ($rows as $row) {
            $nick = (string)$row['nickname'];
            if (!in_array($nick, $ops, true)) {
                $ops[] = $nick;
            }
        }

        return $ops;
    }

    /**
     * Check if user is OP in a channel.
     */
    public static function isOp(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && $user->isOp();
    }

    /**
     * Check if user is VOICE or OP in a channel.
     */
    public static function hasVoice(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && ($user->isOp() || $user->isVoice());
    }
}
