<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Models/UserNick.php';
require_once __DIR__ . '/../../src/Models/Channel.php';
require_once __DIR__ . '/../../src/Models/Subscription.php';
require_once __DIR__ . '/../../src/Models/IrcSetting.php';
require_once __DIR__ . '/../../src/Database/Database.php';
require_once __DIR__ . '/../../src/Database/UserNickRepository.php';
require_once __DIR__ . '/../../src/Database/ChannelRepository.php';
require_once __DIR__ . '/../../src/Database/SettingRepository.php';
require_once __DIR__ . '/../../src/Database/SubscriptionRepository.php';
require_once __DIR__ . '/../../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../../src/IRC/MemoServ.php';
require_once __DIR__ . '/../../src/Services/StripeService.php';

use cx\ivc\Security\SecurityHeaders;
use cx\ivc\Services\StripeService;

SecurityHeaders::apply();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = StripeService::getWebhookSecret();

// Verify HMAC signature if Stripe Signature header and non-sample webhook secret are set
if (!empty($sigHeader) && $webhookSecret !== 'whsec_sample') {
    if (!StripeService::verifyWebhookSignature((string)$payload, $sigHeader, $webhookSecret)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Stripe Webhook Signature'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$event = json_decode((string)$payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload'], JSON_THROW_ON_ERROR);
    exit;
}

$result = StripeService::handleWebhookEvent($event);
echo json_encode(array_merge(['status' => 'ok'], $result), JSON_THROW_ON_ERROR);
