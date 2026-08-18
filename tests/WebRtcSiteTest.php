<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../src/Security/Sanitizer.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Security/TokenManager.php';
require_once __DIR__ . '/../src/Models/UserNick.php';
require_once __DIR__ . '/../src/Models/Channel.php';
require_once __DIR__ . '/../src/Models/ChannelUser.php';
require_once __DIR__ . '/../src/Models/IrcSetting.php';
require_once __DIR__ . '/../src/Models/SharedFile.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Database/SharedFileRepository.php';
require_once __DIR__ . '/../src/Database/UserNickRepository.php';
require_once __DIR__ . '/../src/Database/ChannelRepository.php';
require_once __DIR__ . '/../src/Database/ChannelUserRepository.php';
require_once __DIR__ . '/../src/Database/SettingRepository.php';
require_once __DIR__ . '/../src/Database/BotServRepository.php';
require_once __DIR__ . '/../src/Database/TextServRepository.php';
require_once __DIR__ . '/../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../src/IRC/NameServ.php';
require_once __DIR__ . '/../src/IRC/ChanServ.php';
require_once __DIR__ . '/../src/IRC/MotdServ.php';
require_once __DIR__ . '/../src/IRC/MemoServ.php';
require_once __DIR__ . '/../src/IRC/HostServ.php';
require_once __DIR__ . '/../src/IRC/ServiceRegistry.php';
require_once __DIR__ . '/../src/IRC/ServServ.php';
require_once __DIR__ . '/../src/IRC/HelpServ.php';
require_once __DIR__ . '/../src/IRC/BotServ.php';
require_once __DIR__ . '/../src/IRC/TextServ.php';
require_once __DIR__ . '/../src/IRC/IrcServices.php';
require_once __DIR__ . '/../src/Signaling/RoomManager.php';

use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\Security\TokenManager;
use Fortress\Models\UserNick;
use Fortress\Models\Channel;
use Fortress\Models\ChannelUser;
use Fortress\Models\IrcSetting;
use Fortress\Models\SharedFile;
use Fortress\Database\Database;
use Fortress\Database\SharedFileRepository;
use Fortress\Database\UserNickRepository;
use Fortress\Database\ChannelRepository;
use Fortress\Database\ChannelUserRepository;
use Fortress\Database\SettingRepository;
use Fortress\Database\BotServRepository;
use Fortress\Database\TextServRepository;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\NameServ;
use Fortress\IRC\ChanServ;
use Fortress\IRC\MotdServ;
use Fortress\IRC\MemoServ;
use Fortress\IRC\HostServ;
use Fortress\IRC\ServiceRegistry;
use Fortress\IRC\ServServ;
use Fortress\IRC\BotServ;
use Fortress\IRC\TextServ;
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

RateLimiter::reset();
for ($i = 0; $i < 501; $i++) {
    RateLimiter::check("client-$i", 5, -1);
}

$reflection = new ReflectionClass(RateLimiter::class);
$property = $reflection->getProperty('buckets');
$property->setAccessible(true);
$buckets = $property->getValue();
assertTest(count($buckets) === 501, 'Created 501 expired rate limit buckets');

RateLimiter::check('client-trigger-gc', 5, 60);

$buckets = $property->getValue();
assertTest(count($buckets) === 1, 'Expired buckets purged by gc() when threshold > 500 is reached');

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

$allSettings = SettingsManager::getAllSettings();
assertTest(is_array($allSettings), 'SettingsManager::getAllSettings returns an array');
assertTest(isset($allSettings['network_name']) && $allSettings['network_name']['value'] === 'IVC-IRC Network', 'getAllSettings contains default network_name setting');
assertTest(isset($allSettings['motd']) && $allSettings['motd']['value'] === 'New Fortress IRC MOTD', 'getAllSettings contains updated motd setting');
assertTest(isset($allSettings['motd']['updated_at']) && is_int($allSettings['motd']['updated_at']), 'getAllSettings returns updated_at as integer');
assertTest(array_key_exists('description', $allSettings['motd']), 'getAllSettings returns description key');

