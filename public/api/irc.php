<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Database/Database.php';
require_once __DIR__ . '/../../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../../src/IRC/NameServ.php';
require_once __DIR__ . '/../../src/IRC/ChanServ.php';
require_once __DIR__ . '/../../src/IRC/MotdServ.php';
require_once __DIR__ . '/../../src/IRC/QuoteServ.php';
require_once __DIR__ . '/../../src/IRC/IrcServices.php';
require_once __DIR__ . '/../../src/Signaling/RoomManager.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\NameServ;
use Fortress\IRC\ChanServ;
use Fortress\IRC\MotdServ;
use Fortress\IRC\QuoteServ;
use Fortress\IRC\IrcServices;
use Fortress\Signaling\RoomManager;

SecurityHeaders::apply();

header('Content-Type: application/json');

$clientKey = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::check($clientKey, 120, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait.'], JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'settings';

    if ($action === 'settings') {
        $settings = SettingsManager::getAllSettings();
        echo json_encode(['status' => 'ok', 'settings' => $settings], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'channels') {
        $channels = ChanServ::listChannels();
        echo json_encode(['status' => 'ok', 'channels' => $channels], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'motd') {
        $motd = MotdServ::getMotd();
        echo json_encode(['status' => 'ok', 'motd' => $motd], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'random_quote') {
        $quote = QuoteServ::getRandomQuote();
        echo json_encode(['status' => 'ok', 'quote' => $quote], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'quotes') {
        $quotes = QuoteServ::listQuotes();
        echo json_encode(['status' => 'ok', 'quotes' => $quotes], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'info' && isset($_GET['channel'])) {
        $chan = Sanitizer::sanitizeRoomId($_GET['channel']);
        $info = ChanServ::getInfo($chan);
        echo json_encode(['status' => 'ok', 'info' => $info], JSON_THROW_ON_ERROR);
        exit;
    }

    echo json_encode(['status' => 'ok', 'message' => 'IVC IRC Services API Active'], JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        exit;
    }

    $sender = Sanitizer::sanitizeClientId($data['sender'] ?? $data['nickname'] ?? 'User');
    $channel = Sanitizer::sanitizeRoomId($data['channel'] ?? $data['room'] ?? '#lobby');
    $text = trim($data['text'] ?? $data['command'] ?? '');

    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Command text required'], JSON_THROW_ON_ERROR);
        exit;
    }

    $result = IrcServices::processCommand($sender, $channel, $text);

    if ($result !== null) {
        $broadcast = $data['broadcast'] ?? true;
        if ($broadcast) {
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

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
