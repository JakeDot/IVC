<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\ObjectReaction;

/**
 * ObjectReactionRepository
 * Data access repository for object reactions attached to IVC addressable objects.
 */
class ObjectReactionRepository
{
    private const COLLECTION_NAME = 'object_reactions';

    public static function save(ObjectReaction $reaction): bool
    {
        $coll = Database::getCollection(self::COLLECTION_NAME);
        $objUri = $reaction->getObjectUri();
        $emoji = $reaction->getEmoji();
        $sender = $reaction->getSenderNick();

        $existing = $coll->findOne([
            'object_uri' => $objUri,
            'emoji' => $emoji,
            'sender_nick' => $sender
        ]);

        if ($existing !== null) {
            $coll->updateOne(
                [
                    'object_uri' => $objUri,
                    'emoji' => $emoji,
                    'sender_nick' => $sender
                ],
                [
                    '$set' => [
                        'reacted_at' => $reaction->getReactedAt()
                    ]
                ]
            );
            return true;
        }

        $coll->insertOne($reaction->toArray());
        return true;
    }

    /**
     * Get equivalent object URIs.
     * Network comments are addressable as ivc://:comment-id or ivc://£:comment-id (the £ is optional).
     * Object comments are addressable as ivc://<object>/:comment-id.
     */
    public static function getEquivalentObjectUris(string $objectUri): array
    {
        $objectUri = trim($objectUri);
        $uris = [$objectUri];

        // Network comments: ivc://:comment-id <-> ivc://£:comment-id
        if (preg_match('#^ivc://:([a-zA-Z0-9_\-\.:]+)$#', $objectUri, $m)) {
            $withPound = 'ivc://£:' . $m[1];
            if (!in_array($withPound, $uris, true)) {
                $uris[] = $withPound;
            }
        } elseif (preg_match('#^ivc://£:([a-zA-Z0-9_\-\.:]+)$#', $objectUri, $m)) {
            $withoutPound = 'ivc://:' . $m[1];
            if (!in_array($withoutPound, $uris, true)) {
                $uris[] = $withoutPound;
            }
        }

        return $uris;
    }

    /**
     * @return ObjectReaction[]
     */
    public static function findByObjectUri(string $objectUri): array
    {
        $coll = Database::getCollection(self::COLLECTION_NAME);
        $equivalentUris = self::getEquivalentObjectUris($objectUri);

        $reactions = [];
        $seen = [];

        foreach ($equivalentUris as $uri) {
            $docs = $coll->find(['object_uri' => $uri]);
            foreach ($docs as $doc) {
                $key = ($doc['emoji'] ?? '') . ':' . ($doc['sender_nick'] ?? '');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $reactions[] = ObjectReaction::fromArray($doc);
                }
            }
        }

        return $reactions;
    }

    /**
     * Get aggregated reaction statistics by emoji for a given object URI.
     *
     * @param string $objectUri
     * @return array{object: string, reactions: array<string, array{count: int, users: string[]}>, total_count: int, reactors: string[]}
     */
    public static function getAggregatedReactions(string $objectUri): array
    {
        $objectUri = trim($objectUri);
        $reactions = self::findByObjectUri($objectUri);

        $summary = [];
        $reactors = [];
        $totalCount = 0;

        foreach ($reactions as $r) {
            $emoji = $r->getEmoji();
            $nick = $r->getSenderNick();

            if (!isset($summary[$emoji])) {
                $summary[$emoji] = [
                    'count' => 0,
                    'users' => []
                ];
            }

            $summary[$emoji]['count']++;
            if (!in_array($nick, $summary[$emoji]['users'], true)) {
                $summary[$emoji]['users'][] = $nick;
            }

            if (!in_array($nick, $reactors, true)) {
                $reactors[] = $nick;
            }

            $totalCount++;
        }

        return [
            'object' => $objectUri,
            'reactions' => $summary,
            'total_count' => $totalCount,
            'reactors' => $reactors
        ];
    }

    public static function delete(string $objectUri, string $emoji, string $senderNick): bool
    {
        $coll = Database::getCollection(self::COLLECTION_NAME);
        $coll->deleteOne([
            'object_uri' => trim($objectUri),
            'emoji' => trim($emoji),
            'sender_nick' => trim($senderNick)
        ]);
        return true;
    }

    public static function clear(string $objectUri): bool
    {
        $coll = Database::getCollection(self::COLLECTION_NAME);
        $coll->deleteMany(['object_uri' => trim($objectUri)]);
        return true;
    }
}