// Test 5: NAMESERV (Nickname Service)
echo "\n5. Testing NAMESERV Nickname Registration & Identification...\n";
$regRes = NameServ::register('CyberFox', 'SecretPass123', 'fox@fortress.local');
assertTest($regRes['success'] === true, 'NAMESERV successfully registered nick CyberFox');
assertTest(NameServ::isRegistered('CyberFox') === true, 'NameServ::isRegistered returns true for CyberFox');

$idRes = NameServ::identify('CyberFox', 'SecretPass123');
assertTest($idRes['success'] === true, 'NAMESERV successfully identified CyberFox with correct password');

$idFail = NameServ::identify('CyberFox', 'WrongPassword');
assertTest($idFail['success'] === false, 'NAMESERV rejected incorrect password');

assertTest(NameServ::isIdentified('CyberFox') === true, 'NameServ::isIdentified returns true for identified user CyberFox');
assertTest(NameServ::isIdentified('NonExistentUser') === false, 'NameServ::isIdentified returns false for non-existent user');

$infoRes = NameServ::getInfo('CyberFox');
assertTest($infoRes['success'] === true && str_contains($infoRes['message'], 'Registered:'), 'NAMESERV returned nick registration info');

NameServ::register('ExpiredUser', 'pass', 'test@example.com');
// Manually set last_seen to 2 hours ago
\Fortress\Database\UserNickRepository::updateIdentification('ExpiredUser', false, time() - 7200);
$purged = NameServ::purgeExpired(3600);
assertTest($purged === 1, 'NameServ::purgeExpired successfully purged 1 expired nickname');
assertTest(NameServ::isRegistered('ExpiredUser') === false, 'ExpiredUser was correctly deleted by purgeExpired');

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

// Remove OP from second user
$deopRes = ChanServ::deop('#fortress', 'Alice', 'CyberFox');
assertTest($deopRes['success'] === true && ChanServ::isOp('#fortress', 'Alice') === false, 'ChanServ removed OP from Alice');

// Check permission denied for deop
$deopFailRes = ChanServ::deop('#fortress', 'CyberFox', 'Alice');
assertTest($deopFailRes['success'] === false && ChanServ::isOp('#fortress', 'CyberFox') === true, 'ChanServ denied deop when requester is not OP');

// Update channel topic
$topicRes = ChanServ::setTopic('#fortress', 'Encryption and Security Fortress', 'CyberFox');
assertTest($topicRes['success'] === true, 'ChanServ updated channel topic');

$chanInfo = ChanServ::getInfo('#fortress');
assertTest($chanInfo['success'] === true && str_contains($chanInfo['message'], 'Encryption and Security Fortress'), 'ChanServ returned channel info with topic');

$channelsList = ChanServ::listChannels();
$foundFortress = false;
foreach ($channelsList as $chanListItem) {
    if ($chanListItem['channel_name'] === '#fortress') {
        $foundFortress = true;
        break;
    }
}
assertTest($foundFortress === true, 'ChanServ::listChannels returns array containing registered channel #fortress');

// Test 7: MOTDSERV (Message of the Day Service)
echo "\n7. Testing MOTDSERV Message of the Day Bot...\n";
$motdSet = MotdServ::setMotd('Welcome to Fortress Admin Network', 'AdminUser');
assertTest($motdSet['success'] === true, 'MOTDSERV updated serverwide Message of the Day');
assertTest(MotdServ::getMotd() === 'Welcome to Fortress Admin Network', 'MOTDSERV getMotd returned updated message');

