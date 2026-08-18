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
                'channels' => []
            ];

            echo "New client connected: $clientId\n";
        }
        $key = array_search($server, $read);
        unset($read[$key]);
    }

    // Read data from clients
    foreach ($read as $client) {
        $clientId = (int)$client;
        $data = fread($client, 1024);

        if ($data === false || $data === '') {
            // Client disconnected
            echo "Client disconnected: $clientId\n";
            $nick = $clientData[$clientId]['nick'];
            if ($nick) {
                foreach ($clientData[$clientId]['channels'] as $chan) {
                    RoomManager::leaveRoom($chan, $nick);
                    broadcastToChannel($channels, $chan, ":$nick QUIT :Client exited\r\n");
                    // Remove from our channels tracking
                    if (isset($channels[$chan][$clientId])) {
                        unset($channels[$chan][$clientId]);
                    }
                }
            }
            unset($clients[array_search($client, $clients)]);
            unset($clientData[$clientId]);
            fclose($client);
            continue;
        }

        $clientData[$clientId]['buffer'] .= $data;
        if (strlen($clientData[$clientId]['buffer']) > 1024 * 1024) {
            fclose($client);
            if (($key = array_search($client, $clients)) !== false) {
                unset($clients[$key]);
            }
            unset($clientData[$clientId]);
            continue;
        }

        // Process lines
        while (($pos = strpos($clientData[$clientId]['buffer'], "\n")) !== false) {
            $line = substr($clientData[$clientId]['buffer'], 0, $pos);
            $clientData[$clientId]['buffer'] = substr($clientData[$clientId]['buffer'], $pos + 1);
            $line = trim($line, "\r");

            if (empty($line)) continue;

            echo "[$clientId IN] $line\n";
            processCommand($client, $clientId, $line, $clientData, $channels, $clients);
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
                        fwrite($client, ":$sender PRIVMSG $chan :{$msg['message']}\r\n");
                    }
                } elseif ($msg['type'] === 'peer-joined') {
                    $sender = $msg['sender'];
                    if ($sender !== $nick) {
                        fwrite($client, ":$sender JOIN $chan\r\n");
                    }
                } elseif ($msg['type'] === 'peer-left') {
                    $sender = $msg['sender'];
                    if ($sender !== $nick) {
                        fwrite($client, ":$sender PART $chan\r\n");
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
                fwrite($client, ":server 432 * $args :Erroneous nickname\r\n");
                return;
            }
            $oldNick = $clientData[$clientId]['nick'];
            $clientData[$clientId]['nick'] = $nick;

            if ($oldNick === null && $clientData[$clientId]['user'] !== null) {
                welcomeClient($client, $nick);
            } elseif ($oldNick !== null) {
                fwrite($client, ":$oldNick NICK $nick\r\n");
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
                welcomeClient($client, $clientData[$clientId]['nick']);
            }
            break;

        case 'PING':
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

                fwrite($client, ":$nick JOIN $c\r\n");

                // Get list of users (we could poll RoomManager, but we also have local state)
                // In a full implementation, we'd sync this better.
                fwrite($client, ":server 332 $nick $c :Welcome to $c\r\n");

                $users = RoomManager::joinRoom($c, $nick)['peers'];
                $users[] = $nick; // Add self
                $userList = implode(' ', $users);

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
                // Channel message
                RoomManager::broadcastSignal($target, $nick, [
                    'type' => 'chat',
                    'message' => $text
                ], true); // Exclude self

                // Check if it's a command for services like /supersilent
                if (str_starts_with($text, '/')) {
                    $result = IrcServices::processCommand($nick, $target, $text);
                    if ($result !== null) {
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
                                    fwrite($clients[$key], ":$nick PRIVMSG $target :$text\r\n");
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    if (!$found) {
                        fwrite($client, ":server 401 $nick $target :No such nick/channel\r\n");
                    }
                }
            }
            break;

        case 'QUIT':
            $nick = $clientData[$clientId]['nick'];
            $reason = trim($args, ':');
            if ($nick) {
                foreach ($clientData[$clientId]['channels'] as $chan) {
                    RoomManager::leaveRoom($chan, $nick);
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

function broadcastToChannel(&$channels, $chan, $rawMessage) {
    if (!isset($channels[$chan])) return;
    foreach ($channels[$chan] as $client) {
        if (is_resource($client)) {
            fwrite($client, $rawMessage);
        }
    }
}

function welcomeClient($client, $nick) {
    fwrite($client, ":server 001 $nick :Welcome to the Fortress IRC Network $nick\r\n");
    fwrite($client, ":server 002 $nick :Your host is server, running version 1.0\r\n");
    fwrite($client, ":server 003 $nick :This server was created today\r\n");

    $motd = MotdServ::getMotd();
    if ($motd) {
        fwrite($client, ":server 375 $nick :- server Message of the day - \r\n");
        $lines = explode("\n", $motd);
        foreach ($lines as $line) {
            fwrite($client, ":server 372 $nick :- $line\r\n");
        }
        fwrite($client, ":server 376 $nick :End of /MOTD command.\r\n");
    }
}

function cloneCommandForService($nick, $service, $text) {
    // Process direct MSG to a service as if it was a command in a channel
    // e.g. PRIVMSG NAMESERV :REGISTER pass email -> /msg NAMESERV REGISTER pass email
    return IrcServices::processCommand($nick, '#lobby', "/msg $service $text");
}
