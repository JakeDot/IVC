<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing a registered nickname (nameserv_nicks).
 */
class UserNick
{
    private string $nickname;
    private string $passwordHash;
    private ?string $email;
    private int $registeredAt;
    private int $lastSeen;
    private bool $isIdentified;
    private ?string $subscriptionTier;
    private string $subscriptionStatus;
    private int $subscriptionExpiresAt;

    public function __construct(
        string $nickname,
        string $passwordHash,
        ?string $email = null,
        ?int $registeredAt = null,
        ?int $lastSeen = null,
        bool $isIdentified = false,
        ?string $subscriptionTier = null,
        string $subscriptionStatus = 'none',
        int $subscriptionExpiresAt = 0
    ) {
        $this->nickname = trim($nickname);
        $this->passwordHash = $passwordHash;
        $this->email = $email !== null ? trim($email) : null;
        $this->registeredAt = $registeredAt ?? time();
        $this->lastSeen = $lastSeen ?? time();
        $this->isIdentified = $isIdentified;
        $this->subscriptionTier = $subscriptionTier;
        $this->subscriptionStatus = strtolower(trim($subscriptionStatus));
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = trim($nickname);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email !== null ? trim($email) : null;
    }

    public function getRegisteredAt(): int
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(int $registeredAt): void
    {
        $this->registeredAt = $registeredAt;
    }

    public function getLastSeen(): int
    {
        return $this->lastSeen;
    }

    public function setLastSeen(int $lastSeen): void
    {
        $this->lastSeen = $lastSeen;
    }

    public function isIdentified(): bool
    {
        return $this->isIdentified;
    }

    public function setIsIdentified(bool $isIdentified): void
    {
        $this->isIdentified = $isIdentified;
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

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function toArray(): array
    {
        return [
            'nickname' => $this->nickname,
            'password_hash' => $this->passwordHash,
            'email' => $this->email,
            'registered_at' => $this->registeredAt,
            'last_seen' => $this->lastSeen,
            'is_identified' => $this->isIdentified ? 1 : 0,
            'subscription_tier' => $this->subscriptionTier,
            'subscription_status' => $this->subscriptionStatus,
            'subscription_expires_at' => $this->subscriptionExpiresAt,
            'is_premium' => $this->isPremium() ? 1 : 0,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['nickname'] ?? ''),
            (string)($data['password_hash'] ?? ''),
            isset($data['email']) ? (string)$data['email'] : null,
            isset($data['registered_at']) ? (int)$data['registered_at'] : null,
            isset($data['last_seen']) ? (int)$data['last_seen'] : null,
            !empty($data['is_identified']),
            isset($data['subscription_tier']) ? (string)$data['subscription_tier'] : null,
            (string)($data['subscription_status'] ?? 'none'),
            isset($data['subscription_expires_at']) ? (int)$data['subscription_expires_at'] : 0
        );
    }

}
