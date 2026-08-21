<?php

declare(strict_types=1);

namespace cx\ivc\Database;

use cx\ivc\Models\SharedFile;

/**
 * Data Access Repository for shared file metadata (shared_files).
 */
class SharedFileRepository
{
    /**
     * Find file metadata by ID.
     */
    public static function findById(string $id): ?SharedFile
    {
        $row = Database::fetchOne(
            "SELECT id, channel_name, sharer_client_id, encrypted_metadata, cloud_link, created_at FROM shared_files WHERE id = :id",
            [':id' => trim($id)]
        );

        return $row !== null ? SharedFile::fromArray($row) : null;
    }

    /**
     * Save (insert or update) shared file metadata.
     */
    public static function save(SharedFile $file): bool
    {
        $exists = self::findById($file->getId()) !== null;

        if ($exists) {
            $stmt = Database::execute(
                "UPDATE shared_files SET channel_name = :chan, sharer_client_id = :sharer, encrypted_metadata = :meta, cloud_link = :cloud WHERE id = :id",
                [
                    ':chan' => $file->getChannelName(),
                    ':sharer' => $file->getSharerClientId(),
                    ':meta' => $file->getEncryptedMetadata(),
                    ':cloud' => $file->getCloudLink(),
                    ':id' => $file->getId()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO shared_files (id, channel_name, sharer_client_id, encrypted_metadata, cloud_link, created_at) VALUES (:id, :chan, :sharer, :meta, :cloud, :created)",
                [
                    ':id' => $file->getId(),
                    ':chan' => $file->getChannelName(),
                    ':sharer' => $file->getSharerClientId(),
                    ':meta' => $file->getEncryptedMetadata(),
                    ':cloud' => $file->getCloudLink(),
                    ':created' => $file->getCreatedAt()
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Fetch all shared file metadata for a channel.
     *
     * @return array<int, SharedFile>
     */
    public static function findByChannel(string $channelName): array
    {
        $rows = Database::fetchAll(
            "SELECT id, channel_name, sharer_client_id, encrypted_metadata, cloud_link, created_at FROM shared_files WHERE LOWER(channel_name) = LOWER(:chan) ORDER BY created_at ASC",
            [':chan' => trim($channelName)]
        );

        $files = [];
        foreach ($rows as $row) {
            $files[] = SharedFile::fromArray($row);
        }

        return $files;
    }

    /**
     * Delete file metadata by ID.
     */
    public static function deleteById(string $id): bool
    {
        $stmt = Database::execute(
            "DELETE FROM shared_files WHERE id = :id",
            [':id' => trim($id)]
        );

        return $stmt->rowCount() > 0;
    }
}
