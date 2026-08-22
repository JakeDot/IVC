<?php
declare(strict_types=1);
namespace Fortress\Database;
use Fortress\Models\ChannelUser;

class ChannelUserRepository
{
    public static function findByChannelAndNick(string $channelName, string $nickname): ?ChannelUser
    {
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
        $existing = self::findByChannelAndNick($channelUser->getChannelName(), $channelUser->getNickname());
        $now = time();
        $doc = [
            'channel_name' => $channelUser->getChannelName(),
            'nickname' => $channelUser->getNickname(),
            'role' => $channelUser->getRole(),
            'added_at' => $now
        ];
        if ($existing !== null) {
            $coll->updateOne([
                'channel_name' => ['$regex' => '^' . preg_quote($channelUser->getChannelName(), '/') . '$', '$options' => 'i'],
                'nickname' => ['$regex' => '^' . preg_quote($channelUser->getNickname(), '/') . '$', '$options' => 'i']
            ], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function getOperators(string $channelName): array
    {
        $ops = [];
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null) {
            $ops[] = $channel->getOwnerNick();
        }
        $coll = Database::getCollection('channel_users');
        $rows = $coll->find([
            'channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i'],
            'role' => ['$regex' => '^OP$', '$options' => 'i']
        ]);
        foreach ($rows as $row) {
            $nick = (string)$row['nickname'];
            if (!in_array($nick, $ops, true)) {
                $ops[] = $nick;
            }
        }
        return $ops;
    }

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

    public static function isOp(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }
        $user = self::findByChannelAndNick($channelName, $nickname);
        return $user !== null && $user->isOp();
    }

    public static function hasVoice(string $channelName, string $nickname): bool
    {
        $channel = ChannelRepository::findByChannelName($channelName);
        if ($channel !== null && strcasecmp($channel->getOwnerNick(), trim($nickname)) === 0) {
            return true;
        }
        $user = self::findByChannelAndNick($channelName, $nickname);
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
    }
}
