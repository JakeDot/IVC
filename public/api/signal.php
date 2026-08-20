<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Security/TokenManager.php';
require_once __DIR__ . '/../../src/Database/Database.php';
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
use Fortress\Security\TokenManager;
use Fortress\IRC\IrcServices;
use Fortress\IRC\ChanServ;
use Fortress\Signaling\RoomManager;

header('Content-Type: application/json');

// Rate limiting check based on session ID or anonymized IP hash
$clientKey = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::check($clientKey, 120, 60)) {
    SecurityHeaders::apply(429);
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait.'], JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Poll messages or check SSE
    $roomId = Sanitizer::sanitizeRoomId($_GET['room'] ?? '');
    $clientId = Sanitizer::sanitizeClientId($_GET['client'] ?? '');
    $mode = $_GET['mode'] ?? 'poll';

    if (empty($roomId) || empty($clientId)) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Room ID and Client ID required'], JSON_THROW_ON_ERROR);
        exit;
    }

    // Calculate app modes if applicable to the sender/channel context
    $appModes = $clientId;
    $chanInfo = ChanServ::getInfo($roomId);
    if ($chanInfo['success']) {
        $modes = $chanInfo['data']['modes'] ?? '';
        $opStatus = ChanServ::isOp($roomId, $clientId) ? '+o' : '';
        $appModes .= "{subs [{$roomId}{$modes}{$opStatus}]}";
    }

    SecurityHeaders::apply(200, $appModes);

    if ($mode === 'sse') {
        // SSE (Server-Sent Events) realtime streaming mode
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        RoomManager::joinRoom($roomId, $clientId);

        $start = time();
        while (time() - $start < 25) { // Run SSE loop max 25s per connection
            $messages = RoomManager::pollMessages($roomId, $clientId);
            if (!empty($messages)) {
                foreach ($messages as $msg) {
                    echo "data: " . json_encode($msg, JSON_THROW_ON_ERROR) . "\n\n";
                }
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } else {
                // Send keep-alive ping comment
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            usleep(200000); // 200ms poll delay
        }
        exit;
    }

    // Standard HTTP Poll Mode
    $roomInfo = RoomManager::joinRoom($roomId, $clientId);
    $messages = RoomManager::pollMessages($roomId, $clientId);

    echo json_encode([
        'status' => 'ok',
        'peers' => $roomInfo['peers'],
        'messages' => $messages,
        'csrf_token' => TokenManager::generateCsrfToken()
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST' || $method === 'PUT') {
    $rawPayload = file_get_contents('php://input');
    if ($rawPayload === false) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request payload'], JSON_THROW_ON_ERROR);
        exit;
    }

    $payload = Sanitizer::validateSignalPayload($rawPayload);
    if ($payload === null) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or malformed signal payload'], JSON_THROW_ON_ERROR);
        exit;
    }

    $roomId = Sanitizer::sanitizeRoomId($payload['room'] ?? $_GET['room'] ?? '');
    $clientId = Sanitizer::sanitizeClientId($payload['client'] ?? $_GET['client'] ?? '');

    if (empty($roomId) || empty($clientId)) {
        SecurityHeaders::apply(400);
        http_response_code(400);
        echo json_encode(['error' => 'Room ID and Client ID required'], JSON_THROW_ON_ERROR);
        exit;
    }

    // Calculate app modes if applicable to the sender/channel context
    $appModes = $clientId;
    $chanInfo = ChanServ::getInfo($roomId);
    if ($chanInfo['success']) {
        $modes = $chanInfo['data']['modes'] ?? '';
        $opStatus = ChanServ::isOp($roomId, $clientId) ? '+o' : '';
        $appModes .= "{subs [{$roomId}{$modes}{$opStatus}]}";
    }

    SecurityHeaders::apply(200, $appModes);

    $action = $payload['type'] ?? '';

    if ($action === 'leave') {
        RoomManager::leaveRoom($roomId, $clientId);
        echo json_encode(['status' => 'left'], JSON_THROW_ON_ERROR);
        exit;
    }

    // Check if the target is a non-channel object (e.g. user, network, server) for PUT memos
    $prefix = mb_substr($roomId, 0, 1);
    if ($method === 'PUT' && in_array($prefix, ['@', '£', '$'], true)) {
        $chatMessage = $payload['message'] ?? $payload['text'] ?? '';
        $senderNick = $payload['nickname'] ?? $clientId;

        if (!empty($chatMessage)) {
            require_once __DIR__ . '/../../src/IRC/MemoServ.php';
            $target = ltrim($roomId, '@£$');
            \Fortress\IRC\MemoServ::send($senderNick, $target, "[PUT Notice] " . $chatMessage);
            echo json_encode(['status' => 'sent', 'is_memo' => true, 'message' => "Notice posted to non-channel object {$roomId}."], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    // Join & broadcast signal (offer, answer, ice-candidate, chat, etc)
    RoomManager::joinRoom($roomId, $clientId);

    // Process IRC Commands if message/text provided
    $chatMessage = $payload['message'] ?? $payload['text'] ?? null;
    $senderNick = $payload['nickname'] ?? $clientId;

    if (!empty($chatMessage) && is_string($chatMessage)) {
        $svcResult = IrcServices::processCommand($senderNick, $roomId, $chatMessage);
        if ($svcResult !== null) {
            // Broadcast IRC Bot Response to room
            RoomManager::broadcastSignal($roomId, 'SYSTEM_BOT', [
                'type' => 'chat',
                'sender' => $svcResult['service'],
                'message' => $svcResult['response'],
                'is_bot' => true
            ], false);

            echo json_encode(['status' => 'sent', 'is_service' => true, 'response' => $svcResult['response']], JSON_THROW_ON_ERROR);
            exit;
        }
    }

    RoomManager::broadcastSignal($roomId, $clientId, $payload, true);

    echo json_encode(['status' => 'sent'], JSON_THROW_ON_ERROR);
    exit;
}

SecurityHeaders::apply(405);
http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
