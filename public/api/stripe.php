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
use cx\ivc\Security\Sanitizer;
use cx\ivc\Security\RateLimiter;
use cx\ivc\Services\StripeService;
use cx\ivc\Database\SubscriptionRepository;

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
    $action = $_GET['action'] ?? 'plans';

    if ($action === 'plans') {
        $plans = StripeService::getPlans();
        $pubKey = StripeService::getPublishableKey();
        echo json_encode([
            'status' => 'ok',
            'publishable_key' => $pubKey,
            'plans' => $plans
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'status') {
        $targetType = Sanitizer::sanitizeClientId($_GET['target_type'] ?? $_GET['type'] ?? 'user');
        $targetName = Sanitizer::sanitizeClientId($_GET['target_name'] ?? $_GET['name'] ?? '');
        $subscriber = Sanitizer::sanitizeClientId($_GET['subscriber'] ?? '');

        if (!empty($targetName)) {
            $sub = SubscriptionRepository::findActiveByTarget($targetType, $targetName);
            echo json_encode([
                'status' => 'ok',
                'target_type' => $targetType,
                'target_name' => $targetName,
                'has_active_subscription' => $sub !== null,
                'subscription' => $sub !== null ? $sub->toArray() : null
            ], JSON_THROW_ON_ERROR);
            exit;
        }

        if (!empty($subscriber)) {
            $subs = SubscriptionRepository::findAllBySubscriber($subscriber);
            $subData = array_map(fn($s) => $s->toArray(), $subs);
            echo json_encode([
                'status' => 'ok',
                'subscriber' => $subscriber,
                'subscriptions' => $subData
            ], JSON_THROW_ON_ERROR);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'target_name or subscriber required'], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'success') {
        $sessionId = $_GET['session_id'] ?? '';
        echo json_encode([
            'status' => 'ok',
            'message' => 'Stripe Checkout completed successfully!',
            'session_id' => $sessionId
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'cancel') {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Stripe Checkout was canceled.'
        ], JSON_THROW_ON_ERROR);
        exit;
    }
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        exit;
    }

    $action = $data['action'] ?? 'checkout';

    if ($action === 'checkout') {
        $targetType = Sanitizer::sanitizeClientId($data['target_type'] ?? $data['type'] ?? 'user');
        $targetName = Sanitizer::sanitizeClientId($data['target_name'] ?? $data['name'] ?? $data['subscriber_nick'] ?? 'User');
        $planId = Sanitizer::sanitizeClientId($data['plan_id'] ?? 'nick_pro');
        $subscriberNick = Sanitizer::sanitizeClientId($data['subscriber_nick'] ?? $data['sender'] ?? 'User');
        $successUrl = $data['success_url'] ?? 'http://localhost/api/stripe.php?action=success';
        $cancelUrl = $data['cancel_url'] ?? 'http://localhost/api/stripe.php?action=cancel';

        $res = StripeService::createCheckoutSession(
            $targetType,
            $targetName,
            $planId,
            $subscriberNick,
            $successUrl,
            $cancelUrl
        );

        echo json_encode(array_merge(['status' => 'ok'], $res), JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'cancel_subscription') {
        $subId = Sanitizer::sanitizeClientId($data['subscription_id'] ?? '');
        if (empty($subId)) {
            http_response_code(400);
            echo json_encode(['error' => 'subscription_id required'], JSON_THROW_ON_ERROR);
            exit;
        }

        $res = StripeService::cancelSubscription($subId);
        echo json_encode(array_merge(['status' => 'ok'], $res), JSON_THROW_ON_ERROR);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
