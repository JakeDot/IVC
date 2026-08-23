<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
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
require_once __DIR__ . '/../../src/Services/WhatsAppService.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\RateLimiter;
use Fortress\Services\WhatsAppService;

SecurityHeaders::apply();
header('Content-Type: application/json');

$clientKey = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::check($clientKey, 120, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded.'], JSON_THROW_ON_ERROR);
    die();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        die();
    }

    $response = WhatsAppService::handleWebhook($payload);

    if (!$response['success']) {
        http_response_code(400);
    }

    echo json_encode($response, JSON_THROW_ON_ERROR);
    die();
}

if ($method === 'GET' && isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $challenge = $_GET['hub_challenge'] ?? '';
    echo htmlspecialchars($challenge, ENT_QUOTES, 'UTF-8');
    die();
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
