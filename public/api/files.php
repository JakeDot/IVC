<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Models/SharedFile.php';
require_once __DIR__ . '/../../src/Database/Database.php';
require_once __DIR__ . '/../../src/Database/SharedFileRepository.php';
require_once __DIR__ . '/../../src/Signaling/RoomManager.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\Sanitizer;
use Fortress\Security\RateLimiter;
use Fortress\Models\SharedFile;
use Fortress\Database\SharedFileRepository;
use Fortress\Signaling\RoomManager;

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
    $channel = Sanitizer::sanitizeRoomId($_GET['channel'] ?? $_GET['room'] ?? '');

    if (empty($channel)) {
        http_response_code(400);
        echo json_encode(['error' => 'Channel parameter required'], JSON_THROW_ON_ERROR);
        exit;
    }

    $files = SharedFileRepository::findByChannel($channel);
    $result = array_map(fn(SharedFile $f) => $f->toArray(), $files);

    echo json_encode([
        'status' => 'ok',
        'channel' => $channel,
        'files' => $result
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST') {
    $rawPayload = file_get_contents('php://input');
    if ($rawPayload === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request payload'], JSON_THROW_ON_ERROR);
        exit;
    }

    $data = json_decode((string)$rawPayload, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        exit;
    }

    $id = trim((string)($data['id'] ?? 'file_' . bin2hex(random_bytes(8))));
    $channel = Sanitizer::sanitizeRoomId((string)($data['channel'] ?? $data['room'] ?? ''));
    $sharerClientId = Sanitizer::sanitizeClientId((string)($data['sharer_client_id'] ?? $data['client'] ?? ''));
    $encryptedMetadata = trim((string)($data['encrypted_metadata'] ?? ''));
    $cloudLink = isset($data['cloud_link']) ? trim((string)$data['cloud_link']) : null;

    if (empty($id) || empty($channel) || empty($sharerClientId) || empty($encryptedMetadata)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: id, channel, sharer_client_id, encrypted_metadata'], JSON_THROW_ON_ERROR);
        exit;
    }

    $file = new SharedFile(
        $id,
        $channel,
        $sharerClientId,
        $encryptedMetadata,
        $cloudLink,
        isset($data['created_at']) ? (int)$data['created_at'] : time()
    );

    $saved = SharedFileRepository::save($file);

    if ($saved) {
        // Broadcast file-shared signal to room peers so connected peers receive real-time updates
        RoomManager::broadcastSignal($channel, $sharerClientId, [
            'type' => 'file-shared',
            'file_id' => $id,
            'sharer_client_id' => $sharerClientId,
            'encrypted_metadata' => $encryptedMetadata,
            'cloud_link' => $file->getCloudLink(),
            'created_at' => $file->getCreatedAt()
        ], false);

        echo json_encode([
            'status' => 'ok',
            'file_id' => $id,
            'cloud_link' => $file->getCloudLink(),
            'message' => 'File metadata successfully stored E2EE'
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Failed to store file metadata'], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
