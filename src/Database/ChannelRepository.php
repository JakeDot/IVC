<?php
declare(strict_types=1);
namespace Fortress\Database;
use Fortress\Models\Channel;

class ChannelRepository
{
    public static function findByChannelName(string $channelName): ?Channel
    {
        $coll = Database::getCollection('chanserv_channels');
        $row = $coll->findOne(['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']]);
        return $row !== null ? Channel::fromArray($row) : null;
    }

    public static function save(Channel $channel): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $exists = self::exists($channel->getChannelName());
        $doc = [
            'channel_name' => $channel->getChannelName(),
            'owner_nick' => $channel->getOwnerNick(),
            'topic' => $channel->getTopic(),
            'passkey' => $channel->getPasskey(),
            'modes' => $channel->getModes(),
            'registered_at' => $channel->getRegisteredAt(),
            'subscription_tier' => $channel->getSubscriptionTier(),
            'subscription_status' => $channel->getSubscriptionStatus(),
            'subscription_expires_at' => $channel->getSubscriptionExpiresAt()
        ];
        if ($exists) {
            $coll->updateOne(['channel_name' => ['$regex' => '^' . preg_quote($channel->getChannelName(), '/') . '$', '$options' => 'i']], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateTopic(string $channelName, string $topic): bool
    {
        Database::getCollection('chanserv_channels')->updateOne(['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']], ['$set' => ['topic' => $topic]]);
        return true;
    }

    public static function updateModes(string $channelName, string $modes): bool
    {
        Database::getCollection('chanserv_channels')->updateOne(['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']], ['$set' => ['modes' => $modes]]);
        return true;
    }

    public static function updateSubscription(string $channelName, ?string $tier, string $status, int $expiresAt): bool
    {
        Database::getCollection('chanserv_channels')->updateOne(['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']], ['$set' => ['subscription_tier' => $tier, 'subscription_status' => strtolower(trim($status)), 'subscription_expires_at' => $expiresAt]]);
        return true;
    }

    public static function exists(string $channelName): bool
    {
        $count = Database::getCollection('chanserv_channels')->countDocuments(['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']]);
        return $count > 0;
    }

    public static function findAll(): array
    {
        $rows = Database::getCollection('chanserv_channels')->find([], ['sort' => ['registered_at' => -1]]);
        $channels = [];
        foreach ($rows as $row) {
            $channels[] = Channel::fromArray($row);
        }
        return $channels;
    }
}
