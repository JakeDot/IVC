<?php

declare(strict_types=1);

namespace Fortress\Database;

/**
 * MongoDB Collection Proxy in PHP
 * Delegates document store operations to the Node.js MongoDB store via vrzno.
 */
class MongoCollection
{
    private string $collectionName;
    private static $js = null;

    public function __construct(string $collectionName)
    {
        $this->collectionName = $collectionName;
    }

    private static function initJs(): void
    {
        if (self::$js === null) {
            self::$js = new \vrzno();
        }
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function find(array $query = [], array $options = []): array
    {
        self::initJs();
        $raw = self::$js->mongoFind($this->collectionName, json_encode($query), json_encode($options));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : [];
    }

    public function findOne(array $query = [], array $options = []): ?array
    {
        self::initJs();
        $raw = self::$js->mongoFindOne($this->collectionName, json_encode($query), json_encode($options));
        if ($raw === null || $raw === 'null' || $raw === '') {
            return null;
        }
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : null;
    }

    public function insertOne(array $document): array
    {
        self::initJs();
        $raw = self::$js->mongoInsert($this->collectionName, json_encode($document));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : ['acknowledged' => true];
    }

    public function insertMany(array $documents): array
    {
        $insertedCount = 0;
        foreach ($documents as $doc) {
            $res = $this->insertOne((array)$doc);
            if (!empty($res['acknowledged'])) {
                $insertedCount++;
            }
        }
        return ['acknowledged' => true, 'insertedCount' => $insertedCount];
    }

    public function updateOne(array $filter, array $update, array $options = []): array
    {
        self::initJs();
        $raw = self::$js->mongoUpdate($this->collectionName, json_encode($filter), json_encode($update), json_encode(array_merge($options, ['multi' => false])));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : ['acknowledged' => true, 'matchedCount' => 1, 'modifiedCount' => 1];
    }

    public function updateMany(array $filter, array $update, array $options = []): array
    {
        self::initJs();
        $raw = self::$js->mongoUpdate($this->collectionName, json_encode($filter), json_encode($update), json_encode(array_merge($options, ['multi' => true])));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : ['acknowledged' => true, 'matchedCount' => 1, 'modifiedCount' => 1];
    }

    public function deleteOne(array $filter): array
    {
        self::initJs();
        $raw = self::$js->mongoDelete($this->collectionName, json_encode($filter), json_encode(['single' => true]));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : ['acknowledged' => true, 'deletedCount' => 1];
    }

    public function deleteMany(array $filter = []): array
    {
        self::initJs();
        $raw = self::$js->mongoDelete($this->collectionName, json_encode($filter), json_encode(['single' => false]));
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : ['acknowledged' => true, 'deletedCount' => 1];
    }

    public function countDocuments(array $filter = []): int
    {
        self::initJs();
        return (int)self::$js->mongoCount($this->collectionName, json_encode($filter));
    }
}
