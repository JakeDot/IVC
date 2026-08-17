<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../src/Security/Sanitizer.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Security/TokenManager.php';
require_once __DIR__ . '/../src/Signaling/RoomManager.php';

use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\Security\TokenManager;
use Fortress\Signaling\RoomManager;

echo "=========================================\n";
echo " 🧪 Running Fortress WebRTC Test Suite\n";
echo "=========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  ✅ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "  ❌ FAIL: $message\n";
        $testsFailed++;
    }
}

// Test 1: Sanitizer
echo "1. Testing Sanitizer Sanitization & Validation...\n";
assertTest(Sanitizer::sanitizeRoomId('room123!@#') === 'room123', 'Sanitize invalid characters in room ID');
assertTest(Sanitizer::sanitizeClientId('peer-abc-123') === 'peer-abc-123', 'Valid client ID retained');

$validSignal = json_encode(['type' => 'offer', 'sdp' => ['type' => 'offer', 'sdp' => 'v=0...']]);
$invalidSignal = json_encode(['type' => 'malicious_type']);
assertTest(Sanitizer::validateSignalPayload($validSignal) !== null, 'Valid offer signal accepted');
assertTest(Sanitizer::validateSignalPayload($invalidSignal) === null, 'Disallowed signal type rejected');

// Test 2: Rate Limiter
echo "\n2. Testing Rate Limiter (Non-Logging Ephemeral Keying)...\n";
RateLimiter::reset();
$clientKey = '192.168.1.100';
$limitOk = true;
for ($i = 0; $i < 5; $i++) {
    $limitOk = $limitOk && RateLimiter::check($clientKey, 5, 60);
}
assertTest($limitOk === true, 'Allows requests up to limit');
assertTest(RateLimiter::check($clientKey, 5, 60) === false, 'Blocks requests exceeding limit');

// Test 3: Token Manager
echo "\n3. Testing Token Manager & Room Session Keys...\n";
$key1 = TokenManager::generateRoomKey();
$key2 = TokenManager::generateRoomKey();
assertTest(strlen($key1) === 32 && $key1 !== $key2, 'Generates unique 32-char hex room key');

// Test 4: Room Manager & Ephemeral Signaling
echo "\n4. Testing Ephemeral Non-Logging Room Manager...\n";
RoomManager::reset();
$room = 'test-fortress-room';
$user1 = 'user-1';
$user2 = 'user-2';

$roomInfo1 = RoomManager::joinRoom($room, $user1);
assertTest($roomInfo1['roomId'] === $room, 'User 1 joins room');
assertTest(count($roomInfo1['peers']) === 0, 'No peers initially');

$roomInfo2 = RoomManager::joinRoom($room, $user2);
assertTest(count($roomInfo2['peers']) === 1 && $roomInfo2['peers'][0] === $user1, 'User 2 sees User 1 as peer');

// User 1 polls messages -> should receive peer-joined from User 2
$user1Messages = RoomManager::pollMessages($room, $user1);
assertTest(count($user1Messages) === 1 && $user1Messages[0]['type'] === 'peer-joined', 'User 1 receives peer-joined signal when User 2 joins');

// Broadcast Offer from User 1 to User 2
RoomManager::broadcastSignal($room, $user1, ['type' => 'offer', 'sdp' => 'test-sdp'], true);

// User 1 polls again -> no messages (exclude sender worked)
$user1MessagesAfterOffer = RoomManager::pollMessages($room, $user1);
assertTest(empty($user1MessagesAfterOffer), 'User 1 does not receive own offer (exclude sender)');

// User 2 polls -> receives offer signal
$user2Messages = RoomManager::pollMessages($room, $user2);
assertTest(count($user2Messages) === 1, 'User 2 receives offer signal');
assertTest(isset($user2Messages[0]['type']) && $user2Messages[0]['type'] === 'offer', 'Signal payload match');

// Non-logging zero-retention verify
$user2MessagesAgain = RoomManager::pollMessages($room, $user2);
assertTest(empty($user2MessagesAgain), 'Delivered messages immediately purged from RAM (Zero Retention)');

RoomManager::leaveRoom($room, $user1);
assertTest(RoomManager::getPeerCount($room) === 1, 'User 1 left room, peer count updated');

echo "\n-----------------------------------------\n";
echo "Test Results: $testsPassed Passed, $testsFailed Failed.\n";
echo "-----------------------------------------\n\n";

if ($testsFailed > 0) {
    exit(1);
}
