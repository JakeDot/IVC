#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/Sanitizer.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Signaling/RoomManager.php';
require_once __DIR__ . '/../src/IRC/IrcServices.php';

// Also require all classes needed by IrcServices
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Database/UserNickRepository.php';
require_once __DIR__ . '/../src/Database/ChannelRepository.php';
require_once __DIR__ . '/../src/Database/ChannelUserRepository.php';
require_once __DIR__ . '/../src/Database/SettingRepository.php';
require_once __DIR__ . '/../src/Models/UserNick.php';
require_once __DIR__ . '/../src/Models/Channel.php';
require_once __DIR__ . '/../src/Models/ChannelUser.php';
require_once __DIR__ . '/../src/Models/IrcSetting.php';
require_once __DIR__ . '/../src/IRC/SettingsManager.php';
require_once __DIR__ . '/../src/IRC/NameServ.php';
require_once __DIR__ . '/../src/IRC/ChanServ.php';
require_once __DIR__ . '/../src/IRC/MotdServ.php';
require_once __DIR__ . '/../src/IRC/MemoServ.php';
require_once __DIR__ . '/../src/IRC/HostServ.php';
require_once __DIR__ . '/../src/IRC/ServiceRegistry.php';
require_once __DIR__ . '/../src/IRC/ServServ.php';

use Fortress\Security\Sanitizer;
use Fortress\Signaling\RoomManager;
use Fortress\IRC\IrcServices;
use Fortress\IRC\MotdServ;

echo "=========================================================\n";
echo " 🏰 Fortress IRC Server (PHP)\n";
echo "=========================================================\n";
echo "Starting IRC daemon...\n";

$port = isset($argv[1]) ? (int)$argv[1] : 6667;
$address = "0.0.0.0:$port";

$server = stream_socket_server("tcp://$address", $errno, $errstr);

if (!$server) {
    echo "Error starting IRC server on $address: $errstr ($errno)\n";
    exit(1);
}

stream_set_blocking($server, false);

echo "✅ IRC Server active on tcp://$address\n\n";

$clients = [];
$channels = []; // Keep track of who is in what channel
$clientData = []; // Buffer for incoming data

// WebSocket framing helper
function sendToClient($client, $clientId, $data, &$clientData) {
    if (!is_resource($client)) return;
    if (isset($clientData[$clientId]['is_ws']) && $clientData[$clientId]['is_ws']) {
        $b1 = 0x80 | (0x1 & 0x0f); // text frame, fin
        $length = strlen($data);
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCNN', $b1, 127, 0, $length);
        }
        fwrite($client, $header . $data);
    } else {
        fwrite($client, $data);
    }
}

