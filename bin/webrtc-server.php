#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/Sanitizer.php';
require_once __DIR__ . '/../src/Security/RateLimiter.php';
require_once __DIR__ . '/../src/Signaling/RoomManager.php';

use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\Signaling\RoomManager;

echo "=========================================================\n";
echo " 🏰 Fortress WebRTC Non-Logging Signaling Server (PHP 8.5)\n";
echo "=========================================================\n";
echo "Starting ephemeral in-memory WebRTC signaling process...\n";

$port = isset($argv[1]) ? (int)$argv[1] : 8088;
$address = "127.0.0.1:$port";

$server = @stream_socket_server("tcp://$address", $errno, $errstr);

if (!$server) {
    echo "Error starting signaling server on $address: $errstr ($errno)\n";
    exit(1);
}

echo "✅ WebRTC Signaling Server active on tcp://$address\n";
echo "🔒 Non-Logging Policy: ACTIVE (0 bytes written to disk)\n\n";

// Keep server alive or run background loops if run as standalone daemon
while ($socket = @stream_socket_accept($server, 1)) {
    $request = fread($socket, 4096);
    if ($request === false || $request === '') {
        fclose($socket);
        continue;
    }

    $response = "HTTP/1.1 200 OK\r\n";
    $response .= "Content-Type: application/json\r\n";
    $response .= "Cache-Control: no-store, no-cache, private\r\n";
    $response .= "Connection: close\r\n\r\n";
    $response .= json_encode([
        'status' => 'active',
        'server' => 'Fortress-WebRTC-PHP8.5',
        'time' => time()
    ]);

    fwrite($socket, $response);
    fclose($socket);

    RoomManager::gc();
}

fclose($server);
