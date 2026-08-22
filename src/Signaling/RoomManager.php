<?php

declare(strict_types=1);

namespace Fortress\Signaling;

/**
 * Ephemeral Memory Room Manager for WebRTC Signaling (Zero Disk Logging / Non-Logging)
 * Uses temporary RAM storage (/dev/shm or sys_get_temp_dir) for cross-process state persistence across HTTP requests
 */
final class RoomManager
{
    /**
     * Max message age in seconds before automatically purged from RAM
     */
    private const MESSAGE_TTL = 30;

    /**
     * Max peer inactivity in seconds before automatically removed from room
     */
    private const PEER_TTL = 60;

    /**
     * Path to shared RAM state file
     */
    private static function getStateFilePath(): string
    {
        $baseDir = is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
        $dir = $baseDir . '/fortress_' . md5(__DIR__);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/fortress_webrtc_rooms_state.json';
    }

    /**
     * Memory cache of the last read/written state hashes to avoid redundant parsing
     */
    private static array $lastKnownHashes = [];

    /**
     * Load active rooms from shared RAM storage
     *
     * @param string|null $expectedHash If provided, and the file hash matches, returns null to indicate no change
     * @return array<string, array<string, array{last_active: int, messages: array<int, array<string, mixed>>}>>|null
     */
    private static function loadRooms(?string $expectedHash = null): ?array
    {
        $filePath = self::getStateFilePath();
        if (!file_exists($filePath)) {
            return [];
        }

        $fp = @fopen($filePath, 'r');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if (empty($content)) {
            return [];
        }

        if ($expectedHash !== null) {
            $currentHash = md5($content);
            if ($currentHash === $expectedHash) {
                return null; // Indicates no change
            }
        }

        try {
            $data = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : [];
        } catch (\JsonException $e) {
            return [];
        }
    }

