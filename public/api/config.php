<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/TokenManager.php';

use Fortress\Security\SecurityHeaders;
use Fortress\Security\TokenManager;

SecurityHeaders::apply();

header('Content-Type: application/json');

echo json_encode([
    'csrfToken' => TokenManager::generateCsrfToken()
], JSON_THROW_ON_ERROR);
