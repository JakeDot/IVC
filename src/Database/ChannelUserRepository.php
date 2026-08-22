<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\ChannelUser;

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
<<<<<<< HEAD
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
=======
        $coll = Database::getCollection('channel_users');
        $row = $coll->findOne([
            'channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i'],
            'nickname' => ['$regex' => '^' . preg_quote(trim($nickname), '/') . '$', '$options' => 'i']
        ]);
        return $row !== null ? ChannelUser::fromArray($row) : null;
    }

    public static function saveRole(ChannelUser $channelUser): bool
    {
        $coll = Database::getCollection('channel_users');
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        $existing = self::findByChannelAndNick($channelUser->getChannelName(), $channelUser->getNickname());
        $now = time();

        if ($existing !== null) {
<<<<<<< HEAD
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
=======
            $coll->updateOne([
                'channel_name' => ['$regex' => '^' . preg_quote($channelUser->getChannelName(), '/') . '$', '$options' => 'i'],
                'nickname' => ['$regex' => '^' . preg_quote($channelUser->getNickname(), '/') . '$', '$options' => 'i']
            ], [
                '$set' => ['role' => $channelUser->getRole(), 'added_at' => $now]
            ]);
        } else {
            $coll->insertOne([
                'channel_name' => $channelUser->getChannelName(),
                'nickname' => $channelUser->getNickname(),
                'role' => $channelUser->getRole(),
                'added_at' => $now
            ]);
        }
        return true;
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function getOperators(string $channelName): array
    {
        $ops = [];
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null) {
            $ops[] = $channel->getOwnerNick();
        }

<<<<<<< HEAD
        $rows = Database::fetchAll(
            "SELECT nickname FROM channel_users WHERE LOWER(channel_name) = LOWER(:chan) AND UPPER(role) = 'OP'",
            [':chan' => trim($channelName)]
        );
=======
        $coll = Database::getCollection('channel_users');
        $rows = $coll->find([
            'channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i'],
            'role' => ['$regex' => '^OP$', '$options' => 'i']
        ]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

        foreach ($rows as $row) {
            $nick = (string)$row['nickname'];
            if (!in_array($nick, $ops, true)) {
                $ops[] = $nick;
            }
        }

        return $ops;
    }

<<<<<<< HEAD
    /**
     * Check if user is OP in a channel.
     */
=======
    public static function isNetAdmin(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && $user->isNetAdmin();
    }

    public static function isAdmin(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && $user->isAdmin();
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function isOp(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && $user->isOp();
    }

<<<<<<< HEAD
    /**
     * Check if user is VOICE or OP in a channel.
     */
=======
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function hasVoice(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }

        $user = self::findByChannelAndNick($channelName, $nickname);
<<<<<<< HEAD
        return $user !== null && ($user->isOp() || $user->isVoice());
=======
        return $user !== null && $user->isVoice();
    }

    public static function getUserChannels(string $nickname): array
    {
        $cleanNick = trim($nickname);
        $collUsers = Database::getCollection('channel_users');
        $rows = $collUsers->find(['nickname' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']]);
        
        $collChans = Database::getCollection('chanserv_channels');
        $owned = $collChans->find(['owner_nick' => ['$regex' => '^' . preg_quote($cleanNick, '/') . '$', '$options' => 'i']]);
        
        $all = [];
        $seen = [];
        
        foreach ($owned as $r) {
            $c = (string)$r['channel_name'];
            if (!isset($seen[strtolower($c)])) {
                $seen[strtolower($c)] = true;
                $all[] = ['channel_name' => $c, 'role' => 'OWNER'];
            }
        }
        
        foreach ($rows as $r) {
            $c = (string)$r['channel_name'];
            if (!isset($seen[strtolower($c)])) {
                $seen[strtolower($c)] = true;
                $all[] = ['channel_name' => $c, 'role' => (string)$r['role']];
            }
        }
        
        return $all;
    }

    public static function getMembers(string $channelName): array
    {
        $cleanChan = trim($channelName);
        $chan = ChannelRepository::findByChannelName($cleanChan);
        $members = [];
        $seen = [];
        
        if ($chan !== null) {
            $owner = $chan->getOwnerNick();
            $seen[strtolower($owner)] = true;
            $members[] = ['nickname' => $owner, 'role' => 'OWNER'];
        }
        
        $coll = Database::getCollection('channel_users');
        $rows = $coll->find(['channel_name' => ['$regex' => '^' . preg_quote($cleanChan, '/') . '$', '$options' => 'i']]);
        
        foreach ($rows as $r) {
            $n = (string)$r['nickname'];
            if (!isset($seen[strtolower($n)])) {
                $seen[strtolower($n)] = true;
                $members[] = ['nickname' => $n, 'role' => (string)$r['role']];
            }
        }
        
        return $members;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }
}