    /**
     * Save active rooms to shared RAM storage atomically with flock
     *
     * @param array<string, array<string, array{last_active: int, messages: array<int, array<string, mixed>>}>> $rooms
     * @return string The MD5 hash of the saved JSON content
     */
    private static function saveRooms(array $rooms): string
    {
        $filePath = self::getStateFilePath();
        $fp = @fopen($filePath, 'c+');
        $json = json_encode($rooms, JSON_THROW_ON_ERROR);
        $hash = md5($json);

        if (!$fp) {
            return $hash;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return $hash;
    }

    /**
     * Join room or refresh peer presence in room
     */
    public static function joinRoom(string $roomId, string $clientId): array
    {
        if (class_exists('\Fortress\IRC\ChanServ')) {
            $access = \Fortress\IRC\ChanServ::checkAccess($roomId, $clientId);
            if (!$access['success']) {
                return ['error' => true, 'code' => $access['code'] ?? 403, 'message' => $access['message']];
            }
            $roomId = $access['base_target'];
        }
        
        $rooms = self::loadRooms() ?? [];
        $rooms = self::gcInternal($rooms);

        if (!isset($rooms[$roomId])) {
            $rooms[$roomId] = [];
        }

        if (!isset($rooms[$roomId][$clientId])) {
            $rooms[$roomId][$clientId] = [
                'last_active' => time(),
                'messages' => []
            ];

            // Notify other peers in room about new peer
            $rooms = self::broadcastSignalInternal($rooms, $roomId, $clientId, [
                'type' => 'peer-joined',
                'sender' => $clientId,
                'timestamp' => time()
            ], true);
        } else {
            $rooms[$roomId][$clientId]['last_active'] = time();
        }

        self::saveRooms($rooms);

        $peers = array_keys($rooms[$roomId]);
        return [
            'roomId' => $roomId,
            'clientId' => $clientId,
            'peers' => array_values(array_filter($peers, fn($p) => $p !== $clientId))
        ];
    }

    /**
     * Dispatch signal/message to a room or specific target peer
     *
     * @param string $roomId
     * @param string $senderId
     * @param array<string, mixed> $payload
     * @param bool $excludeSender
     */
    public static function broadcastSignal(string $roomId, string $senderId, array $payload, bool $excludeSender = true): void
    {
        $rooms = self::loadRooms() ?? [];
        $rooms = self::broadcastSignalInternal($rooms, $roomId, $senderId, $payload, $excludeSender);
        self::saveRooms($rooms);
    }

    private static function broadcastSignalInternal(array $rooms, string $roomId, string $senderId, array $payload, bool $excludeSender = true): array
    {
        $targetPeer = $payload['target'] ?? null;
        $isSupersilent = !empty($payload['supersilent']) || !empty($payload['is_supersilent']);

        // Deliver to target room
        if (isset($rooms[$roomId])) {
            $message = array_merge($payload, [
                'sender' => $senderId,
                'room' => $roomId,
                'time' => time()
            ]);

            foreach ($rooms[$roomId] as $peerId => &$peerData) {
                if ($excludeSender && $peerId === $senderId) {
                    continue;
                }

                if ($targetPeer !== null && is_string($targetPeer) && $peerId !== $targetPeer) {
                    continue;
                }

                $peerData['messages'][] = $message;
                if (count($peerData['messages']) > 50) {
                    array_shift($peerData['messages']);
                }
            }
            unset($peerData);
        }

        // Deliver to subrooms if message is not supersilent and not targeted to a specific peer
        if (!$isSupersilent && $targetPeer === null) {
            $superRoomPrefix = $roomId . '/';
            foreach ($rooms as $subRoomId => &$subPeers) {
                if ($subRoomId === $roomId) {
                    continue;
                }

                if (str_starts_with($subRoomId, $superRoomPrefix)) {
                    $subMessage = array_merge($payload, [
                        'sender' => $senderId,
                        'room' => $roomId,
                        'super_room' => $roomId,
                        'time' => time()
                    ]);

                    foreach ($subPeers as $peerId => &$peerData) {
                        if ($excludeSender && $peerId === $senderId) {
                            continue;
                        }

                        $peerData['messages'][] = $subMessage;
                        if (count($peerData['messages']) > 50) {
                            array_shift($peerData['messages']);
                        }
                    }
                    unset($peerData);
                }
            }
            unset($subPeers);
        }

        return $rooms;
    }

    /**
     * Fetch unread signals/messages for a client and immediately purge them (Non-logging / zero retention)
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pollMessages(string $roomId, string $clientId): array
    {
        // For SSE loops calling this constantly, we can track the last known hash of our state.
        // If the file contents haven't changed since our last read/write, there are no new messages.
        // However, we still need to occasionally force a load to update last_active.
        $cacheKey = "$roomId:$clientId";
        $canUseCache = isset(self::$lastKnownHashes[$cacheKey]);

        $rooms = self::loadRooms($canUseCache ? self::$lastKnownHashes[$cacheKey] : null);

        if ($rooms === null) {
            // The file on disk exactly matches our last known hash.
            // This means NO other process has modified the file since we last touched it.
            // There cannot be any new messages for us.
            return [];
        }

        $originalRooms = $rooms;
        $rooms = self::gcInternal($rooms);

        if (!isset($rooms[$roomId][$clientId])) {
            self::$lastKnownHashes[$cacheKey] = self::saveRooms($rooms);
            self::joinRoom($roomId, $clientId);
            return [];
        }

        $messages = $rooms[$roomId][$clientId]['messages'];
        $needsSave = ($rooms !== $originalRooms);

        // Only update last_active if it's older than 10 seconds to avoid constant disk I/O
        if (time() - $rooms[$roomId][$clientId]['last_active'] >= 10) {
            $rooms[$roomId][$clientId]['last_active'] = time();
            $needsSave = true;
        }

        if (!empty($messages)) {
            // Zero-retention: Clear delivered messages immediately from RAM
            $rooms[$roomId][$clientId]['messages'] = [];
            $needsSave = true;
        }

        if ($needsSave) {
            self::$lastKnownHashes[$cacheKey] = self::saveRooms($rooms);
        } else {
            // State didn't change (no save), but we loaded it and want to track the current hash
            self::$lastKnownHashes[$cacheKey] = md5(json_encode($rooms, JSON_THROW_ON_ERROR));
        }

        return $messages;
    }

    /**
     * Explicitly leave a room
     */
    public static function leaveRoom(string $roomId, string $clientId): void
    {
        $rooms = self::loadRooms() ?? [];

        if (isset($rooms[$roomId][$clientId])) {
            unset($rooms[$roomId][$clientId]);
            $rooms = self::broadcastSignalInternal($rooms, $roomId, $clientId, [
                'type' => 'peer-left',
                'sender' => $clientId,
                'timestamp' => time()
            ], true);
        }

        if (isset($rooms[$roomId]) && empty($rooms[$roomId])) {
            unset($rooms[$roomId]);
        }

        self::saveRooms($rooms);
    }

    /**
     * Return active peer count in room
     */
    public static function getPeerCount(string $roomId): int
    {
        $rooms = self::loadRooms() ?? [];
        return isset($rooms[$roomId]) ? count($rooms[$roomId]) : 0;
    }

    /**
     * Garbage collection to clean stale peers and empty rooms from memory
     */
    public static function gc(): void
    {
        $rooms = self::loadRooms() ?? [];
        $rooms = self::gcInternal($rooms);
        self::saveRooms($rooms);
    }

    private static function gcInternal(array $rooms): array
    {
        $now = time();

        foreach ($rooms as $roomId => $peers) {
            foreach ($peers as $peerId => $peerData) {
                if ($now - $peerData['last_active'] > self::PEER_TTL) {
                    unset($rooms[$roomId][$peerId]);
                    $rooms = self::broadcastSignalInternal($rooms, $roomId, $peerId, [
                        'type' => 'peer-left',
                        'sender' => $peerId,
                        'reason' => 'timeout'
                    ], true);
                }
            }

            if (empty($rooms[$roomId])) {
                unset($rooms[$roomId]);
            }
        }

        return $rooms;
    }

    /**
     * Reset all active room states
     */
    public static function reset(): void
    {
        self::$lastKnownHashes = [];
        $filePath = self::getStateFilePath();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
