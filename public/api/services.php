<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Security/SecurityHeaders.php';
require_once __DIR__ . '/../../src/Security/Sanitizer.php';
require_once __DIR__ . '/../../src/Security/RateLimiter.php';
require_once __DIR__ . '/../../src/Database/Database.php';
require_once __DIR__ . '/../../src/IRC/ServiceRegistry.php';
require_once __DIR__ . '/../../src/IRC/ServServ.php';

use cx\ivc\Security\SecurityHeaders;
use cx\ivc\Security\Sanitizer;
use cx\ivc\Security\RateLimiter;
use cx\ivc\IRC\ServiceRegistry;
use cx\ivc\IRC\ServServ;

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
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $services = ServiceRegistry::listServices();
        echo json_encode(['status' => 'ok', 'services' => $services], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'info' && (!empty($_GET['service']) || !empty($_GET['host']))) {
        $query = $_GET['service'] ?? $_GET['host'];
        $info = ServServ::getServiceInfo((string)$query);
        echo json_encode(['status' => 'ok', 'info' => $info], JSON_THROW_ON_ERROR);
        exit;
    }

    $all = ServServ::listAllServices();
    echo json_encode(['status' => 'ok', 'services' => $all], JSON_THROW_ON_ERROR);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_THROW_ON_ERROR);
        exit;
    }

    $action = strtolower($data['action'] ?? $data['type'] ?? 'register');

    if ($action === 'register') {
        $serviceName = trim($data['service_name'] ?? $data['service'] ?? $data['name'] ?? '');
        $host = trim($data['host'] ?? $data['domain'] ?? '');
        $endpoint = trim($data['api_endpoint'] ?? $data['endpoint'] ?? $data['url'] ?? '');
        $metadata = isset($data['metadata']) ? (is_array($data['metadata']) ? json_encode($data['metadata']) : (string)$data['metadata']) : null;

        if (empty($serviceName) || empty($host) || empty($endpoint)) {
            http_response_code(400);
            echo json_encode(['error' => 'service_name, host, and api_endpoint are required'], JSON_THROW_ON_ERROR);
            exit;
        }

        $res = ServiceRegistry::registerService($serviceName, $host, $endpoint, $metadata);
        if ($res['success']) {
            echo json_encode(['status' => 'ok', 'message' => $res['message'], 'service' => $res['service'] ?? null], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $res['message']], JSON_THROW_ON_ERROR);
        }
        exit;
    }

    if ($action === 'unregister') {
        $serviceName = trim($data['service_name'] ?? $data['service'] ?? '');
        if (empty($serviceName)) {
            http_response_code(400);
            echo json_encode(['error' => 'service_name required'], JSON_THROW_ON_ERROR);
            exit;
        }

        $res = ServiceRegistry::unregisterService($serviceName);
        if ($res['success']) {
            echo json_encode(['status' => 'ok', 'message' => $res['message']], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
            echo json_encode(['error' => $res['message']], JSON_THROW_ON_ERROR);
        }
        exit;
    }

    if ($action === 'ping' || $action === 'heartbeat') {
        $serviceName = trim($data['service_name'] ?? $data['service'] ?? '');
        $svcStatus = $data['status'] ?? 'ACTIVE';

        if (empty($serviceName)) {
            http_response_code(400);
            echo json_encode(['error' => 'service_name required'], JSON_THROW_ON_ERROR);
            exit;
        }

        $res = ServiceRegistry::pingService($serviceName, $svcStatus);
        if ($res['success']) {
            echo json_encode(['status' => 'ok', 'message' => $res['message']], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
            echo json_encode(['error' => $res['message']], JSON_THROW_ON_ERROR);
        }
        exit;
    }

    if ($action === 'execute' || $action === 'command') {
        $sender = Sanitizer::sanitizeClientId($data['sender'] ?? $data['nickname'] ?? 'User');
        $serviceName = trim($data['service_name'] ?? $data['service'] ?? '');
        $command = trim($data['command'] ?? $data['text'] ?? '');

        if (empty($serviceName) || empty($command)) {
            http_response_code(400);
            echo json_encode(['error' => 'service_name and command text required'], JSON_THROW_ON_ERROR);
            exit;
        }

        $res = ServServ::dispatchForeignCommand($sender, $serviceName, $command);
        if ($res['success']) {
            echo json_encode(['status' => 'ok', 'response' => $res['message'], 'service' => $res['service_name'], 'host' => $res['host']], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $res['message']], JSON_THROW_ON_ERROR);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unsupported action'], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_THROW_ON_ERROR);
