<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/TokenManager.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\TokenManager;
use Fortress\Security\Sanitizer;

SecurityHeaders::apply();

header('Content-Type: application/json');

$csrfToken = TokenManager::generateCsrfToken();

// Parse room ID from request URI if provided as ivc.com/#room or ivc.com/<room>
$requestUri = parse_url($_SERVER['HTTP_REFERER'] ?? '/', PHP_URL_PATH) ?? '/';
$uriPath = trim($requestUri, '/');

$urlRoom = '';
if (!empty($uriPath) && !str_contains($uriPath, '.') && !str_starts_with($uriPath, 'api/')) {
    $urlRoom = Sanitizer::sanitizeRoomId($uriPath);
} elseif (isset($_GET['room'])) {
    $urlRoom = Sanitizer::sanitizeRoomId((string)$_GET['room']);
}

echo json_encode([
    'csrfToken' => $csrfToken,
    'urlRoom' => $urlRoom
], JSON_THROW_ON_ERROR);
