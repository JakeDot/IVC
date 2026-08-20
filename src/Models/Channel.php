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
}
