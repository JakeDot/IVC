<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Models/UserNick.php';
require_once __DIR__ . '/../../src/Models/Channel.php';
require_once __DIR__ . '/../../src/Models/ChannelUser.php';
require_once __DIR__ . '/../../src/Models/IrcSetting.php';
require_once __DIR__ . '/../../src/Database/Database.php';
require_once __DIR__ . '/../../src/Database/UserNickRepository.php';
require_once __DIR__ . '/../../src/Database/ChannelRepository.php';
require_once __DIR__ . '/../../src/Database/ChannelUserRepository.php';
require_once __DIR__ . '/../../src/Database/SettingRepository.php';
require_once __DIR__ . '/../../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../../src/IRC/ChanServ.php';
require_once __DIR__ . '/../../src/Signaling/RoomManager.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\RateLimiter;
use Fortress\Database\Database;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\ChanServ;
use Fortress\Signaling\RoomManager;

SecurityHeaders::apply();

header('Content-Type: application/json');

$clientKey = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::check($clientKey, 120, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait.'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = Database::getConnection();
    $dbDriver = Database::getDriver();
    $dbConnected = true;

    $chanCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM chanserv_channels");
    $nickCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM nameserv_nicks");
} catch (\Throwable $e) {
    $dbDriver = 'unknown';
    $dbConnected = false;
    $chanCount = 0;
    $nickCount = 0;
}

$settings = SettingsManager::getAllSettings();

// Load signaling state from RAM to compute live room and client stats
$roomsStatePath = is_writable('/dev/shm') ? '/dev/shm/fortress_webrtc_rooms_state.json' : sys_get_temp_dir() . '/fortress_webrtc_rooms_state.json';
$activeRooms = [];
$totalClients = 0;

if (file_exists($roomsStatePath)) {
    $raw = @file_get_contents($roomsStatePath);
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $rId => $peers) {
                if (is_array($peers) && !empty($peers)) {
                    $peerCount = count($peers);
                    $activeRooms[] = [
                        'room' => $rId,
                        'peers' => $peerCount
                    ];
                    $totalClients += $peerCount;
                }
            }
        }
    }
}

$stats = [
    'timestamp' => time(),
    'php_version' => PHP_VERSION,
    'server_time' => date('c'),
    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    'database' => [
        'status' => $dbConnected ? 'Connected' : 'Disconnected',
        'driver' => $dbDriver,
        'registered_channels' => $chanCount,
        'registered_nicks' => $nickCount,
    ],
    'signaling' => [
        'active_rooms_count' => count($activeRooms),
        'total_clients_count' => $totalClients,
        'rooms' => $activeRooms,
    ],
    'network_settings' => [
        'network_name' => $settings['network_name']['value'] ?? 'IVC-IRC Network',
        'server_name' => $settings['server_name']['value'] ?? 'fortress.ivc.local',
        'motd' => $settings['motd']['value'] ?? '',
        'allow_anonymous' => $settings['allow_anonymous']['value'] ?? '1'
    ]
];

echo json_encode(['status' => 'ok', 'stats' => $stats], JSON_THROW_ON_ERROR);
