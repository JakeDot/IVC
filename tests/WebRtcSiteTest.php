<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../src/Security/Sanitizer.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Security/TokenManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../src/IRC/NameServ.php';
require_once __DIR__ . '/../src/IRC/ChanServ.php';
require_once __DIR__ . '/../src/IRC/MotdServ.php';
require_once __DIR__ . '/../src/IRC/QuoteServ.php';
require_once __DIR__ . '/../src/IRC/IrcServices.php';
require_once __DIR__ . '/../src/Signaling/RoomManager.php';

use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\Security\TokenManager;
use Fortress\Database\Database;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\NameServ;
use Fortress\IRC\ChanServ;
use Fortress\IRC\MotdServ;
use Fortress\IRC\QuoteServ;
use Fortress\IRC\IrcServices;
use Fortress\Signaling\RoomManager;

echo "=========================================\n";
echo " 🧪 Running Fortress WebRTC & IRC Test Suite\n";
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

// Test 1: Sanitizer & IRC #room scheme
echo "1. Testing Sanitizer & IRC #room channel scheme...\n";
assertTest(Sanitizer::sanitizeRoomId('room123!@#') === '#room123', 'Sanitize invalid characters and normalize to #room123');
assertTest(Sanitizer::sanitizeRoomId('#fortress-channel') === '#fortress-channel', 'Retain existing leading # in channel name');
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

// Test 4: Database Layer & Serverwide Settings
echo "\n4. Testing MySQL Database Layer & Serverwide IRC Settings...\n";
Database::resetDatabase();
$pdo = Database::getConnection();
assertTest($pdo !== null, 'Database connection established successfully');

$networkName = SettingsManager::getSetting('network_name');
assertTest($networkName === 'IVC-IRC Network', 'Default serverwide setting loaded from DB');

SettingsManager::setSetting('motd', 'New Fortress IRC MOTD');
assertTest(SettingsManager::getSetting('motd') === 'New Fortress IRC MOTD', 'Updated serverwide MOTD setting in DB');

// Test 5: NAMESERV (Nickname Service)
echo "\n5. Testing NAMESERV Nickname Registration & Identification...\n";
$regRes = NameServ::register('CyberFox', 'SecretPass123', 'fox@fortress.local');
assertTest($regRes['success'] === true, 'NAMESERV successfully registered nick CyberFox');
assertTest(NameServ::isRegistered('CyberFox') === true, 'NameServ::isRegistered returns true for CyberFox');

$idRes = NameServ::identify('CyberFox', 'SecretPass123');
assertTest($idRes['success'] === true, 'NAMESERV successfully identified CyberFox with correct password');

$idFail = NameServ::identify('CyberFox', 'WrongPassword');
assertTest($idFail['success'] === false, 'NAMESERV rejected incorrect password');

$infoRes = NameServ::getInfo('CyberFox');
assertTest($infoRes['success'] === true && str_contains($infoRes['message'], 'Registered:'), 'NAMESERV returned nick registration info');

// Test 6: CHANSERV (Channel Service)
echo "\n6. Testing CHANSERV Channel Management & OP Assignment...\n";
$chanReg = ChanServ::register('#fortress', 'CyberFox', 'channelkey123');
assertTest($chanReg['success'] === true, 'CHANSERV successfully registered channel #fortress with owner CyberFox');
assertTest(ChanServ::isRegistered('#fortress') === true, 'ChanServ::isRegistered returns true for #fortress');
assertTest(ChanServ::isOp('#fortress', 'CyberFox') === true, 'Channel owner CyberFox automatically granted OP status');

// Grant OP to second user
ChanServ::setRole('#fortress', 'Alice', 'MEMBER');
$opRes = ChanServ::op('#fortress', 'Alice', 'CyberFox');
assertTest($opRes['success'] === true && ChanServ::isOp('#fortress', 'Alice') === true, 'ChanServ granted OP to Alice');

// Update channel topic
$topicRes = ChanServ::setTopic('#fortress', 'Encryption and Security Fortress', 'CyberFox');
assertTest($topicRes['success'] === true, 'ChanServ updated channel topic');

$chanInfo = ChanServ::getInfo('#fortress');
assertTest($chanInfo['success'] === true && str_contains($chanInfo['message'], 'Encryption and Security Fortress'), 'ChanServ returned channel info with topic');

