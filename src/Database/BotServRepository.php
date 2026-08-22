<?php
declare(strict_types=1);
namespace Fortress\Database;

class BotServRepository
{
    public static function assignBot(string $target, string $botNick, string $serviceName, string $assignedBy): bool
    {
        $coll = Database::getCollection('botserv_bots');
        $doc = [
            'target' => strtoupper($target),
            'bot_nick' => $botNick,
            'service_name' => $serviceName,
            'assigned_by' => $assignedBy,
            'assigned_at' => time()
        ];
        
        $existing = $coll->findOne([
            'target' => strtoupper($target),
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        
        if ($existing) {
            $coll->updateOne([
                'target' => strtoupper($target),
                'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
            ], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function unassignBot(string $target, string $botNick): bool
    {
        $coll = Database::getCollection('botserv_bots');
        $coll->deleteOne([
            'target' => strtoupper($target),
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        return true;
    }

    public static function getAssignedBot(string $target, string $botNick): ?string
    {
        $coll = Database::getCollection('botserv_bots');
        $row = $coll->findOne([
            'target' => strtoupper($target),
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ]);
        return $row !== null ? (string)$row['service_name'] : null;
    }

    public static function getAllBotsForTarget(string $target): array
    {
        $coll = Database::getCollection('botserv_bots');
        $rows = $coll->find([
            '$or' => [
                ['target' => strtoupper($target)],
                ['target' => 'GLOBAL']
            ]
        ]);
        return array_map(function($r) {
            return ['bot_nick' => $r['bot_nick'], 'service_name' => $r['service_name']];
        }, $rows);
    }

    public static function resolveBotService(string $channel, string $botNick): ?string
    {
        $coll = Database::getCollection('botserv_bots');
        $rows = $coll->find([
            '$or' => [
                ['target' => strtoupper($channel)],
                ['target' => 'GLOBAL']
            ],
            'bot_nick' => ['$regex' => '^' . preg_quote($botNick, '/') . '$', '$options' => 'i']
        ], ['sort' => ['target' => -1]]);
        if (!empty($rows)) {
            return (string)$rows[0]['service_name'];
        }
        return null;
    }
}
