<?php

declare(strict_types=1);

namespace cx\ivc\Database;

use cx\ivc\Models\IrcSetting;

/**
 * Data Access Repository for serverwide IRC settings (irc_settings).
 */
class SettingRepository
{
    /**
     * Find setting by key.
     */
    public static function findByKey(string $key): ?IrcSetting
    {
        $row = Database::fetchOne(
            "SELECT setting_key, setting_value, description, updated_at FROM irc_settings WHERE setting_key = :key",
            [':key' => trim($key)]
        );

        return $row !== null ? IrcSetting::fromArray($row) : null;
    }

    /**
     * Save (insert or update) setting.
     */
    public static function save(IrcSetting $setting): bool
    {
        $existing = self::findByKey($setting->getSettingKey());
        $now = time();

        if ($existing !== null) {
            $sql = "UPDATE irc_settings SET setting_value = :val, updated_at = :time"
                 . ($setting->getDescription() !== null ? ", description = :desc" : "")
                 . " WHERE setting_key = :key";

            $params = [
                ':val' => $setting->getSettingValue(),
                ':time' => $now,
                ':key' => $setting->getSettingKey()
            ];
            if ($setting->getDescription() !== null) {
                $params[':desc'] = $setting->getDescription();
            }

            $stmt = Database::execute($sql, $params);
        } else {
            $stmt = Database::execute(
                "INSERT INTO irc_settings (setting_key, setting_value, description, updated_at) VALUES (:key, :val, :desc, :time)",
                [
                    ':key' => $setting->getSettingKey(),
                    ':val' => $setting->getSettingValue(),
                    ':desc' => $setting->getDescription(),
                    ':time' => $now
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Fetch all serverwide settings.
     *
     * @return array<string, IrcSetting>
     */
    public static function findAll(): array
    {
        $rows = Database::fetchAll("SELECT setting_key, setting_value, description, updated_at FROM irc_settings");
        $settings = [];
        foreach ($rows as $row) {
            $setting = IrcSetting::fromArray($row);
            $settings[$setting->getSettingKey()] = $setting;
        }

        return $settings;
    }
}
