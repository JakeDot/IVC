<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
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
require_once __DIR__ . '/../../src/Database/BotServRepository.php';
require_once __DIR__ . '/../../src/Database/TextServRepository.php';
require_once __DIR__ . '/../../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../../src/IRC/NameServ.php';
require_once __DIR__ . '/../../src/IRC/ChanServ.php';
require_once __DIR__ . '/../../src/IRC/MotdServ.php';
require_once __DIR__ . '/../../src/IRC/MemoServ.php';
require_once __DIR__ . '/../../src/IRC/HostServ.php';
require_once __DIR__ . '/../../src/IRC/ServiceRegistry.php';
require_once __DIR__ . '/../../src/IRC/ServServ.php';
require_once __DIR__ . '/../../src/IRC/HelpServ.php';
require_once __DIR__ . '/../../src/IRC/BotServ.php';
require_once __DIR__ . '/../../src/IRC/TextServ.php';
require_once __DIR__ . '/../../src/IRC/IrcServices.php';
require_once __DIR__ . '/../../src/Signaling/RoomManager.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\NameServ;
use Fortress\IRC\ChanServ;
use Fortress\IRC\MotdServ;
use Fortress\IRC\IrcServices;
use Fortress\Signaling\RoomManager;

header('Content-Type: application/json');

$clientKey = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::check($clientKey, 120, 60)) {
    SecurityHeaders::apply(429);
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait.'], JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'settings';

    if ($action === 'settings') {
        SecurityHeaders::apply(200);
        $settings = SettingsManager::getAllSettings();
        echo json_encode(['status' => 'ok', 'settings' => $settings], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'channels') {
        SecurityHeaders::apply(200);
        $channels = ChanServ::listChannels();
        echo json_encode(['status' => 'ok', 'channels' => $channels], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'motd') {
        SecurityHeaders::apply(200);
        $motd = MotdServ::getMotd();
        echo json_encode(['status' => 'ok', 'motd' => $motd], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'info' && isset($_GET['channel'])) {
        $chan = Sanitizer::sanitizeRoomId($_GET['channel']);
        $info = ChanServ::getInfo($chan);
        SecurityHeaders::apply(200);
        echo json_encode(['status' => 'ok', 'info' => $info], JSON_THROW_ON_ERROR);
        exit;
    }

    SecurityHeaders::apply(200);
    echo json_encode(['status' => 'ok', 'message' => 'IVC IRC Services API Active'], JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        exit;
    }

    $sender = Sanitizer::sanitizeClientId($data['sender'] ?? $data['nickname'] ?? 'User');
    $channel = Sanitizer::sanitizeRoomId($data['channel'] ?? $data['room'] ?? '#lobby');
    $text = trim($data['text'] ?? $data['command'] ?? '');

    if (empty($text)) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Command text required'], JSON_THROW_ON_ERROR);
        exit;
    }

    $result = IrcServices::processCommand($sender, $channel, $text);

    // Calculate app modes if applicable to the sender/channel context
    $appModes = $sender;
    $chanInfo = ChanServ::getInfo($channel);
    if ($chanInfo['success']) {
        $modes = $chanInfo['data']['modes'] ?? '';
        $opStatus = ChanServ::isOp($channel, $sender) ? '+o' : '';
        $appModes .= "{subs [{$channel}{$modes}{$opStatus}]}";
    }

    SecurityHeaders::apply(200, $appModes);

    if ($result !== null) {
        $broadcast = $data['broadcast'] ?? true;
        if ($broadcast && empty($result['skip_bot_broadcast'])) {
            RoomManager::broadcastSignal($channel, 'SYSTEM_BOT', [
                'type' => 'chat',
                'sender' => $result['service'],
                'message' => $result['response'],
                'is_bot' => true
            ], false);
        }

        echo json_encode([
            'status' => 'ok',
            'is_service_command' => true,
            'service' => $result['service'],
            'response' => $result['response'],
            'channel' => $result['channel']
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'is_service_command' => false,
        'message' => 'Not a service command'
    ], JSON_THROW_ON_ERROR);
    exit;
}

SecurityHeaders::apply(405);
http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
