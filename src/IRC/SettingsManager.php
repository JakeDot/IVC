<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

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
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value, description, updated_at FROM irc_settings");
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description'] ?? null,
                'updated_at' => (int)$row['updated_at']
            ];
        }

        return $settings;
    }

    /**
     * Get a single setting value
     */
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM irc_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();

        return $val !== false ? (string)$val : $default;
    }

    /**
     * Set/update a serverwide setting
     */
    public static function setSetting(string $key, string $value, ?string $description = null): bool
    {
        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM irc_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            $sql = "UPDATE irc_settings SET setting_value = :val, updated_at = :time" . ($description !== null ? ", description = :desc" : "") . " WHERE setting_key = :key";
            $params = [':val' => $value, ':time' => $now, ':key' => $key];
            if ($description !== null) {
                $params[':desc'] = $description;
            }
            $updateStmt = $pdo->prepare($sql);
            return $updateStmt->execute($params);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO irc_settings (setting_key, setting_value, description, updated_at) VALUES (:key, :val, :desc, :time)");
            return $insertStmt->execute([
                ':key' => $key,
                ':val' => $value,
                ':desc' => $description,
                ':time' => $now
            ]);
        }
    }
}
