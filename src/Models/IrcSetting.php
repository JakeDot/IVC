<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Model class representing a serverwide IRC setting (irc_settings).
 */
class IrcSetting
{
    private string $settingKey;
    private string $settingValue;
    private ?string $description;
    private int $updatedAt;

    public function __construct(
        string $settingKey,
        string $settingValue,
        ?string $description = null,
        ?int $updatedAt = null
    ) {
        $this->settingKey = trim($settingKey);
        $this->settingValue = $settingValue;
        $this->description = $description !== null ? trim($description) : null;
        $this->updatedAt = $updatedAt ?? time();
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): void
    {
        $this->settingKey = trim($settingKey);
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $settingValue): void
    {
        $this->settingValue = $settingValue;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description !== null ? trim($description) : null;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function toArray(): array
    {
        return [
            'setting_key' => $this->settingKey,
            'setting_value' => $this->settingValue,
            'description' => $this->description,
            'updated_at' => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['setting_key'] ?? ''),
            (string)($data['setting_value'] ?? ''),
            isset($data['description']) ? (string)$data['description'] : null,
            isset($data['updated_at']) ? (int)$data['updated_at'] : null
        );
    }
}