// Test 7: MOTDSERV (Message of the Day Service)
echo "\n7. Testing MOTDSERV Message of the Day Bot...\n";
$motdSet = MotdServ::setMotd('Welcome to Fortress Admin Network', 'AdminUser');
assertTest($motdSet['success'] === true, 'MOTDSERV updated serverwide Message of the Day');
assertTest(MotdServ::getMotd() === 'Welcome to Fortress Admin Network', 'MOTDSERV getMotd returned updated message');

// Test 8: IrcServices Command Parser & Dispatcher
echo "\n8. Testing IrcServices Command Parser...\n";
$cmd1 = IrcServices::processCommand('Bob', '#lobby', '/msg CHANSERV REGISTER #lobby');
assertTest($cmd1 !== null && $cmd1['is_service_command'] === true && $cmd1['service'] === 'CHANSERV', 'Parsed /msg CHANSERV REGISTER command');

$cmd2 = IrcServices::processCommand('Alice', '#fortress', '/topic Fortress Encryption Zone');
assertTest($cmd2 !== null && $cmd2['service'] === 'CHANSERV', 'Parsed /topic command');

$cmd3 = IrcServices::processCommand('Admin', '#lobby', '/msg MOTDSERV SET Hello Admin World');
assertTest($cmd3 !== null && $cmd3['service'] === 'MOTDSERV', 'Parsed /msg MOTDSERV SET command');

$cmd4 = IrcServices::processCommand('Alice', '#fortress', '/help');
assertTest($cmd4 !== null && str_contains($cmd4['response'], 'Available IRC Commands'), 'Parsed /help command');

// Test 9: Room Manager & Ephemeral Signaling with #room names
echo "\n9. Testing Ephemeral Non-Logging Room Manager with #room scheme...\n";
RoomManager::reset();
$room = '#test-channel';
$user1 = 'user-1';
$user2 = 'user-2';

$roomInfo1 = RoomManager::joinRoom($room, $user1);
assertTest($roomInfo1['roomId'] === $room, 'User 1 joins #test-channel');

$roomInfo2 = RoomManager::joinRoom($room, $user2);
assertTest(count($roomInfo2['peers']) === 1 && $roomInfo2['peers'][0] === $user1, 'User 2 sees User 1 in #test-channel');

RoomManager::broadcastSignal($room, $user1, ['type' => 'offer', 'sdp' => 'test-sdp'], true);
$user2Messages = RoomManager::pollMessages($room, $user2);
assertTest(count($user2Messages) === 1 && $user2Messages[0]['type'] === 'offer', 'User 2 receives signal in #test-channel');

RoomManager::leaveRoom($room, $user1);
assertTest(RoomManager::getPeerCount($room) === 1, 'User 1 left #test-channel');

// Test 10: QUOTESERV Bot & /quote command
echo "\n10. Testing QUOTESERV Bot & /quote Command...\n";
$qRes1 = QuoteServ::addQuote('The journey of a thousand miles begins with one step.', 'LaoTzu');
assertTest($qRes1['success'] === true && $qRes1['id'] > 0, 'QUOTESERV created quote #1');

$cmdQuote = IrcServices::processCommand('Alice', '#lobby', '/quote Cryptography is the ultimate form of non-violent direct action.');
assertTest($cmdQuote !== null && $cmdQuote['service'] === 'QUOTESERV' && str_contains($cmdQuote['response'], 'added successfully'), 'Parsed /quote <text> command');

$randomQ = QuoteServ::getRandomQuote();
assertTest($randomQ !== null && !empty($randomQ['quote_text']), 'QuoteServ::getRandomQuote returns a quote');

$editRes = QuoteServ::editQuote($qRes1['id'], 'The journey of a thousand miles begins with a single step.');
assertTest($editRes['success'] === true, 'Admin successfully edited quote #' . $qRes1['id']);

$subRes = QuoteServ::subscribe('CyberFox');
assertTest($subRes['success'] === true && QuoteServ::isSubscribed('CyberFox') === true, 'CyberFox subscribed to periodic quotes');

$unsubRes = QuoteServ::unsubscribe('CyberFox');
assertTest($unsubRes['success'] === true && QuoteServ::isSubscribed('CyberFox') === false, 'CyberFox unsubscribed from periodic quotes');

$delCmd = IrcServices::processCommand('AdminUser', '#lobby', "/msg QUOTESERV DEL {$qRes1['id']}");
assertTest($delCmd !== null && str_contains($delCmd['response'], 'has been removed'), 'Parsed /msg QUOTESERV DEL command');

echo "\n-----------------------------------------\n";
echo "Test Results: $testsPassed Passed, $testsFailed Failed.\n";
echo "-----------------------------------------\n\n";

if ($testsFailed > 0) {
    exit(1);
}