while (true) {
    $read = $clients;
    $read[] = $server;
    $write = null;
    $except = null;

    // Use stream_select to check for I/O
    if (stream_select($read, $write, $except, 0, 200000) === false) {
        break;
    }

    // New connection
    if (in_array($server, $read)) {
        $client = stream_socket_accept($server);
        if ($client) {
            stream_set_blocking($client, false);
            $clients[] = $client;
            $clientId = (int)$client;

            $clientData[$clientId] = [
                'nick' => null,
                'user' => null,
                'buffer' => '',
                'channels' => [],
                'is_ws' => false,
                'ws_handshake_done' => false
            ];

            echo "New client connected: $clientId\n";
        }
        $key = array_search($server, $read);
        unset($read[$key]);
    }

    // Read data from clients
    foreach ($read as $client) {
        $clientId = (int)$client;
        $data = fread($client, 8192);

        if ($data === false || $data === '') {
            // Client disconnected
            echo "Client disconnected: $clientId\n";
            $nick = $clientData[$clientId]['nick'];
            if ($nick) {
                foreach ($clientData[$clientId]['channels'] as $chan) {
                    RoomManager::leaveRoom($chan, $nick);
                    broadcastToChannel($channels, $chan, ":$nick QUIT :Client exited\r\n", $clientData);
                    // Remove from our channels tracking
                    if (isset($channels[$chan][$clientId])) {
                        unset($channels[$chan][$clientId]);
                    }
                }
            }
            if (($key = array_search($client, $clients)) !== false) {
                unset($clients[$key]);
            }
            unset($clientData[$clientId]);
            fclose($client);
            continue;
        }

        $clientData[$clientId]['buffer'] .= $data;
        if (strlen($clientData[$clientId]['buffer']) > 1024 * 1024) {
            if (($key = array_search($client, $clients)) !== false) {
                unset($clients[$key]);
            }
            unset($clientData[$clientId]);
            fclose($client);
            continue;
        }

        // WebSocket Handshake
        if (!$clientData[$clientId]['ws_handshake_done'] && str_starts_with($clientData[$clientId]['buffer'], 'GET ')) {
            if (($headerEnd = strpos($clientData[$clientId]['buffer'], "\r\n\r\n")) !== false) {
                $headers = substr($clientData[$clientId]['buffer'], 0, $headerEnd);
                $clientData[$clientId]['buffer'] = substr($clientData[$clientId]['buffer'], $headerEnd + 4);

                if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/i", $headers, $match)) {
                    $key = trim($match[1]);
                    $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                               "Upgrade: websocket\r\n" .
                               "Connection: Upgrade\r\n" .
                               "Sec-WebSocket-Accept: $acceptKey\r\n" .
                               "Sec-WebSocket-Protocol: irc\r\n\r\n";
                    fwrite($client, $upgrade);
                    $clientData[$clientId]['is_ws'] = true;
                }
                $clientData[$clientId]['ws_handshake_done'] = true;
            } else {
                continue; // Wait for full headers
            }
        }

        if ($clientData[$clientId]['is_ws'] && $clientData[$clientId]['ws_handshake_done']) {
            // Unframe WebSocket data
            while (strlen($clientData[$clientId]['buffer']) >= 2) {
                $firstByte = ord($clientData[$clientId]['buffer'][0]);
                $secondByte = ord($clientData[$clientId]['buffer'][1]);
                $opcode = $firstByte & 0x0F;
                $isMasked = ($secondByte & 0x80) === 0x80;
                $payloadLength = $secondByte & 0x7F;

                $headerLength = 2;
                if ($payloadLength === 126) {
                    if (strlen($clientData[$clientId]['buffer']) < 4) break;
                    $payloadLength = unpack('n', substr($clientData[$clientId]['buffer'], 2, 2))[1];
                    $headerLength += 2;
                } elseif ($payloadLength === 127) {
                    if (strlen($clientData[$clientId]['buffer']) < 10) break;
                    $payloadLength = unpack('J', substr($clientData[$clientId]['buffer'], 2, 8))[1];
                    $headerLength += 8;
                }

                if ($isMasked) {
                    if (strlen($clientData[$clientId]['buffer']) < $headerLength + 4) break;
                    $mask = substr($clientData[$clientId]['buffer'], $headerLength, 4);
                    $headerLength += 4;
                }

                if (strlen($clientData[$clientId]['buffer']) < $headerLength + $payloadLength) break;

                $payload = substr($clientData[$clientId]['buffer'], $headerLength, $payloadLength);
                if ($isMasked) {
                    $unmaskedPayload = '';
                    for ($i = 0; $i < $payloadLength; $i++) {
                        $unmaskedPayload .= $payload[$i] ^ $mask[$i % 4];
                    }
                    $payload = $unmaskedPayload;
                }

                $clientData[$clientId]['buffer'] = substr($clientData[$clientId]['buffer'], $headerLength + $payloadLength);

                if ($opcode === 0x8) { // Close frame
                    fclose($client);
                    if (($key = array_search($client, $clients)) !== false) unset($clients[$key]);
                    unset($clientData[$clientId]);
                    continue 2;
                } elseif ($opcode === 0x1) { // Text frame
                    // Process lines in payload
                    $lines = explode("\n", $payload);
                    foreach ($lines as $line) {
                        $line = trim($line, "\r");
                        if (empty($line)) continue;
                        echo "[$clientId WS IN] $line\n";
                        processCommand($client, $clientId, $line, $clientData, $channels, $clients);
                    }
                }
            }
        } else {
            // Process raw TCP lines
            while (($pos = strpos($clientData[$clientId]['buffer'], "\n")) !== false) {
                $line = substr($clientData[$clientId]['buffer'], 0, $pos);
                $clientData[$clientId]['buffer'] = substr($clientData[$clientId]['buffer'], $pos + 1);
                $line = trim($line, "\r");

                if (empty($line)) continue;

                echo "[$clientId TCP IN] $line\n";
                processCommand($client, $clientId, $line, $clientData, $channels, $clients);
            }
        }
    }

    // Poll messages from RoomManager for each user in each channel
    foreach ($clients as $client) {
        $clientId = (int)$client;
        $nick = $clientData[$clientId]['nick'];
        if (!$nick) continue;

        foreach ($clientData[$clientId]['channels'] as $chan) {
            $messages = RoomManager::pollMessages($chan, $nick);
            foreach ($messages as $msg) {
                if ($msg['type'] === 'chat') {
                    $sender = $msg['sender'];
                    if ($sender !== $nick) { // don't echo back
                        sendToClient($client, $clientId, ":$sender PRIVMSG $chan :{$msg['message']}\r\n", $clientData);
                        fwrite($client, ":$sender PRIVMSG $chan :{$msg['message']}\r\n");
                    }
                } elseif ($msg['type'] === 'peer-joined') {
                    $sender = $msg['sender'];
                    if ($sender !== $nick) {
                        sendToClient($client, $clientId, ":$sender JOIN $chan\r\n", $clientData);
                    }
                } elseif ($msg['type'] === 'peer-left') {
                    $sender = $msg['sender'];
                    if ($sender !== $nick) {
                        sendToClient($client, $clientId, ":$sender PART $chan\r\n", $clientData);
                    }
                }
            }
        }
    }
}