$motdInfo = MotdServ::getInfo();
assertTest($motdInfo['success'] === true, 'MOTDSERV getInfo returned success');
assertTest(str_contains($motdInfo['message'], 'Welcome to Fortress Admin Network') && str_contains($motdInfo['message'], 'MOTDSERV Message of the Day'), 'MOTDSERV getInfo returned correctly formatted info');

// Test 8: MEMOSERV (Memo Service Bot)
echo "\n8. Testing MEMOSERV Stored Offline Messaging...\n";
$memoSend = MemoServ::send('Alice', 'CyberFox', 'Hello CyberFox, welcome to Fortress IRC!');
assertTest($memoSend['success'] === true, 'MEMOSERV sent memo to CyberFox');
assertTest(MemoServ::getUnreadCount('CyberFox') === 1, 'MemoServ unread count is 1 for CyberFox');

$memoList = MemoServ::listMemos('CyberFox');
assertTest($memoList['success'] === true && count($memoList['memos']) === 1, 'MEMOSERV listed 1 memo for CyberFox');

$memoRead = MemoServ::read('CyberFox', 1);
assertTest($memoRead['success'] === true && str_contains($memoRead['message'], 'welcome to Fortress IRC'), 'MEMOSERV read memo content successfully');
assertTest(MemoServ::getUnreadCount('CyberFox') === 0, 'MemoServ unread count became 0 after reading');

$memoDel = MemoServ::delete('CyberFox', 1);
assertTest($memoDel['success'] === true, 'MEMOSERV deleted memo successfully');

// Test 9: HOSTSERV (Virtual Host Service Bot)
echo "\n9. Testing HOSTSERV Virtual Host Management...\n";
$vhReq = HostServ::requestVhost('CyberFox', 'vip.fortress.net');
assertTest($vhReq['success'] === true, 'HOSTSERV requested/assigned vhost vip.fortress.net');
assertTest(HostServ::getActiveVhost('CyberFox') === 'vip.fortress.net', 'HostServ::getActiveVhost returned assigned vhost');

$vhOff = HostServ::setVhostStatus('CyberFox', false);
assertTest($vhOff['success'] === true && HostServ::getActiveVhost('CyberFox') === null, 'HOSTSERV deactivated vhost');

$vhInfo = HostServ::getVhostInfo('CyberFox');
assertTest($vhInfo['success'] === true && str_contains($vhInfo['message'], 'vip.fortress.net'), 'HOSTSERV returned vhost registration info');

// Test 10: ServiceRegistry & Foreign Services Operating Under Different Hosts
echo "\n10. Testing ServiceRegistry & Foreign Services API...\n";
$regSvc = ServiceRegistry::registerService('HELPBOT', 'help.external-domain.org', 'https://help.external-domain.org/api/irc', 'External AI Help Bot');
assertTest($regSvc['success'] === true, 'ServiceRegistry registered foreign service HELPBOT under host help.external-domain.org');

$getSvc = ServiceRegistry::getService('HELPBOT');
assertTest($getSvc !== null && $getSvc['host'] === 'help.external-domain.org', 'ServiceRegistry retrieved registered foreign service by name');

$pingRes = ServiceRegistry::pingService('HELPBOT', 'ACTIVE');
assertTest($pingRes['success'] === true, 'ServiceRegistry ping update successful');

$svcList = ServiceRegistry::listServices();
assertTest(count($svcList) >= 1, 'ServiceRegistry listed registered foreign services');

// Test 11: ServServ Bot & Foreign Service Command Dispatching
echo "\n11. Testing ServServ Bot & Foreign Service Routing...\n";
$ssList = ServServ::listAllServices();
assertTest($ssList['success'] === true && count($ssList['foreign_services']) >= 1, 'ServServ listed local and foreign services');

$ssInfo = ServServ::getServiceInfo('HELPBOT');
assertTest($ssInfo['success'] === true && str_contains($ssInfo['message'], 'help.external-domain.org'), 'ServServ returned foreign service info');

