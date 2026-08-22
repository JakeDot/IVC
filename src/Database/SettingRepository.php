<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\IrcSetting;

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
<<<<<<< HEAD
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
=======
        $coll = Database::getCollection('irc_settings');
        $row = $coll->findOne(['setting_key' => trim($key)]);
        return $row !== null ? IrcSetting::fromArray($row) : null;
    }

    public static function save(IrcSetting $setting): bool
    {
        $coll = Database::getCollection('irc_settings');
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        $existing = self::findByKey($setting->getSettingKey());
        $now = time();

        if ($existing !== null) {
<<<<<<< HEAD
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
=======
            $update = ['setting_value' => $setting->getSettingValue(), 'updated_at' => $now];
            if ($setting->getDescription() !== null) {
                $update['description'] = $setting->getDescription();
            }
            $coll->updateOne(['setting_key' => $setting->getSettingKey()], ['$set' => $update]);
        } else {
            $coll->insertOne([
                'setting_key' => $setting->getSettingKey(),
                'setting_value' => $setting->getSettingValue(),
                'description' => $setting->getDescription(),
                'updated_at' => $now
            ]);
        }
        return true;
    }

    public static function findAll(): array
    {
        $coll = Database::getCollection('irc_settings');
        $rows = $coll->find([]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        $settings = [];
        foreach ($rows as $row) {
            $setting = IrcSetting::fromArray($row);
            $settings[$setting->getSettingKey()] = $setting;
        }
<<<<<<< HEAD

=======
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        return $settings;
    }
}
