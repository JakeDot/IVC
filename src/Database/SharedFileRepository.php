<?php
declare(strict_types=1);
namespace Fortress\Database;
use Fortress\Models\SharedFile;

class SharedFileRepository
{
    public static function findById(string $id): ?SharedFile
    {
        $coll = Database::getCollection('shared_files');
        $row = $coll->findOne(['id' => trim($id)]);
        return $row !== null ? SharedFile::fromArray($row) : null;
    }

    public static function save(SharedFile $file): bool
    {
        $coll = Database::getCollection('shared_files');
        $exists = self::findById($file->getId()) !== null;
        $doc = [
            'id' => $file->getId(),
            'channel_name' => $file->getChannelName(),
            'sharer_client_id' => $file->getSharerClientId(),
            'encrypted_metadata' => $file->getEncryptedMetadata(),
            'cloud_link' => $file->getCloudLink(),
            'created_at' => $file->getCreatedAt()
        ];
        
        if ($exists) {
            $coll->updateOne(['id' => $file->getId()], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function findByChannel(string $channelName): array
    {
        $coll = Database::getCollection('shared_files');
        $rows = $coll->find(
            ['channel_name' => ['$regex' => '^' . preg_quote(trim($channelName), '/') . '$', '$options' => 'i']],
            ['sort' => ['created_at' => -1]]
        );
        $files = [];
        foreach ($rows as $row) {
            $files[] = SharedFile::fromArray($row);
        }
        return $files;
    }

    public static function delete(string $id): bool
    {
        $coll = Database::getCollection('shared_files');
        $coll->deleteOne(['id' => trim($id)]);
        return true;
    }
}
