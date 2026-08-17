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
}