$ssDispatch = ServServ::dispatchForeignCommand('Alice', 'HELPBOT', 'SEARCH WebRTC security');
assertTest($ssDispatch['success'] === true && str_contains($ssDispatch['message'], 'HELPBOT@help.external-domain.org'), 'ServServ dispatched command to foreign service');

// Test 12: IrcServices Command Parser with All Services
echo "\n12. Testing IrcServices Command Parser with new Services...\n";
$cmdMemo = IrcServices::processCommand('Bob', '#lobby', '/msg MEMOSERV SEND CyberFox Meet in #fortress');
assertTest($cmdMemo !== null && $cmdMemo['service'] === 'MEMOSERV', 'Parsed /msg MEMOSERV SEND command');

$cmdVhost = IrcServices::processCommand('Bob', '#lobby', '/vhost REQUEST dev.fortress.local');
assertTest($cmdVhost !== null && $cmdVhost['service'] === 'HOSTSERV', 'Parsed /vhost REQUEST shortcut command');

$cmdSvcList = IrcServices::processCommand('Bob', '#lobby', '/msg SERVSERV LIST');
assertTest($cmdSvcList !== null && $cmdSvcList['service'] === 'SERVSERV', 'Parsed /msg SERVSERV LIST command');

$cmdForeign = IrcServices::processCommand('Bob', '#lobby', '/msg HELPBOT ASK How do I lock a channel?');
assertTest($cmdForeign !== null && $cmdForeign['service'] === 'HELPBOT' && str_contains($cmdForeign['response'], 'HELPBOT@help.external-domain.org'), 'Parsed /msg <FOREIGN_SERVICE> command and routed to foreign host');

// Test 13: Room Manager & Ephemeral Signaling with #room names
echo "\n13. Testing Ephemeral Non-Logging Room Manager with #room scheme...\n";
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

// Test 10: Model PHP Entity Classes
echo "\n10. Testing Domain Model PHP Entity Classes...\n";
$uNick = new UserNick('TestUser', UserNick::hashPassword('mypassword'), 'test@example.com', 1700000000, 1700000000, true);
assertTest($uNick->getNickname() === 'TestUser', 'UserNick getter returns nickname');
assertTest($uNick->verifyPassword('mypassword') === true, 'UserNick verifyPassword succeeds with correct password');
assertTest($uNick->verifyPassword('wrongpass') === false, 'UserNick verifyPassword fails with incorrect password');

$uArray = $uNick->toArray();
$uRestored = UserNick::fromArray($uArray);
assertTest($uRestored->getNickname() === 'TestUser' && $uRestored->getEmail() === 'test@example.com', 'UserNick array serialization and restoration');

$chan = new Channel('#dev', 'Developer', 'Development Channel', 'devkey123', '+tn');
assertTest($chan->getChannelName() === '#dev' && $chan->getPasskey() === 'devkey123', 'Channel model getters work');
$cArray = $chan->toArray();
$cRestored = Channel::fromArray($cArray);
assertTest($cRestored->getTopic() === 'Development Channel', 'Channel array serialization and restoration');

$cUser = new ChannelUser('#dev', 'Developer', 'OP');
assertTest($cUser->isOp() === true, 'ChannelUser isOp returns true for OP');

$setting = new IrcSetting('custom_key', 'custom_val', 'Custom Description');
assertTest($setting->getSettingKey() === 'custom_key' && $setting->getSettingValue() === 'custom_val', 'IrcSetting model getters work');

// Test 11: Database Prepared Statements & Transactions
echo "\n11. Testing Database Prepared Statements & Transactions...\n";
$fetchOneRes = Database::fetchOne("SELECT setting_value FROM irc_settings WHERE setting_key = :key", [':key' => 'network_name']);
assertTest($fetchOneRes !== null && $fetchOneRes['setting_value'] === 'IVC-IRC Network', 'Database::fetchOne returns correct record');