fclose($server);

function processCommand($client, $clientId, $line, &$clientData, &$channels, &$clients) {
    $parts = explode(' ', $line, 2);
    $cmd = strtoupper($parts[0]);
    $args = isset($parts[1]) ? $parts[1] : '';

    switch ($cmd) {
        case 'NICK':
            $nick = Sanitizer::sanitizeClientId($args);
            if (empty($nick)) {
                sendToClient($client, $clientId, ":server 432 * $args :Erroneous nickname\r\n", $clientData);
                return;
            }
            $oldNick = $clientData[$clientId]['nick'];
            $clientData[$clientId]['nick'] = $nick;

            if ($oldNick === null && $clientData[$clientId]['user'] !== null) {
                welcomeClient($client, $clientId, $nick, $clientData);
            } elseif ($oldNick !== null) {
                sendToClient($client, $clientId, ":$oldNick NICK $nick\r\n", $clientData);
                // Update in all channels
                foreach ($clientData[$clientId]['channels'] as $chan) {
                    RoomManager::leaveRoom($chan, $oldNick);
                    RoomManager::joinRoom($chan, $nick);
                }
            }
            break;

        case 'USER':
            $clientData[$clientId]['user'] = $args;
            if ($clientData[$clientId]['nick'] !== null) {
                welcomeClient($client, $clientId, $clientData[$clientId]['nick'], $clientData);
                welcomeClient($client, $clientData[$clientId]['nick']);
            }
            break;

        case 'PING':
            sendToClient($client, $clientId, ":server PONG server :$args\r\n", $clientData);
            fwrite($client, ":server PONG server :$args\r\n");
            break;

        case 'JOIN':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $chansToJoin = explode(',', $args);
            foreach ($chansToJoin as $c) {
                $c = Sanitizer::sanitizeRoomId(trim($c));
                if (empty($c)) continue;
                if ($c[0] !== '#') $c = '#' . $c;

                RoomManager::joinRoom($c, $nick);
                $clientData[$clientId]['channels'][] = $c;

                if (!isset($channels[$c])) {
                    $channels[$c] = [];
                }
                $channels[$c][$clientId] = $client;

                sendToClient($client, $clientId, ":$nick JOIN $c\r\n", $clientData);

                // Get list of users (we could poll RoomManager, but we also have local state)
                // In a full implementation, we'd sync this better.
                sendToClient($client, $clientId, ":server 332 $nick $c :Welcome to $c\r\n", $clientData);
                fwrite($client, ":$nick JOIN $c\r\n");

                // Get list of users (we could poll RoomManager, but we also have local state)
                // In a full implementation, we'd sync this better.
                fwrite($client, ":server 332 $nick $c :Welcome to $c\r\n");

                $users = RoomManager::joinRoom($c, $nick)['peers'];
                $users[] = $nick; // Add self
                $userList = implode(' ', $users);

                sendToClient($client, $clientId, ":server 353 $nick = $c :$userList\r\n", $clientData);
                sendToClient($client, $clientId, ":server 366 $nick $c :End of /NAMES list\r\n", $clientData);
                fwrite($client, ":server 353 $nick = $c :$userList\r\n");
                fwrite($client, ":server 366 $nick $c :End of /NAMES list\r\n");
            }
            break;

        case 'PART':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $partArgs = explode(' ', $args, 2);
            $chansToPart = explode(',', $partArgs[0]);
            $reason = isset($partArgs[1]) ? trim($partArgs[1], ':') : '';

            foreach ($chansToPart as $c) {
                $c = trim($c);
                RoomManager::leaveRoom($c, $nick);

                if (($key = array_search($c, $clientData[$clientId]['channels'])) !== false) {
                    unset($clientData[$clientId]['channels'][$key]);
                }
                if (isset($channels[$c][$clientId])) {
                    unset($channels[$c][$clientId]);
                }

                sendToClient($client, $clientId, ":$nick PART $c" . ($reason ? " :$reason" : "") . "\r\n", $clientData);
                broadcastToChannel($channels, $c, ":$nick PART $c" . ($reason ? " :$reason" : "") . "\r\n", $clientData);
                fwrite($client, ":$nick PART $c" . ($reason ? " :$reason" : "") . "\r\n");
                broadcastToChannel($channels, $c, ":$nick PART $c" . ($reason ? " :$reason" : "") . "\r\n");
            }
            break;

        case 'PRIVMSG':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $msgParts = explode(' ', $args, 2);
            if (count($msgParts) < 2) return;

            $target = trim($msgParts[0]);
            $text = trim($msgParts[1], ':');

            if (str_starts_with($target, '#')) {
                // Check if channel is moderated (+m)
                $chanModel = \Fortress\Database\ChannelRepository::findByChannelName($target);
                if ($chanModel !== null && str_contains($chanModel->getModes(), 'm')) {
                    if (!\Fortress\Database\ChannelUserRepository::hasVoice($target, $nick)) {
                        fwrite($client, ":server 404 $nick $target :Cannot send to channel (+m)\r\n");
                        return;
                    }
                }

                // Channel message
                RoomManager::broadcastSignal($target, $nick, [
                    'type' => 'chat',
                    'message' => $text
                ], true); // Exclude self

                // Check if it's a command for services like /supersilent
                if (str_starts_with($text, '/')) {
                    $result = IrcServices::processCommand($nick, $target, $text);
                    if ($result !== null) {
                        sendToClient($client, $clientId, ":{$result['service']} PRIVMSG $target :{$result['response']}\r\n", $clientData);
                        fwrite($client, ":{$result['service']} PRIVMSG $target :{$result['response']}\r\n");

                        if (empty($result['skip_bot_broadcast'])) {
                            RoomManager::broadcastSignal($target, 'SYSTEM_BOT', [
                                'type' => 'chat',
                                'sender' => $result['service'],
                                'message' => $result['response'],
                                'is_bot' => true
                            ], false);
                        }
                    }
                }
            } else {
                // Direct message to user or service
                $serviceMap = [
                    'NAMESERV', 'CHANSERV', 'MOTDSERV', 'MEMOSERV', 'HOSTSERV', 'SERVSERV'
                ];

                if (in_array(strtoupper($target), $serviceMap)) {
                    $result = cloneCommandForService($nick, $target, $text);
                    if ($result !== null) {
                        $lines = explode("\n", $result['response']);
                        foreach ($lines as $line) {
                            if (!empty(trim($line))) {
                                sendToClient($client, $clientId, ":{$result['service']} PRIVMSG $nick :" . trim($line) . "\r\n", $clientData);
                                fwrite($client, ":{$result['service']} PRIVMSG $nick :" . trim($line) . "\r\n");
                            }
                        }
                    }
                } else {
                    // Direct message to another user
                    // We'll broadcast it via RoomManager with a specific target
                    // Actually RoomManager supports targeted messages if they are in the same room,
                    // but direct messaging across the server without a common room is tricky without
                    // global tracking. Let's just find the user locally for now.
                    $found = false;
                    foreach ($clientData as $cid => $cdata) {
                        if (strtolower($cdata['nick']) === strtolower($target)) {
                            // Find the correct client index in the $clients array by iterating
                            foreach ($clients as $key => $c) {
                                if ((int)$c === $cid) {
                                    sendToClient($clients[$key], $cid, ":$nick PRIVMSG $target :$text\r\n", $clientData);
                                    fwrite($clients[$key], ":$nick PRIVMSG $target :$text\r\n");
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    if (!$found) {
                        sendToClient($client, $clientId, ":server 401 $nick $target :No such nick/channel\r\n", $clientData);
                        fwrite($client, ":server 401 $nick $target :No such nick/channel\r\n");
                    }
                }
            }
            break;

        case 'CAP':
            $capParts = explode(' ', $args, 2);
            $subCmd = strtoupper($capParts[0]);

            if ($subCmd === 'LS') {
                sendToClient($client, $clientId, ":server CAP * LS :\r\n", $clientData);
            } elseif ($subCmd === 'REQ') {
                $requested = isset($capParts[1]) ? trim($capParts[1], ':') : '';
                sendToClient($client, $clientId, ":server CAP * NAK :$requested\r\n", $clientData);
                fwrite($client, ":server CAP * LS :\r\n");
            } elseif ($subCmd === 'REQ') {
                $requested = isset($capParts[1]) ? trim($capParts[1], ':') : '';
                fwrite($client, ":server CAP * NAK :$requested\r\n");
            } elseif ($subCmd === 'END') {
                // Capabilities negotiation ended.
            }
            break;

        case 'TOPIC':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $topicParts = explode(' ', $args, 2);
            $targetChan = trim($topicParts[0]);

            if (empty($targetChan)) {
                sendToClient($client, $clientId, ":server 461 $nick TOPIC :Not enough parameters\r\n", $clientData);
                fwrite($client, ":server 461 $nick TOPIC :Not enough parameters\r\n");
                return;
            }

            if (count($topicParts) === 1) {
                // Get topic
                $info = \Fortress\IRC\ChanServ::getInfo($targetChan);
                if (isset($info['data']) && isset($info['data']['topic']) && $info['data']['topic']) {
                    sendToClient($client, $clientId, ":server 332 $nick $targetChan :{$info['data']['topic']}\r\n", $clientData);
                } else {
                    sendToClient($client, $clientId, ":server 331 $nick $targetChan :No topic is set\r\n", $clientData);
                }
            } else {
                // Set topic
                $newTopic = trim($topicParts[1], ':');
                $result = \Fortress\IRC\ChanServ::setTopic($targetChan, $newTopic, $nick);
                // We'll broadcast the change if ChanServ accepted it (or even if it's just ephemeral for now)
                // For simplicity, we just broadcast it to everyone in the channel right away.
                broadcastToChannel($channels, $targetChan, ":$nick TOPIC $targetChan :$newTopic\r\n", $clientData);
            }
            break;

        case 'WHO':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $target = trim($args);
            if (empty($target)) {
                sendToClient($client, $clientId, ":server 315 $nick $target :End of /WHO list\r\n", $clientData);
                fwrite($client, ":server 315 $nick $target :End of /WHO list\r\n");
                return;
            }

            if (str_starts_with($target, '#') && isset($channels[$target])) {
                foreach ($channels[$target] as $cid => $c) {
                    $cNick = $clientData[$cid]['nick'];
                    $cUser = $clientData[$cid]['user'] ?: 'user';
                    sendToClient($client, $clientId, ":server 352 $nick $target $cUser 127.0.0.1 server $cNick H :0 Real Name\r\n", $clientData);
                }
            }
            sendToClient($client, $clientId, ":server 315 $nick $target :End of /WHO list\r\n", $clientData);
            break;

        case 'WHOIS':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $target = trim($args);
            if (empty($target)) {
                sendToClient($client, $clientId, ":server 431 $nick :No nickname given\r\n", $clientData);
                return;
            }

            $info = \Fortress\IRC\NameServ::getInfo($target);
            if ($info['success']) {
                $user = 'user';
                foreach ($clientData as $cdata) {
                    if (strtolower($cdata['nick']) === strtolower($target)) {
                        $user = $cdata['user'] ?: 'user';
                        break;
                    }
                }
                sendToClient($client, $clientId, ":server 311 $nick $target $user 127.0.0.1 * :Real Name\r\n", $clientData);
                sendToClient($client, $clientId, ":server 312 $nick $target server :Fortress IRC Server\r\n", $clientData);
                sendToClient($client, $clientId, ":server 318 $nick $target :End of /WHOIS list\r\n", $clientData);
            } else {
                sendToClient($client, $clientId, ":server 401 $nick $target :No such nick/channel\r\n", $clientData);
            }
            break;

        case 'LIST':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            sendToClient($client, $clientId, ":server 321 $nick Channel :Users  Name\r\n", $clientData);
            fwrite($client, ":server 321 $nick Channel :Users  Name\r\n");
            $chans = \Fortress\IRC\ChanServ::listChannels();
            foreach ($chans as $c) {
                // Since this is ephemeral, we can also check $channels
                $count = isset($channels[$c['channel_name']]) ? count($channels[$c['channel_name']]) : 0;
                $topic = $c['topic'] ?: '';
                sendToClient($client, $clientId, ":server 322 $nick {$c['channel_name']} $count :$topic\r\n", $clientData);
            }
            sendToClient($client, $clientId, ":server 323 $nick :End of /LIST\r\n", $clientData);
            break;

        case 'NAMES':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $targetChan = trim($args);
            if (empty($targetChan)) {
                sendToClient($client, $clientId, ":server 366 $nick * :End of /NAMES list\r\n", $clientData);
                return;
            }

            $chans = explode(',', $targetChan);
            foreach ($chans as $c) {
                $c = trim($c);
                if (isset($channels[$c])) {
                    $users = [];
                    foreach ($channels[$c] as $cid => $cl) {
                        $users[] = $clientData[$cid]['nick'];
                    }
                    $userList = implode(' ', $users);
                    sendToClient($client, $clientId, ":server 353 $nick = $c :$userList\r\n", $clientData);
                }
                sendToClient($client, $clientId, ":server 366 $nick $c :End of /NAMES list\r\n", $clientData);
            }
            break;

        case 'MODE':
            $nick = $clientData[$clientId]['nick'];
            if (!$nick) return;

            $modeParts = explode(' ', $args, 2);
            $target = trim($modeParts[0]);

            if (empty($target)) {
                sendToClient($client, $clientId, ":server 461 $nick MODE :Not enough parameters\r\n", $clientData);
                return;
            }

            if (str_starts_with($target, '#')) {
                $modes = isset($modeParts[1]) ? trim($modeParts[1]) : '';
                if (empty($modes)) {
                    // Fetch current modes
                    $info = \Fortress\IRC\ChanServ::getInfo($target);
                    if ($info['success'] && isset($info['data']['modes'])) {
                        sendToClient($client, $clientId, ":server 324 $nick $target {$info['data']['modes']}\r\n", $clientData);
                    } else {
                        sendToClient($client, $clientId, ":server 324 $nick $target +nt\r\n", $clientData); // fallback
                    }
                } else {
                    // Try to set modes using ChanServ logic
                    $res = \Fortress\IRC\ChanServ::setModes($target, $modes, $nick);
                    if ($res['success']) {
                        broadcastToChannel($channels, $target, ":$nick MODE $target $modes\r\n", $clientData);
                    } else {
                        sendToClient($client, $clientId, ":server 482 $nick $target :You're not channel operator\r\n", $clientData);
                    }
                }
            } else {
                if (strtolower($target) === strtolower($nick)) {
                    sendToClient($client, $clientId, ":server 221 $nick +i\r\n", $clientData);
                } else {
                    sendToClient($client, $clientId, ":server 502 $nick :Cant change mode for other users\r\n", $clientData);
                }
            }
            break;

        case 'QUIT':
            $nick = $clientData[$clientId]['nick'];
            $reason = trim($args, ':');
            if ($nick) {
                foreach ($clientData[$clientId]['channels'] as $chan) {
                    RoomManager::leaveRoom($chan, $nick);
                    broadcastToChannel($channels, $chan, ":$nick QUIT :" . ($reason ?: "Client exited") . "\r\n", $clientData);
                    broadcastToChannel($channels, $chan, ":$nick QUIT :" . ($reason ?: "Client exited") . "\r\n");
                    // Remove from our channels tracking
                    if (isset($channels[$chan][$clientId])) {
                        unset($channels[$chan][$clientId]);
                    }
                }
            }
            if (($key = array_search($client, $clients)) !== false) {
                unset($clients[$key]);
            }
            unset($clientData[$clientId]);
            fclose($client);
            break;
    }
}

function broadcastToChannel(&$channels, $chan, $rawMessage, &$clientData = []) {
    if (!isset($channels[$chan])) return;
    foreach ($channels[$chan] as $cid => $client) {
        if (is_resource($client)) {
            if (!empty($clientData)) {
                sendToClient($client, $cid, $rawMessage, $clientData);
            } else {
                fwrite($client, $rawMessage);
            }
        }
    }
}

function welcomeClient($client, $clientId, $nick, &$clientData) {
    sendToClient($client, $clientId, ":server 001 $nick :Welcome to the Fortress IRC Network $nick\r\n", $clientData);
    sendToClient($client, $clientId, ":server 002 $nick :Your host is server, running version 1.0\r\n", $clientData);
    sendToClient($client, $clientId, ":server 003 $nick :This server was created today\r\n", $clientData);

    $motd = MotdServ::getMotd();
    if ($motd) {
        sendToClient($client, $clientId, ":server 375 $nick :- server Message of the day - \r\n", $clientData);
        $lines = explode("\n", $motd);
        foreach ($lines as $line) {
            sendToClient($client, $clientId, ":server 372 $nick :- $line\r\n", $clientData);
        }
        sendToClient($client, $clientId, ":server 376 $nick :End of /MOTD command.\r\n", $clientData);
    }
}

function cloneCommandForService($nick, $service, $text) {
    // Process direct MSG to a service as if it was a command in a channel
    // e.g. PRIVMSG NAMESERV :REGISTER pass email -> /msg NAMESERV REGISTER pass email
    return IrcServices::processCommand($nick, '#lobby', "/msg $service $text");
}
