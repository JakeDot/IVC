<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing a shared file entity with E2EE encrypted metadata.
 */
class SharedFile
{
    private string $id;
    private string $channelName;
    private string $sharerClientId;
    private string $encryptedMetadata;
    private ?string $cloudLink;
    private int $createdAt;

    public function __construct(
        string $id,
        string $channelName,
        string $sharerClientId,
        string $encryptedMetadata,
        ?string $cloudLink = null,
        ?int $createdAt = null
    ) {
        $this->id = trim($id);
        $this->channelName = trim($channelName);
        $this->sharerClientId = trim($sharerClientId);
        $this->encryptedMetadata = $encryptedMetadata;
        $this->cloudLink = $cloudLink !== null && trim($cloudLink) !== '' ? trim($cloudLink) : null;
        $this->createdAt = $createdAt ?? time();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function getSharerClientId(): string
    {
        return $this->sharerClientId;
    }

    public function getEncryptedMetadata(): string
    {
        return $this->encryptedMetadata;
    }

    public function getCloudLink(): ?string
    {
        return $this->cloudLink;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel_name' => $this->channelName,
            'sharer_client_id' => $this->sharerClientId,
            'encrypted_metadata' => $this->encryptedMetadata,
            'cloud_link' => $this->cloudLink,
            'created_at' => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['channel_name'] ?? ''),
            (string)($data['sharer_client_id'] ?? ''),
            (string)($data['encrypted_metadata'] ?? ''),
            isset($data['cloud_link']) ? (string)$data['cloud_link'] : null,
            isset($data['created_at']) ? (int)$data['created_at'] : null
        );
    }
}