$fetchColRes = Database::fetchColumn("SELECT COUNT(*) FROM irc_settings");
assertTest((int)$fetchColRes > 0, 'Database::fetchColumn returns aggregate count');

// Test transaction rollback
try {
    Database::transaction(function() {
        Database::execute("INSERT INTO irc_settings (setting_key, setting_value, description, updated_at) VALUES ('tx_test', 'tx_val', 'desc', 123456)");
        throw new \Exception("Trigger rollback");
    });
} catch (\Throwable $e) {
    // Expected exception
}
$txCheck = Database::fetchOne("SELECT setting_value FROM irc_settings WHERE setting_key = 'tx_test'");
assertTest($txCheck === null, 'Database::transaction correctly rolled back changes on exception');

// Test 12: Repositories
echo "\n12. Testing Data Access Repositories...\n";
$repoUser = UserNickRepository::findByNickname('CyberFox');
assertTest($repoUser !== null && $repoUser->getNickname() === 'CyberFox', 'UserNickRepository::findByNickname retrieved record');

$repoChan = ChannelRepository::findByChannelName('#fortress');
assertTest($repoChan !== null && $repoChan->getOwnerNick() === 'CyberFox', 'ChannelRepository::findByChannelName retrieved record');

$allChans = ChannelRepository::findAll();
assertTest(count($allChans) > 0 && $allChans[0] instanceof Channel, 'ChannelRepository::findAll returned Channel objects');

$settingRepo = SettingRepository::findByKey('network_name');
assertTest($settingRepo !== null && $settingRepo->getSettingValue() === 'IVC-IRC Network', 'SettingRepository::findByKey retrieved IrcSetting object');

// Test 13: Grouped Subchats (#room/sub-room) and /supersilent Command
echo "\n13. Testing Grouped Subchats (#room/sub-room) & /supersilent Command...\n";
assertTest(Sanitizer::sanitizeRoomId('#tech/dev') === '#tech/dev', 'Sanitize subroom #tech/dev syntax');
assertTest(Sanitizer::sanitizeRoomId('tech/dev/backend') === '#tech/dev/backend', 'Normalize subroom syntax and prepend #');
assertTest(Sanitizer::sanitizeRoomId('#tech//dev/') === '#tech/dev', 'Collapse slashes and trim trailing slash in subroom name');

// Reset RoomManager and set up super room and subroom clients
RoomManager::reset();
$superRoom = '#tech';
$subRoom1 = '#tech/dev';
$subRoom2 = '#tech/dev/backend';
$otherRoom = '#general';

$uSuper = 'peer-super';
$uSub1 = 'peer-sub1';
$uSub2 = 'peer-sub2';
$uOther = 'peer-other';

RoomManager::joinRoom($superRoom, $uSuper);
RoomManager::joinRoom($subRoom1, $uSub1);
RoomManager::joinRoom($subRoom2, $uSub2);
RoomManager::joinRoom($otherRoom, $uOther);

// Broadcast standard message in super room
RoomManager::broadcastSignal($superRoom, $uSuper, ['type' => 'chat', 'message' => 'Announcement to all tech subrooms'], true);

$msgSub1 = RoomManager::pollMessages($subRoom1, $uSub1);
$msgSub2 = RoomManager::pollMessages($subRoom2, $uSub2);
$msgOther = RoomManager::pollMessages($otherRoom, $uOther);

assertTest(count($msgSub1) === 1 && $msgSub1[0]['message'] === 'Announcement to all tech subrooms', 'Subroom #tech/dev received super room message');
assertTest(count($msgSub2) === 1 && $msgSub2[0]['message'] === 'Announcement to all tech subrooms', 'Nested subroom #tech/dev/backend received super room message');
assertTest(count($msgOther) === 0, 'Unrelated room #general did not receive super room message');

// Test /supersilent command usage and override
$ssUsage = IrcServices::processCommand($uSuper, $superRoom, '/supersilent');
assertTest($ssUsage !== null && str_contains($ssUsage['response'], 'Usage: /supersilent'), 'Returned usage info for /supersilent command');

