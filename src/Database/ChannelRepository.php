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
<<<<<<< HEAD
        $row = Database::fetchOne(
            "SELECT channel_name, owner_nick, topic, passkey, modes, registered_at, subscription_tier, subscription_status, subscription_expires_at
             FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(:chan)",
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
                "UPDATE chanserv_channels SET
                    owner_nick = :owner,
                    topic = :topic,
                    passkey = :pass,
                    modes = :modes,
                    subscription_tier = :stier,
                    subscription_status = :sstatus,
                    subscription_expires_at = :sexp
                 WHERE LOWER(channel_name) = LOWER(:chan)",
                [
                    ':owner' => $channel->getOwnerNick(),
                    ':topic' => $channel->getTopic(),
                    ':pass' => $channel->getPasskey(),
                    ':modes' => $channel->getModes(),
                    ':stier' => $channel->getSubscriptionTier(),
                    ':sstatus' => $channel->getSubscriptionStatus(),
                    ':sexp' => $channel->getSubscriptionExpiresAt(),
                    ':chan' => $channel->getChannelName()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO chanserv_channels (channel_name, owner_nick, topic, passkey, modes, registered_at, subscription_tier, subscription_status, subscription_expires_at)
                 VALUES (:chan, :owner, :topic, :pass, :modes, :reg, :stier, :sstatus, :sexp)",
                [
                    ':chan' => $channel->getChannelName(),
                    ':owner' => $channel->getOwnerNick(),
                    ':topic' => $channel->getTopic(),
                    ':pass' => $channel->getPasskey(),
                    ':modes' => $channel->getModes(),
                    ':reg' => $channel->getRegisteredAt(),
                    ':stier' => $channel->getSubscriptionTier(),
                    ':sstatus' => $channel->getSubscriptionStatus(),
                    ':sexp' => $channel->getSubscriptionExpiresAt()
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

    /**
     * Update channel subscription details.
     */
    public static function updateSubscription(string $channelName, ?string $tier, string $status, int $expiresAt): bool
    {
        $stmt = Database::execute(
            "UPDATE chanserv_channels SET subscription_tier = :tier, subscription_status = :status, subscription_expires_at = :exp WHERE LOWER(channel_name) = LOWER(:chan)",
            [
                ':tier' => $tier,
                ':status' => strtolower(trim($status)),
                ':exp' => $expiresAt,
                ':chan' => trim($channelName)
            ]
        );

        return $stmt->rowCount() > 0;
=======
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
            'subscription_tier' => $channel->getSubscriptionTier(),
            'subscription_status' => $channel->getSubscriptionStatus(),
            'subscription_expires_at' => $channel->getSubscriptionExpiresAt()
        ];

        if ($exists) {
            $coll->updateOne(
                ['channel_name' => ['$regex' => '^' . preg_quote($channel->getChannelName(), '/') . '$', '$options' => 'i']],
                ['$set' => $doc]
            );
        } else {
            $doc['registered_at'] = $channel->getRegisteredAt();
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateTopic(string $channelName, string $topic): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $coll->updateOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']],
            ['$set' => ['topic' => $topic]]
        );
        return true;
    }

    public static function updateModes(string $channelName, string $modes): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $coll->updateOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']],
            ['$set' => ['modes' => $modes]]
        );
        return true;
    }

    public static function setPasskey(string $channelName, string $passkey): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $coll->updateOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']],
            ['$set' => ['passkey' => $passkey]]
        );
        return true;
    }

    public static function removePasskey(string $channelName): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $coll->updateOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']],
            ['$set' => ['passkey' => null]]
        );
        return true;
    }

    public static function drop(string $channelName): bool
    {
        $coll = Database::getCollection('chanserv_channels');
        $coll->deleteOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']]
        );
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function exists(string $channelName): bool
    {
<<<<<<< HEAD
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
        $rows = Database::fetchAll("SELECT channel_name, owner_nick, topic, passkey, modes, registered_at, subscription_tier, subscription_status, subscription_expires_at FROM chanserv_channels ORDER BY registered_at DESC");
=======
        $coll = Database::getCollection('chanserv_channels');
        $row = $coll->findOne(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']]
        );
        return $row !== null;
    }

    public static function fetchAll(): array
    {
        $coll = Database::getCollection('chanserv_channels');
        $rows = $coll->find([]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        $channels = [];
        foreach ($rows as $row) {
            $channels[] = Channel::fromArray($row);
        }
<<<<<<< HEAD

        return $channels;
    }
=======
        return $channels;
    }

    public static function count(): int
    {
        $coll = Database::getCollection('chanserv_channels');
        $rows = $coll->find([]);
        return count($rows);
    }
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
}
