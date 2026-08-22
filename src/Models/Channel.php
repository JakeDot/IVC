<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing a registered channel (chanserv_channels).
 */
class Channel
{
    private string $channelName;
    private string $ownerNick;
    private ?string $topic;
    private ?string $passkey;
    private string $modes;
    private int $registeredAt;
    private ?string $subscriptionTier;
    private string $subscriptionStatus;
    private int $subscriptionExpiresAt;

    public function __construct(
        string $channelName,
        string $ownerNick,
        ?string $topic = null,
        ?string $passkey = null,
        string $modes = '+t',
        ?int $registeredAt = null,
        ?string $subscriptionTier = null,
        string $subscriptionStatus = 'none',
        int $subscriptionExpiresAt = 0
    ) {
        $this->channelName = trim($channelName);
        $this->ownerNick = trim($ownerNick);
        $this->topic = $topic !== null ? trim($topic) : null;
        $this->passkey = $passkey;
        $this->modes = $modes;
        $this->registeredAt = $registeredAt ?? time();
        $this->subscriptionTier = $subscriptionTier;
        $this->subscriptionStatus = strtolower(trim($subscriptionStatus));
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function setChannelName(string $channelName): void
    {
        $this->channelName = trim($channelName);
    }

    public function getOwnerNick(): string
    {
        return $this->ownerNick;
    }

    public function setOwnerNick(string $ownerNick): void
    {
        $this->ownerNick = trim($ownerNick);
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): void
    {
        $this->topic = $topic !== null ? trim($topic) : null;
    }

    public function getPasskey(): ?string
    {
        return $this->passkey;
    }

    public function setPasskey(?string $passkey): void
    {
        $this->passkey = $passkey;
    }

    public function getModes(): string
    {
        return $this->modes;
    }

    public function setModes(string $modes): void
    {
        $this->modes = $modes;
    }

    public function getRegisteredAt(): int
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(int $registeredAt): void
    {
        $this->registeredAt = $registeredAt;
    }

    public function getSubscriptionTier(): ?string
    {
        return $this->subscriptionTier;
    }

    public function setSubscriptionTier(?string $subscriptionTier): void
    {
        $this->subscriptionTier = $subscriptionTier;
    }

    public function getSubscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function setSubscriptionStatus(string $subscriptionStatus): void
    {
        $this->subscriptionStatus = strtolower(trim($subscriptionStatus));
    }

    public function getSubscriptionExpiresAt(): int
    {
        return $this->subscriptionExpiresAt;
    }

    public function setSubscriptionExpiresAt(int $subscriptionExpiresAt): void
    {
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
    }

    public function isPremium(): bool
    {
        return in_array($this->subscriptionStatus, ['active', 'trialing'], true) && $this->subscriptionExpiresAt > time();
    }

    public function toArray(): array
    {
        return [
            'channel_name' => $this->channelName,
            'owner_nick' => $this->ownerNick,
            'topic' => $this->topic,
            'passkey' => $this->passkey,
            'modes' => $this->modes,
            'registered_at' => $this->registeredAt,
            'subscription_tier' => $this->subscriptionTier,
            'subscription_status' => $this->subscriptionStatus,
            'subscription_expires_at' => $this->subscriptionExpiresAt,
            'is_premium' => $this->isPremium() ? 1 : 0,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['channel_name'] ?? ''),
            (string)($data['owner_nick'] ?? ''),
            isset($data['topic']) ? (string)$data['topic'] : null,
            isset($data['passkey']) ? (string)$data['passkey'] : null,
            (string)($data['modes'] ?? '+t'),
            isset($data['registered_at']) ? (int)$data['registered_at'] : null,
            isset($data['subscription_tier']) ? (string)$data['subscription_tier'] : null,
            (string)($data['subscription_status'] ?? 'none'),
            isset($data['subscription_expires_at']) ? (int)$data['subscription_expires_at'] : 0
        );
    }

    public static function register(string $channel, string $ownerNick, ?string $passkey = null): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $ownerNick = trim($ownerNick);

        if (empty($channel) || empty($ownerNick)) {
            return ['success' => false, 'message' => 'CHANSERV: Valid channel name and owner nickname are required.'];
        }

        if (\Fortress\Database\ChannelRepository::exists($channel)) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is already registered."];
        }

        $chanModel = new self($channel, $ownerNick, null, $passkey, '+t', time());
        $success = \Fortress\Database\ChannelRepository::save($chanModel);

        if ($success) {
            self::setRole($channel, $ownerNick, 'OP');
            return ['success' => true, 'message' => "CHANSERV: Channel '{$channel}' successfully registered to owner '{$ownerNick}'."];
        }

        return ['success' => false, 'message' => 'CHANSERV: Channel registration failed.'];
    }

    public static function op(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant OP status."];
        }

        self::setRole($channel, $targetNick, 'OP');
        return ['success' => true, 'message' => "CHANSERV: Granted OP status (+o) to '{$targetNick}' in {$channel}."];
    }

    public static function deop(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove OP status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed OP status (-o) from '{$targetNick}' in {$channel}."];
    }

    public static function setTopicCommand(string $channel, string $topic, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);

        if (!empty($requesterNick) && self::isRegistered($channel) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. Only channel operators can set the topic for {$channel}."];
        }

        if (self::isRegistered($channel)) {
            \Fortress\Database\ChannelRepository::updateTopic($channel, $topic);
        }

        return ['success' => true, 'message' => "CHANSERV: Topic for {$channel} updated to: \"{$topic}\"", 'topic' => $topic];
    }

<<<<<<< HEAD
=======
    public static function admin(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant ADMIN status."];
        }

        self::setRole($channel, $targetNick, 'ADMIN');
        return ['success' => true, 'message' => "CHANSERV: Granted ADMIN status (+a) to '{$targetNick}' in {$channel}."];
    }

    public static function deadmin(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove ADMIN status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed ADMIN status (-a) from '{$targetNick}' in {$channel}."];
    }

    public static function netadmin(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant NETADMIN status."];
        }

        self::setRole($channel, $targetNick, 'NETADMIN');
        return ['success' => true, 'message' => "CHANSERV: Granted NETADMIN status (+n) to '{$targetNick}' in {$channel}."];
    }

    public static function denetadmin(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove NETADMIN status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed NETADMIN status (-n) from '{$targetNick}' in {$channel}."];
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function isRegistered(string $channel): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelRepository::exists($channel);
    }

    public static function setRole(string $channel, string $nickname, string $role): void
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        $channelUser = new ChannelUser($channel, $nickname, $role);
        \Fortress\Database\ChannelUserRepository::saveRole($channelUser);
    }

<<<<<<< HEAD
=======
    public static function hasVoice(string $channel, string $nickname): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::hasVoice($channel, $nickname);
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function isOp(string $channel, string $nickname): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::isOp($channel, $nickname);
    }

<<<<<<< HEAD
=======
    public static function isAdmin(string $channel, string $nickname): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::isAdmin($channel, $nickname);
    }

    public static function isNetAdmin(string $channel, string $nickname): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::isNetAdmin($channel, $nickname);
    }

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    public static function getOperators(string $channel): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::getOperators($channel);
    }
}
