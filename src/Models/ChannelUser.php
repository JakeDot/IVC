<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing user role in a channel (channel_users).
 */
class ChannelUser
{
    private ?int $id;
    private string $channelName;
    private string $nickname;
    private string $role;
    private int $addedAt;

    public function __construct(
        string $channelName,
        string $nickname,
        string $role = 'MEMBER',
        ?int $id = null,
        ?int $addedAt = null
    ) {
        $this->channelName = trim($channelName);
        $this->nickname = trim($nickname);
        $this->role = strtoupper(trim($role));
        $this->id = $id;
        $this->addedAt = $addedAt ?? time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function setChannelName(string $channelName): void
    {
        $this->channelName = trim($channelName);
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): void
    {
        $this->nickname = trim($nickname);
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = strtoupper(trim($role));
    }

    public function isOp(): bool
    {
        return $this->role === 'OP';
    }

    public function getAddedAt(): int
    {
        return $this->addedAt;
    }

    public function setAddedAt(int $addedAt): void
    {
        $this->addedAt = $addedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel_name' => $this->channelName,
            'nickname' => $this->nickname,
            'role' => $this->role,
            'added_at' => $this->addedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['channel_name'] ?? ''),
            (string)($data['nickname'] ?? ''),
            (string)($data['role'] ?? 'MEMBER'),
            isset($data['id']) ? (int)$data['id'] : null,
            isset($data['added_at']) ? (int)$data['added_at'] : null
        );
    }
}
