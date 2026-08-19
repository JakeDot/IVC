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

    public function __construct(
        string $channelName,
        string $ownerNick,
        ?string $topic = null,
        ?string $passkey = null,
        string $modes = '+t',
        ?int $registeredAt = null
    ) {
        $this->channelName = trim($channelName);
        $this->ownerNick = trim($ownerNick);
        $this->topic = $topic !== null ? trim($topic) : null;
        $this->passkey = $passkey;
        $this->modes = $modes;
        $this->registeredAt = $registeredAt ?? time();
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

    public function toArray(): array
    {
        return [
            'channel_name' => $this->channelName,
            'owner_nick' => $this->ownerNick,
            'topic' => $this->topic,
            'passkey' => $this->passkey,
            'modes' => $this->modes,
            'registered_at' => $this->registeredAt,
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
            isset($data['registered_at']) ? (int)$data['registered_at'] : null
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

    public static function isOp(string $channel, string $nickname): bool
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::isOp($channel, $nickname);
    }

    public static function getOperators(string $channel): array
    {
        $channel = \Fortress\IRC\ChanServ::normalizeChannelName($channel);
        return \Fortress\Database\ChannelUserRepository::getOperators($channel);
    }
}
