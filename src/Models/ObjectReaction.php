<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * ObjectReaction Model
 * Represents an emoji reaction attached to an addressable object (e.g. chat message ivc://object/:id).
 */
class ObjectReaction
{
    private ?string $id;
    private string $objectUri;
    private string $emoji;
    private string $senderNick;
    private int $reactedAt;

    public function __construct(
        string $objectUri,
        string $emoji,
        string $senderNick,
        ?int $reactedAt = null,
        ?string $id = null
    ) {
        $this->objectUri = self::normalizeObjectUri($objectUri);
        $this->emoji = trim($emoji);
        $this->senderNick = trim($senderNick);
        $this->reactedAt = $reactedAt ?? time();
        $this->id = $id;
    }

    public static function normalizeObjectUri(string $uri): string
    {
        $uri = trim($uri);
        // Normalize HEART / emoji command target formats e.g. ivc://object/:123 -> ivc://object/:123
        return $uri;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getObjectUri(): string
    {
        return $this->objectUri;
    }

    public function setObjectUri(string $objectUri): void
    {
        $this->objectUri = self::normalizeObjectUri($objectUri);
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function setEmoji(string $emoji): void
    {
        $this->emoji = trim($emoji);
    }

    public function getSenderNick(): string
    {
        return $this->senderNick;
    }

    public function setSenderNick(string $senderNick): void
    {
        $this->senderNick = trim($senderNick);
    }

    public function getReactedAt(): int
    {
        return $this->reactedAt;
    }

    public function setReactedAt(int $reactedAt): void
    {
        $this->reactedAt = $reactedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object_uri' => $this->objectUri,
            'emoji' => $this->emoji,
            'sender_nick' => $this->senderNick,
            'reacted_at' => $this->reactedAt
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['object_uri'] ?? '',
            $data['emoji'] ?? '',
            $data['sender_nick'] ?? '',
            isset($data['reacted_at']) ? (int)$data['reacted_at'] : null,
            isset($data['id']) ? (string)$data['id'] : null
        );
    }
}