$ssCmd = IrcServices::processCommand($uSuper, $superRoom, '/supersilent Local super room announcement only');
assertTest($ssCmd !== null && $ssCmd['service'] === 'SUPERSILENT', 'Processed /supersilent message');

$ssSub1Msgs = RoomManager::pollMessages($subRoom1, $uSub1);
assertTest(count($ssSub1Msgs) === 0, 'Subroom #tech/dev did NOT receive /supersilent message (override behavior)');

// Test 14: Multi-Theme Support & /theme Service Command
echo "\n14. Testing Multi-Theme Support & /theme Service Command...\n";
$themeListCmd = IrcServices::processCommand('User1', '#lobby', '/theme list');
assertTest($themeListCmd !== null && $themeListCmd['service'] === 'THEMESERV' && str_contains($themeListCmd['response'], 'halloween'), 'Processed /theme list command');

$themeHalCmd = IrcServices::processCommand('User1', '#lobby', '/theme halloween');
assertTest($themeHalCmd !== null && $themeHalCmd['service'] === 'THEMESERV' && str_contains($themeHalCmd['response'], 'halloween'), 'Processed /theme halloween command');

$themeConsCmd = IrcServices::processCommand('User1', '#lobby', '/theme console');
assertTest($themeConsCmd !== null && $themeConsCmd['service'] === 'THEMESERV' && str_contains($themeConsCmd['response'], 'console'), 'Processed /theme console command');

$themeXmasCmd = IrcServices::processCommand('User1', '#lobby', '/theme christmas');
assertTest($themeXmasCmd !== null && $themeXmasCmd['service'] === 'THEMESERV' && str_contains($themeXmasCmd['response'], 'christmas'), 'Processed /theme christmas command');

// Test 15: SharedFile Domain Model, E2EE Metadata & SharedFileRepository
echo "\n15. Testing SharedFile Domain Model & SharedFileRepository...\n";
$sharedFile = new SharedFile('file-test-999', '#lobby', 'peer-alice', 'E2EE_ENCRYPTED_BLOB_STRING', 'https://cloud.example.com/share/999');
assertTest($sharedFile->getId() === 'file-test-999', 'SharedFile getter returns ID');
assertTest($sharedFile->getChannelName() === '#lobby', 'SharedFile getter returns channel name');
assertTest($sharedFile->getSharerClientId() === 'peer-alice', 'SharedFile getter returns sharer client ID');
assertTest($sharedFile->getEncryptedMetadata() === 'E2EE_ENCRYPTED_BLOB_STRING', 'SharedFile getter returns encrypted metadata');
assertTest($sharedFile->getCloudLink() === 'https://cloud.example.com/share/999', 'SharedFile getter returns cloud link');

$savedFile = SharedFileRepository::save($sharedFile);
assertTest($savedFile === true, 'SharedFileRepository successfully saved file record');

$foundFile = SharedFileRepository::findById('file-test-999');
assertTest($foundFile !== null && $foundFile->getSharerClientId() === 'peer-alice' && $foundFile->getCloudLink() === 'https://cloud.example.com/share/999', 'SharedFileRepository::findById retrieved record');

$channelFiles = SharedFileRepository::findByChannel('#lobby');
assertTest(count($channelFiles) >= 1 && $channelFiles[0]->getId() === 'file-test-999', 'SharedFileRepository::findByChannel retrieved channel files');

$deletedFile = SharedFileRepository::deleteById('file-test-999');
assertTest($deletedFile === true, 'SharedFileRepository::deleteById deleted file record');

echo "\n-----------------------------------------\n";
echo "Test Results: $testsPassed Passed, $testsFailed Failed.\n";
echo "-----------------------------------------\n\n";

if ($testsFailed > 0) {
    exit(1);
}
