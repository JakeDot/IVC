<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * SERVSERV (Services Directory & Foreign Service Dispatcher Bot)
 * Manages network services overview and routes commands to local or foreign services operating under different hosts.
 */
class ServServ
{
    public const SERVICE_NAME = 'SERVSERV';

    /**
     * List all core local services and registered foreign services
     */
    public static function listAllServices(): array
    {
        $coreServices = [
            'NAMESERV' => 'Nickname Registration & Auth Service',
            'CHANSERV' => 'Channel Management & Operator Controls',
            'MOTDSERV' => 'Message of the Day Management',
            'MEMOSERV' => 'Offline Messaging & Memo Storage',
            'HOSTSERV' => 'Virtual Host (VHost) Management',
            'SERVSERV' => 'Network & Foreign Services Directory',
        ];

        $foreignServices = ServiceRegistry::listServices();

        $lines = ["SERVSERV Registered IRC Network Services:"];
        $lines[] = "--- CORE LOCAL SERVICES ---";
        foreach ($coreServices as $name => $desc) {
            $lines[] = "• {$name} (Local Host): {$desc}";
        }

        if (!empty($foreignServices)) {
            $lines[] = "--- FOREIGN SERVICES OPERATING UNDER DIFFERENT HOSTS ---";
            foreach ($foreignServices as $fs) {
                $status = strtoupper($fs['status']);
                $lines[] = "• {$fs['service_name']} (Host: {$fs['host']}, Status: {$status}) - Endpoint: {$fs['api_endpoint']}";
            }
        } else {
            $lines[] = "--- FOREIGN SERVICES ---";
            $lines[] = "• No foreign services currently registered. Register using: /msg SERVSERV REGISTER <name> <host> <endpoint>";
        }

        return [
            'success' => true,
            'message' => implode("\n", $lines),
            'core_services' => $coreServices,
            'foreign_services' => $foreignServices
        ];
    }

    /**
     * Get detailed information on a service
     */
    public static function getServiceInfo(string $serviceName): array
    {
        $serviceName = strtoupper(trim($serviceName));

        $coreServices = [
            'NAMESERV' => 'Nickname Registration & Auth Service',
            'CHANSERV' => 'Channel Management & Operator Controls',
            'MOTDSERV' => 'Message of the Day Management',
            'MEMOSERV' => 'Offline Messaging & Memo Storage',
            'HOSTSERV' => 'Virtual Host (VHost) Management',
            'SERVSERV' => 'Network & Foreign Services Directory',
        ];

        if (isset($coreServices[$serviceName])) {
            return [
                'success' => true,
                'message' => "SERVSERV Info for {$serviceName}:\n• Type: Core Local Service\n• Host: Localhost\n• Description: {$coreServices[$serviceName]}"
            ];
        }

        $fs = ServiceRegistry::getService($serviceName);
        if ($fs) {
            $regDate = date('Y-m-d H:i:s', (int)$fs['registered_at']);
            $lastPing = date('Y-m-d H:i:s', (int)$fs['last_ping']);
            $msg = "SERVSERV Foreign Service Info for {$fs['service_name']}:\n" .
                   "• Host: {$fs['host']}\n" .
                   "• Endpoint: {$fs['api_endpoint']}\n" .
                   "• Status: {$fs['status']}\n" .
                   "• Registered: {$regDate}\n" .
                   "• Last Ping: {$lastPing}\n" .
                   "• Metadata: " . ($fs['metadata'] ?? 'None');

            return ['success' => true, 'message' => $msg, 'data' => $fs];
        }

        return ['success' => false, 'message' => "SERVSERV: Service '{$serviceName}' not found."];
    }

    /**
     * Register a foreign service via bot command
     */
    public static function registerForeignService(string $serviceName, string $host, string $apiEndpoint, ?string $metadata = null): array
    {
        $res = ServiceRegistry::registerService($serviceName, $host, $apiEndpoint, $metadata);
        return [
            'success' => $res['success'],
            'message' => "SERVSERV: " . $res['message']
        ];
    }

    /**
     * Dispatch or route command to a foreign service operating under a different host
     */
    public static function dispatchForeignCommand(string $senderNick, string $serviceName, string $commandText): array
    {
        $serviceName = strtoupper(trim($serviceName));
        $commandText = trim($commandText);

        $fs = ServiceRegistry::getService($serviceName);
        if (!$fs) {
            return [
                'success' => false,
                'message' => "SERVSERV: Foreign service '{$serviceName}' is not registered."
            ];
        }

        if (strtoupper($fs['status']) !== 'ACTIVE') {
            return [
                'success' => false,
                'message' => "SERVSERV: Foreign service '{$serviceName}' on host '{$fs['host']}' is currently INACTIVE."
            ];
        }

        // Simulating or routing the foreign service dispatch
        // In real HTTP / webhook bridge execution, this would call $fs['api_endpoint']
        $response = "Dispatched command '{$commandText}' from {$senderNick} to Foreign Service {$serviceName} [Host: {$fs['host']}, Endpoint: {$fs['api_endpoint']}] - OK";

        return [
            'success' => true,
            'service_name' => $serviceName,
            'host' => $fs['host'],
            'message' => "{$serviceName}@{$fs['host']}: {$response}"
        ];
    }
}
