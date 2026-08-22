<?php
declare(strict_types=1);
namespace Fortress\Database;

class SettingRepository
{
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $coll = Database::getCollection('irc_settings');
        $row = $coll->findOne(['setting_key' => $key]);
        if ($row !== null && isset($row['setting_value'])) {
            return (string)$row['setting_value'];
        }
        return $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::getSetting($key);
        if ($val === null) return $default;
        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::getSetting($key);
        if ($val === null) return $default;
        return (int)$val;
    }

    public static function setSetting(string $key, string $value, ?string $description = null): bool
    {
        $coll = Database::getCollection('irc_settings');
        $now = time();
        $existing = $coll->findOne(['setting_key' => $key]);
        if ($existing) {
            $update = ['setting_value' => $value, 'updated_at' => $now];
            if ($description !== null) {
                $update['description'] = $description;
            }
            $coll->updateOne(['setting_key' => $key], ['$set' => $update]);
        } else {
            $coll->insertOne([
                'setting_key' => $key,
                'setting_value' => $value,
                'description' => $description,
                'updated_at' => $now
            ]);
        }
        return true;
    }

    public static function getAllSettings(): array
    {
        $coll = Database::getCollection('irc_settings');
        return $coll->find([]);
    }
}
