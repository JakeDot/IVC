<?php

declare(strict_types=1);

namespace cx\ivc\IRC;

use cx\ivc\Database\SettingRepository;
use cx\ivc\Models\IrcSetting;

/**
 * Manages serverwide IRC settings stored in MySQL (or fallback DB).
 */
class SettingsManager
{
    /**
     * Get all serverwide IRC settings
     *
     * @return array<string, array{value: string, description: string|null, updated_at: int}>
     */
    public static function getAllSettings(): array
    {
        $all = SettingRepository::findAll();
        $settings = [];

        foreach ($all as $key => $setting) {
            $settings[$key] = [
                'value' => $setting->getSettingValue(),
                'description' => $setting->getDescription(),
                'updated_at' => $setting->getUpdatedAt()
            ];
        }

        return $settings;
    }

    /**
     * Get a single setting value
     */
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $setting = SettingRepository::findByKey($key);
        return $setting !== null ? $setting->getSettingValue() : $default;
    }

    /**
     * Set/update a serverwide setting
     */
    public static function setSetting(string $key, string $value, ?string $description = null): bool
    {
        $setting = SettingRepository::findByKey($key);
        if ($setting !== null) {
            $setting->setSettingValue($value);
            if ($description !== null) {
                $setting->setDescription($description);
            }
            $setting->setUpdatedAt(time());
        } else {
            $setting = new IrcSetting($key, $value, $description);
        }

        return SettingRepository::save($setting);
    }
}
