<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\Database;
use PDO;

/**
 * Service Registry Manager for Foreign Services Operating Under Different Hosts.
 * Allows registering, unregistering, pinging, listing, and querying foreign services.
 */
class ServiceRegistry
{
    /**
     * Register or update a foreign service operating under a foreign host.
     *
     * @param string $serviceName Unique bot/service name (e.g. STATS_BOT, DISCORD_BRIDGE, HELPSERV)
     * @param string $host Host domain or IP operating the service (e.g. bot.external-domain.com, 10.0.0.50)
     * @param string $apiEndpoint Endpoint URL or handler path for dispatching service commands
     * @param string|null $metadata Optional JSON or description metadata
     * @return array{success: bool, message: string, service?: array}
     */
    public static function registerService(string $serviceName, string $host, string $apiEndpoint, ?string $metadata = null): array
    {
        $serviceName = strtoupper(trim($serviceName));
        $host = trim($host);
        $apiEndpoint = trim($apiEndpoint);

        if (empty($serviceName) || empty($host) || empty($apiEndpoint)) {
            return [
                'success' => false,
                'message' => 'SERVICE REGISTRY: Service name, host, and API endpoint are required.'
            ];
        }

        if (!preg_match('/^[A-Z0-9\_\-]+$/', $serviceName)) {
            return [
                'success' => false,
                'message' => 'SERVICE REGISTRY: Service name must contain only uppercase letters, numbers, underscores, or dashes.'
            ];
        }

        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM foreign_services WHERE UPPER(service_name) = UPPER(:name)");
        $stmt->execute([':name' => $serviceName]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            $update = $pdo->prepare("UPDATE foreign_services SET host = :host, api_endpoint = :endpoint, status = 'ACTIVE', last_ping = :now, metadata = :meta WHERE UPPER(service_name) = UPPER(:name)");
            $success = $update->execute([
                ':host' => $host,
                ':endpoint' => $apiEndpoint,
                ':now' => $now,
                ':meta' => $metadata,
                ':name' => $serviceName
            ]);
            $action = 'updated';
        } else {
            $insert = $pdo->prepare("INSERT INTO foreign_services (service_name, host, api_endpoint, status, registered_at, last_ping, metadata) VALUES (:name, :host, :endpoint, 'ACTIVE', :reg, :ping, :meta)");
            $success = $insert->execute([
                ':name' => $serviceName,
                ':host' => $host,
                ':endpoint' => $apiEndpoint,
                ':reg' => $now,
                ':ping' => $now,
                ':meta' => $metadata
            ]);
            $action = 'registered';
        }

        if ($success) {
            return [
                'success' => true,
                'message' => "SERVICE REGISTRY: Foreign service '{$serviceName}' operating under host '{$host}' successfully {$action}.",
                'service' => [
                    'service_name' => $serviceName,
                    'host' => $host,
                    'api_endpoint' => $apiEndpoint,
                    'status' => 'ACTIVE',
                    'last_ping' => $now,
                    'metadata' => $metadata
                ]
            ];
        }

        return ['success' => false, 'message' => 'SERVICE REGISTRY: Database execution error while registering foreign service.'];
    }

    /**
     * Unregister a foreign service
     */
    public static function unregisterService(string $serviceName): array
    {
        $serviceName = strtoupper(trim($serviceName));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("DELETE FROM foreign_services WHERE UPPER(service_name) = UPPER(:name)");
        $stmt->execute([':name' => $serviceName]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => "SERVICE REGISTRY: Foreign service '{$serviceName}' successfully unregistered."];
        }

        return ['success' => false, 'message' => "SERVICE REGISTRY: Foreign service '{$serviceName}' not found."];
    }

    /**
     * Send heartbeat/ping update for a registered foreign service
     */
    public static function pingService(string $serviceName, ?string $status = 'ACTIVE'): array
    {
        $serviceName = strtoupper(trim($serviceName));
        $pdo = Database::getConnection();
        $now = time();

        $stmt = $pdo->prepare("UPDATE foreign_services SET last_ping = :time, status = :status WHERE UPPER(service_name) = UPPER(:name)");
        $stmt->execute([':time' => $now, ':status' => $status, ':name' => $serviceName]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => "SERVICE REGISTRY: Heartbeat updated for '{$serviceName}'."];
        }

        return ['success' => false, 'message' => "SERVICE REGISTRY: Foreign service '{$serviceName}' not found."];
    }

    /**
     * Get details of a registered foreign service by service name or host
     */
    public static function getService(string $serviceNameOrHost): ?array
    {
        $query = trim($serviceNameOrHost);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT service_name, host, api_endpoint, status, registered_at, last_ping, metadata FROM foreign_services WHERE UPPER(service_name) = UPPER(:q) OR LOWER(host) = LOWER(:q)");
        $stmt->execute([':q' => $query]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * List all registered foreign services
     *
     * @return array<int, array{service_name: string, host: string, api_endpoint: string, status: string, registered_at: int, last_ping: int, metadata: string|null}>
     */
    public static function listServices(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT service_name, host, api_endpoint, status, registered_at, last_ping, metadata FROM foreign_services ORDER BY service_name ASC");
        return $stmt->fetchAll();
    }
}
