<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * IRC Service Command Dispatcher & Parser
 * Handles interaction with NAMESERV, CHANSERV, PAYSERV, MOTDSERV, MEMOSERV, HOSTSERV, SERVICESERV,
 * THEMESERV, and Foreign Services operating under different hosts via IRC-style slash commands or direct messages.
 */
class IrcServices
{
    /**
     * Parse subobjects (§prop property and ∆event event objects) and their modes (+mo-des) from a target or path.
     *
     * @param string $input
     * @return array{base_target: string, subobjects: array<int, array{symbol: string, type: string, name: string, value: string, modes: string, mode_flags: array}>, props: array<string, array{value: string, modes: string, mode_flags: array}>, events: array<string, array{value: string, modes: string, mode_flags: array}>}
     */
    public static function parseSubobjects(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [
                'base_target' => '',
                'subobjects' => [],
                'props' => [],
                'events' => []
            ];
        }

        $firstSubPos = null;
        if (preg_match('/[§∆Δ]/u', $input, $matches, PREG_OFFSET_CAPTURE)) {
            $firstSubPos = mb_strlen(substr($input, 0, $matches[0][1]));
        }

        if ($firstSubPos === null) {
            return [
                'base_target' => $input,
                'subobjects' => [],
                'props' => [],
                'events' => []
            ];
        }

        $baseTarget = mb_substr($input, 0, $firstSubPos);
        $subStr = mb_substr($input, $firstSubPos);

        $tokens = preg_split('/([§∆Δ])/u', $subStr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $subobjects = [];
        $props = [];
        $events = [];

        for ($i = 0; $i < count($tokens); $i += 2) {
            $symbol = $tokens[$i] ?? '';
            $segment = $tokens[$i + 1] ?? '';

            if ($symbol !== '§' && $symbol !== '∆' && $symbol !== 'Δ') {
                continue;
            }

            $type = ($symbol === '§') ? 'property' : 'event';
            $name = '';
            $value = 'true';
            $modes = '';

            if (preg_match('/^([a-zA-Z0-9_\-\.\/]+)(?:\+([a-zA-Z0-9_]+))?=(.*?)([\+\-][a-zA-Z0-9_\-\+]+)?$/u', $segment, $m)) {
                $name = $m[1];
                $modes = ($m[2] ?? '') !== '' ? '+' . $m[2] : ($m[4] ?? '');
                $value = $m[3];
            } elseif (preg_match('/^([a-zA-Z0-9_\-\.\/]+)=(.*?)([\+\-][a-zA-Z0-9_\-\+]+)?$/u', $segment, $m)) {
                $name = $m[1];
                $value = $m[2];
                $modes = $m[3] ?? '';
            } elseif (preg_match('/^([a-zA-Z0-9_\-\.\/]+)([\+\-][a-zA-Z0-9_\-\+]+)?$/u', $segment, $m)) {
                $name = $m[1];
                $modes = $m[2] ?? '';
                $value = 'true';
            } else {
                $name = $segment;
            }

            $modeFlags = ChanServ::parseModeFlags($modes);

            $subItem = [
                'symbol' => $symbol,
                'type' => $type,
                'name' => $name,
                'value' => $value,
                'modes' => $modes,
                'mode_flags' => $modeFlags
            ];

            $subobjects[] = $subItem;

            $dictItem = [
                'value' => $value,
                'modes' => $modes,
                'mode_flags' => $modeFlags
            ];

            if ($type === 'property') {
                $props[$name] = $dictItem;
            } else {
                $events[$name] = $dictItem;
            }
        }

        return [
            'base_target' => $baseTarget,
            'subobjects' => $subobjects,
            'props' => $props,
            'events' => $events
        ];
    }

    /**
     * Format an object dictionary or map into an ivc://$me/object§prop=value URI.
     *
     * @param mixed $objData
     * @param string $host
     * @return string
     */
    public static function formatObjectUri(mixed $objData, string $host = '$me'): string
    {
        if (is_string($objData)) {
            $objData = trim($objData);
            if (str_starts_with($objData, 'ivc://')) {
                return $objData;
            }
            if (str_contains($objData, ' ') || str_contains($objData, ':') || str_starts_with($objData, '{')) {
                $trimmed = trim(preg_replace('/^\{|\}$/', '', $objData));
                $parts = preg_split('/\s+/', $trimmed);
                $objName = array_shift($parts) ?: 'object';
                $subs = '';
                foreach ($parts as $part) {
                    if (str_contains($part, ':')) {
                        list($k, $v) = explode(':', $part, 2);
                        $subs .= "§{$k}={$v}";
                    } elseif (str_contains($part, '=')) {
                        list($k, $v) = explode('=', $part, 2);
                        $subs .= "§{$k}={$v}";
                    }
                }
                return "ivc://{$host}/{$objName}{$subs}";
            }
            return "ivc://{$host}/{$objData}";
        }

        if (!is_array($objData)) {
            return "ivc://{$host}/object";
        }

        $baseObject = $objData['object'] ?? $objData['name'] ?? null;

        if ($baseObject === null) {
            $keys = array_keys($objData);
            if (count($keys) === 1 && is_array($objData[$keys[0]])) {
                $baseObject = $keys[0];
                $props = $objData[$keys[0]];
            } else {
                $baseObject = 'object';
                $props = $objData;
            }
        } else {
            $props = $objData;
        }

        $subStr = '';
        $reservedKeys = ['object', 'name', 'host', 'protocol', 'scheme', 'subobjects', 'props', 'events', 'uri', 'asObject', 'modes'];

        foreach ($props as $k => $v) {
            if (in_array((string)$k, $reservedKeys, true)) {
                continue;
            }

            $symbol = '§';
            $keyName = (string)$k;

            if (str_starts_with($keyName, '§')) {
                $symbol = '§';
                $keyName = mb_substr($keyName, 1);
            } elseif (str_starts_with($keyName, '∆')) {
                $symbol = '∆';
                $keyName = mb_substr($keyName, 1);
            }

            $valStr = 'true';
            $modeStr = '';

            if (is_array($v)) {
                $valStr = (string)($v['value'] ?? 'true');
                $modeStr = (string)($v['modes'] ?? '');
            } else {
                $valStr = (string)$v;
            }

            $subStr .= "{$symbol}{$keyName}={$valStr}{$modeStr}";
        }

        $modesSuffix = !empty($objData['modes']) ? (string)$objData['modes'] : '';
        return "ivc://{$host}/{$baseObject}{$subStr}{$modesSuffix}";
    }

    /**
     * Parse an ivc://$me/object§prop=value URI into an object map and structure.
     *
     * @param string $uri
     * @return array
     */
    public static function parseObjectFromUri(string $uri): array
    {
        $parsedServer = self::parseServerUri($uri);
        if (!$parsedServer) {
            $subParsed = self::parseSubobjects($uri);
            $baseObj = ltrim($subParsed['base_target'], '#');
            $asObject = ['object' => $baseObj];
            foreach ($subParsed['props'] as $k => $item) {
                $asObject[$k] = $item['value'];
            }
            foreach ($subParsed['events'] as $k => $item) {
                $asObject["∆{$k}"] = $item['value'];
            }
            return [
                'scheme' => 'ivc',
                'host' => '$me',
                'object' => $baseObj,
                'uri' => $uri,
                'subobjects' => $subParsed['subobjects'],
                'props' => $subParsed['props'],
                'events' => $subParsed['events'],
                'asObject' => $asObject
            ];
        }

        $baseObj = ltrim($parsedServer['channel'], '#');
        $asObject = ['object' => $baseObj];

        foreach ($parsedServer['props'] as $k => $item) {
            $asObject[$k] = $item['value'];
        }
        foreach ($parsedServer['events'] as $k => $item) {
            $asObject["∆{$k}"] = $item['value'];
        }

        return [
            'scheme' => strtolower($parsedServer['protocol']),
            'host' => $parsedServer['host'],
            'object' => $baseObj,
            'uri' => $uri,
            'subobjects' => $parsedServer['subobjects'],
            'props' => $parsedServer['props'],
            'events' => $parsedServer['events'],
            'asObject' => $asObject
        ];
    }

    /**
     * Set or toggle modes on a subobject.
     *
     * @param array $subobject
     * @param string $modeChange
     * @return array
     */
    public static function setSubobjectMode(array $subobject, string $modeChange): array
    {
        $currentModes = $subobject['modes'] ?? '';
        $add = true;
        $chars = mb_str_split($modeChange);

        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            if ($char === '+') {
                $add = true;
            } elseif ($char === '-') {
                $add = false;
            } elseif (preg_match('/[a-zA-Z]/u', $char)) {
                if ($add && !str_contains($currentModes, $char)) {
                    $currentModes .= $char;
                } elseif (!$add && str_contains($currentModes, $char)) {
                    $currentModes = str_replace($char, '', $currentModes);
                }
            }
        }

        $cleanModes = str_replace('+', '', $currentModes);
        $currentModes = !empty($cleanModes) ? '+' . $cleanModes : '';

        $subobject['modes'] = $currentModes;
        $subobject['mode_flags'] = ChanServ::parseModeFlags($currentModes);

        return $subobject;
    }

    /**
     * Generate a detailed tracing data stream for a parent object.
     *
     * @param string $parentObject
     * @param array $extraData
     * @return array
     */
    public static function generateTraceStream(string $parentObject, array $extraData = []): array
    {
        $timestamp = microtime(true);
        $traceId = 'tr-' . substr(md5($parentObject . (string)$timestamp . bin2hex(random_bytes(4))), 0, 12);

        return [
            'trace_id' => $traceId,
            'parent_object' => $parentObject,
            'timestamp' => $timestamp,
            'formatted_time' => date('Y-m-d H:i:s', (int)$timestamp),
            'status' => $extraData['status'] ?? 'active',
            'data_stream' => array_merge([
                'event' => 'trace_init',
                'parent' => $parentObject,
                'origin' => '$me',
                'state' => 'monitored'
            ], $extraData)
        ];
    }

    /**
     * Attach a ∆trace subobject to a parent object or URI.
     *
     * @param mixed $objData
     * @param mixed $tracePayload
     * @param string $host
     * @return string
     */
    public static function attachTraceSubobject(mixed $objData, mixed $tracePayload = null, string $host = '$me'): string
    {
        if ($tracePayload === null) {
            $parentName = is_string($objData) ? $objData : ($objData['object'] ?? 'object');
            $traceData = self::generateTraceStream($parentName);
            $tracePayload = $traceData['trace_id'] . ':active';
        } elseif (is_array($tracePayload)) {
            $tracePayload = json_encode($tracePayload);
        }

        if (is_string($objData)) {
            $parsed = self::parseObjectFromUri($objData);
            $objData = $parsed['asObject'];
        } elseif (!is_array($objData)) {
            $objData = ['object' => 'object'];
        }

        $objData['∆trace'] = (string)$tracePayload;

        return self::formatObjectUri($objData, $host);
    }

    /**
     * Get detailed tracing data stream for a parent object from URI, object array, or subobjects structure.
     *
     * @param mixed $objUriOrData
     * @return array|null
     */
    public static function getTraceDataStream(mixed $objUriOrData): ?array
    {
        if (is_string($objUriOrData)) {
            $parsed = self::parseObjectFromUri($objUriOrData);
            $events = $parsed['events'] ?? [];
            $parentObject = $parsed['object'] ?? 'object';
        } elseif (is_array($objUriOrData)) {
            if (isset($objUriOrData['events'])) {
                $events = $objUriOrData['events'];
                $parentObject = $objUriOrData['object'] ?? 'object';
            } else {
                $parsed = self::parseObjectFromUri(self::formatObjectUri($objUriOrData));
                $events = $parsed['events'] ?? [];
                $parentObject = $parsed['object'] ?? ($objUriOrData['object'] ?? 'object');
            }
        } else {
            return null;
        }

        if (!isset($events['trace'])) {
            return null;
        }

        $traceItem = $events['trace'];
        $rawTraceVal = $traceItem['value'] ?? '';

        $decodedPayload = null;
        if (str_starts_with($rawTraceVal, '{') || str_starts_with($rawTraceVal, '[')) {
            $decodedPayload = json_decode($rawTraceVal, true);
        }

        return [
            'parent_object' => $parentObject,
            'symbol' => '∆',
            'subobject' => 'trace',
            'raw_value' => $rawTraceVal,
            'modes' => $traceItem['modes'] ?? '',
            'mode_flags' => $traceItem['mode_flags'] ?? [],
            'stream_details' => $decodedPayload ?? [
                'trace_payload' => $rawTraceVal,
                'parent' => $parentObject,
                'active' => true
            ]
        ];
    }

    /**
     * Parse server URI supporting https://, ivc://, and irc:// protocols.
     *
     * @param string $uri
     * @return array{protocol: string, host: string, port: int, channel: string, modes: string, uri: string, subobjects: array, props: array, events: array}|null
     */
    public static function parseServerUri(string $uri): ?array
    {
        $uri = trim($uri);
        if (!preg_match('/^(https|ivc(?:-[a-zA-Z0-9_-]+)?|irc):\/\/([^\/:#?]*)(?::(\d+))?(?:[\/#](.*))?$/i', $uri, $matches)) {
            return null;
        }

        $scheme = strtolower($matches[1]);
        $hostRaw = $matches[2];

        // Strip +modes from the host component
        $hostModes = '';
        if ($hostRaw !== '' && str_contains($hostRaw, '+')) {
            $parts = explode('+', $hostRaw);
            $hostRaw = $parts[0];
            array_shift($parts); // Remove the base host
            $hostModes = implode('+', $parts); // Keep remaining string if it had multiple +'s
        }
        $host = $hostRaw; // Do not strtolower to preserve case in complex symbol URIs

        $defaultPorts = [
            'https' => 443,
            'ivc'   => 8080,
            'irc'   => 6667,
        ];
        if (str_starts_with($scheme, 'ivc-')) {
            $defaultPorts[$scheme] = 8080;
        }
        $portStr = $matches[3] ?? '';
        $port = $portStr !== '' ? (int)$portStr : ($defaultPorts[$scheme] ?? 443);

        $channel = '#';
        $extractedModes = '';

        $processPrefix = function (string $input): string {
            $first = mb_substr($input, 0, 1);
            if (in_array($first, ['#', '&', '@', '£', '$'], true)) {
                return $input;
            }
            return '#' . $input;
        };

        $rawPathOrFragment = $matches[4] ?? '';

        $subParsed = self::parseSubobjects($rawPathOrFragment);
        $chanRaw = $subParsed['base_target'];

        if ($host === '' && $chanRaw === '') {
            $channel = '£';
        }


        if ($chanRaw !== '') {
            $plusPos = strpos($chanRaw, '+');
            if ($plusPos !== false) {
                $extractedModes = substr($chanRaw, $plusPos + 1);
                $chanRaw = substr($chanRaw, 0, $plusPos);
            }
            if ($chanRaw !== '') {
                $channel = $processPrefix($chanRaw);
            }
        }

        $channel = \Fortress\Security\Sanitizer::sanitizeRoomId($channel);

        // Combine host modes and channel modes
        $allModes = trim($hostModes . $extractedModes, '+');

        return [
            'protocol' => strtoupper($scheme),
            'host'     => $host,
            'port'     => $port,
            'channel'  => $channel,
            'modes'    => $allModes,
            'extracted_modes' => $extractedModes,
            'uri'      => $uri,
            'subobjects' => $subParsed['subobjects'],
            'props'    => $subParsed['props'],
            'events'   => $subParsed['events']
        ];
    }

    /**
     * Check if a message is an IRC service command and execute it.
     *
     * @param string $senderNick
     * @param string $channel
     * @param string $text
     * @return array{is_service_command: true, service: string, response: string, channel: string, appstatus?: string, skip_bot_broadcast?: bool}|null
     */
    public static function processCommand(string $senderNick, string $channel, string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Standardize & automatically IDENT user@domain users or default to user@<anonymous>
        NameServ::autoIdent($senderNick);

        // Apply global "me" aliases
        $text = preg_replace('/(^|\s)@me(?=\s|$)/i', '$1' . $senderNick, $text);
        $text = preg_replace('/(^|\s)#me(?=\s|$)/i', '$1' . $channel, $text);
        $text = preg_replace('/(^|\s)\\$me(?=\s|$)/i', '$1server', $text);

        // Calculate AppStatus block for injection into native clients
        $appModes = $senderNick;
        $chanInfo = ChanServ::getInfo($channel);
        if ($chanInfo['success']) {
            $modes = $chanInfo['data']['modes'] ?? '';
            $opStatus = ChanServ::isOp($channel, $senderNick) ? '+o' : '';
            $appModes .= "{subs [{$channel}{$modes}{$opStatus}]}";
        }

        $parts = preg_split('/\s+/', $text);
        $first = strtolower($parts[0] ?? '');
        $firstOriginal = $parts[0] ?? '';

        // Check if the command is just an emoji (or emojis), alias to /REACT
        if (str_starts_with($firstOriginal, '/') && preg_match('/^\/([\p{Emoji}\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F1E6}-\x{1F1FF}]+)$/u', $firstOriginal, $matches)) {
            $emoji = $matches[1];
            $reactArgs = array_slice($parts, 1);
            if (empty($reactArgs)) {
                $reactArgs[] = (empty($channel) || $channel === '#') ? '£' : $channel;
            }
            $reactText = '/REACT ' . $emoji . ' ' . implode(' ', $reactArgs);

            \Fortress\Signaling\RoomManager::broadcastSignal($channel, $senderNick, [
                'type' => 'chat',
                'sender' => $senderNick,
                'message' => $reactText
            ], false);

            return [
                'is_service_command' => true,
                'service' => 'REACT',
                'response' => "[Reaction {$emoji} sent]",
                'channel' => $channel,
                'skip_bot_broadcast' => true
            ];
        }

        // Check for BOTSERV integration (External bot routing based on username)
        if ($first === '/msg' || $first === '/privmsg') {
            $targetNick = $parts[1] ?? '';
            if (!empty($targetNick) && ($service = BotServ::resolveBotService($channel, $targetNick))) {
                $fullCommand = implode(' ', array_slice($parts, 2));
                $res = ServServ::dispatchForeignCommand($senderNick, $service, $fullCommand);
                return [
                    'is_service_command' => true,
                    'service' => $targetNick,
                    'response' => $res['message'],
                    'channel' => $channel,
                    'appstatus' => $appModes
                ];
            }
        } elseif (str_ends_with($first, ':')) {
            $targetNick = rtrim($first, ':');
            if (!empty($targetNick) && ($service = BotServ::resolveBotService($channel, $targetNick))) {
                $fullCommand = implode(' ', array_slice($parts, 1));
                $res = ServServ::dispatchForeignCommand($senderNick, $service, $fullCommand);
                return [
                    'is_service_command' => true,
                    'service' => $targetNick,
                    'response' => $res['message'],
                    'channel' => $channel,
                    'appstatus' => $appModes
                ];
            }
        }

        // Server Management Commands: /connect and /disconnect
        if ($first === '/connect') {
            $uri = $parts[1] ?? '';
            if (empty($uri)) {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVERSERV',
                    'response' => 'SERVERSERV: Usage: /connect <URI> (Supported protocols: https://, ivc://, irc://)',
                    'channel' => $channel
                ];
            }

            $parsed = self::parseServerUri($uri);
            if (!$parsed) {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVERSERV',
                    'response' => 'SERVERSERV: Invalid URI format. Supported protocols are https://, ivc://, and irc:// (e.g. https://server.com/#channel)',
                    'channel' => $channel
                ];
            }

            $targetObj = $parsed['channel'];
            $reqModes = $parsed['modes'] ?? '';

            $access = ChanServ::checkAccess($parsed['uri'] ?? $targetObj, $senderNick);
            if (!$access['success']) {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVERSERV',
                    'response' => "SERVERSERV: " . $access['message'],
                    'channel' => $channel
                ];
            }

            if ($reqModes !== '') {
                $prefix = mb_substr($targetObj, 0, 1);

                // If the user is trying to join a channel (# or &) with specified modes, verify permissions.
                if (($prefix === '#' || $prefix === '&') && str_contains($reqModes, 'o')) {
                    if (!ChanServ::isRegistered($targetObj)) {
                        return [
                            'is_service_command' => true,
                            'service' => 'SERVERSERV',
                            'response' => "SERVERSERV: Permission denied. Channel {$targetObj} is not registered.",
                            'channel' => $channel
                        ];
                    }

                    if (!ChanServ::isOp($targetObj, $senderNick)) {
                        return [
                            'is_service_command' => true,
                            'service' => 'SERVERSERV',
                            'response' => "SERVERSERV: Permission denied. You must be an OP on {$targetObj} to connect with +{$reqModes} modes.",
                            'channel' => $channel
                        ];
                    }
                }
            }

            return [
                'is_service_command' => true,
                'service' => 'SERVERSERV',
                'response' => "SERVERSERV: Connected to server '{$parsed['host']}:{$parsed['port']}' via {$parsed['protocol']} (Channel: {$parsed['channel']}).",
                'channel' => $parsed['channel'],
                'appstatus' => $appModes
            ];
        }

        if ($first === '/disconnect') {
            $target = $parts[1] ?? '';
            $targetStr = !empty($target) ? " '{$target}'" : "";
            return [
                'is_service_command' => true,
                'service' => 'SERVERSERV',
                'response' => "SERVERSERV: Disconnected from server{$targetStr}.",
                'channel' => $channel,
                'appstatus' => $appModes
            ];
        }

        // 1. Direct /msg or bot service command
        if ($first === '/msg' || $first === '/privmsg') {
            $targetService = strtoupper($parts[1] ?? '');
            $cmd = strtoupper($parts[2] ?? '');
            $args = array_slice($parts, 3);

            if ($targetService === NameServ::SERVICE_NAME || $targetService === 'NICKSERV') {
                return self::handleNameServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === ChanServ::SERVICE_NAME) {
                return self::handleChanServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === PayServ::SERVICE_NAME || $targetService === 'SUBSERV') {
                return self::handlePayServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === MotdServ::SERVICE_NAME) {
                return self::handleMotdServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === MemoServ::SERVICE_NAME) {
                return self::handleMemoServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === BotServ::SERVICE_NAME) {
                return self::handleBotServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === HelpServ::SERVICE_NAME) {
                return self::handleHelpServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === HostServ::SERVICE_NAME) {
                return self::handleHostServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === ServServ::SERVICE_NAME || $targetService === 'SERVICESERV') {
                return self::handleServServCommand($senderNick, $channel, $cmd, $args);
            }

            // Check if $targetService is a registered foreign service operating under a different host
            $foreignService = ServiceRegistry::getService($targetService);
            if ($foreignService) {
                $fullCommand = implode(' ', array_slice($parts, 2));
                $res = ServServ::dispatchForeignCommand($senderNick, $targetService, $fullCommand);
                return [
                    'is_service_command' => true,
                    'service' => $targetService,
                    'response' => $res['message'],
                    'channel' => $channel
                ];
            }
        }

        if ($first === '/nameserv' || $first === '/nickserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleNameServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/chanserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleChanServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/payserv' || $first === '/subserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handlePayServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/motdserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleMotdServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/memoserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleMemoServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/hostserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleHostServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/servserv' || $first === '/serviceserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleServServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/helpserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleHelpServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/botserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleBotServCommand($senderNick, $channel, $cmd, $args);
        }

        if ($first === '/textserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleTextServCommand($senderNick, $channel, $cmd, $args);
        }

        // 2. Chat-based Subscription & Payment Shortcuts (/subscribe & /pay)
        if ($first === '/subscribe' || $first === '/pay') {
            $level = strtolower($parts[1] ?? 'user');
            $target = $parts[2] ?? '';
            $planId = $parts[3] ?? null;

            if ($level === 'plans' || $level === 'list') {
                $res = PayServ::listPlans();
            } else {
                if (empty($target)) {
                    if ($level === 'channel') {
                        $target = $channel;
                    } elseif ($level === 'server') {
                        $target = 'IVC-IRC Network';
                    } else {
                        $target = $senderNick;
                    }
                }
                $res = PayServ::subscribe($senderNick, $level, $target, $planId);
            }

            return [
                'is_service_command' => true,
                'service' => PayServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        // 3. Theme Command
        if ($first === '/cabpfaserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleCabPfaServCommand($senderNick, $channel, $cmd, $args);
        }

        // 2. Theme Command
        if ($first === '/theme') {
            $arg = strtolower($parts[1] ?? 'list');
            if ($arg === 'list' || $arg === 'help') {
                $resp = "Available themes: dark, light, halloween, console, christmas. Usage: /theme <name> or /theme custom";
            } elseif (in_array($arg, ['dark', 'light', 'halloween', 'console', 'christmas'], true)) {
                $resp = "THEMESERV: Theme set to '{$arg}'.";
            } elseif ($arg === 'custom' || $arg === 'create') {
                $resp = "THEMESERV: Custom Theme Creator mode activated.";
            } else {
                $resp = "THEMESERV: Theme set to '{$arg}'.";
            }

            return [
                'is_service_command' => true,
                'service' => 'THEMESERV',
                'response' => $resp,
                'channel' => $channel
            ];
        }

        // 4. Convenience Slash Commands
        if ($first === '/react') {
            $reactArgs = array_slice($parts, 1);
            if (empty($reactArgs)) {
                return [
                    'is_service_command' => true,
                    'service' => 'REACT',
                    'response' => 'REACT: Usage: /react <reaction> [<object-uri|[prefix]me>]',
                    'channel' => $channel
                ];
            }
            if (count($reactArgs) === 1) {
                $reactArgs[] = (empty($channel) || $channel === '#') ? '£' : $channel;
            }
            $reactText = '/REACT ' . implode(' ', $reactArgs);

            \Fortress\Signaling\RoomManager::broadcastSignal($channel, $senderNick, [
                'type' => 'chat',
                'sender' => $senderNick,
                'message' => $reactText
            ], false);

            return [
                'is_service_command' => true,
                'service' => 'REACT',
                'response' => "[Reaction sent]",
                'channel' => $channel,
                'skip_bot_broadcast' => true
            ];
        }

        if ($first === '/join') {
            $rawTarget = $parts[1] ?? '';
            if (empty($rawTarget)) {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVERSERV',
                    'response' => 'SERVERSERV: Usage: /join <#channel[+modes]>',
                    'channel' => $channel
                ];
            }
            $access = ChanServ::checkAccess($rawTarget, $senderNick);
            if (!$access['success']) {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVERSERV',
                    'response' => "SERVERSERV: " . $access['message'],
                    'channel' => $channel
                ];
            }
            $targetChan = $access['base_target'];
            $modesStr = $access['modes'] ?? '';
            return [
                'is_service_command' => true,
                'service' => 'SERVERSERV',
                'response' => "SERVERSERV: Joined channel {$targetChan}{$modesStr}.",
                'channel' => $targetChan . $modesStr,
                'appstatus' => $appModes
            ];
        }

        if ($first === '/part') {
            $targetChan = $parts[1] ?? $channel;
            return [
                'is_service_command' => true,
                'service' => 'SERVERSERV',
                'response' => "SERVERSERV: Left channel {$targetChan}.",
                'channel' => $targetChan,
                'appstatus' => $appModes
            ];
        }

        if ($first === '/raw') {
            $rawLine = trim(substr($text, strlen($parts[0])));
            return [
                'is_service_command' => true,
                'service' => 'SERVERSERV',
                'response' => "[RAW OUTPUT] {$rawLine}",
                'channel' => $channel
            ];
        }

        if ($first === '/delta') {
            $targetChan = $parts[1] ?? $channel;
            ChanServ::setModes($targetChan, '+Δmodes', $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => "CHANSERV: Δmodes active for {$targetChan}.",
                'channel' => $targetChan
            ];
        }

        if ($first === '/mode') {
            $targetChan = $parts[1] ?? $channel;
            $modeStr = $parts[2] ?? '';
            $targetUser = $parts[3] ?? '';
            if (str_starts_with($targetChan, '+') || str_starts_with($targetChan, '-')) {
                $targetUser = $parts[2] ?? '';
                $modeStr = $targetChan;
                $targetChan = $channel;
            }
            if ($modeStr === '') {
                $info = ChanServ::getInfo($targetChan);
                $resp = $info['success'] ? "Modes for {$targetChan}: " . ($info['data']['modes'] ?? '+t') : "No modes set for {$targetChan}.";
            } elseif (!empty($targetUser)) {
                if ($modeStr === '+o') {
                    $res = ChanServ::op($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '-o') {
                    $res = ChanServ::deop($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '+v') {
                    $res = ChanServ::voice($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '-v') {
                    $res = ChanServ::devoice($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '+a') {
                    $res = ChanServ::admin($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '-a') {
                    $res = ChanServ::deadmin($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '+n') {
                    $res = ChanServ::netadmin($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } elseif ($modeStr === '-n') {
                    $res = ChanServ::denetadmin($targetChan, $targetUser, $senderNick);
                    $resp = $res['message'];
                } else {
                    $res = ChanServ::setModes($targetChan, $modeStr, $senderNick);
                    $resp = $res['message'];
                }
            } else {
                $res = ChanServ::setModes($targetChan, $modeStr, $senderNick);
                $resp = $res['message'];
            }
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $resp,
                'channel' => $targetChan
            ];
        }

        if ($first === '/memo') {
            $sub = strtoupper($parts[1] ?? 'LIST');
            if ($sub === 'SEND') {
                $target = $parts[2] ?? '';
                $msg = implode(' ', array_slice($parts, 3));
                $res = MemoServ::send($senderNick, $target, $msg);
            } elseif ($sub === 'READ') {
                $num = (int)($parts[2] ?? 1);
                $res = MemoServ::read($senderNick, $num);
            } elseif ($sub === 'DEL' || $sub === 'DELETE') {
                $num = (int)($parts[2] ?? 1);
                $res = MemoServ::delete($senderNick, $num);
            } else {
                $res = MemoServ::listMemos($senderNick);
            }

            return [
                'is_service_command' => true,
                'service' => MemoServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        if ($first === '/vhost') {
            $sub = strtoupper($parts[1] ?? 'INFO');
            if ($sub === 'REQUEST' || $sub === 'SET') {
                $vh = $parts[2] ?? '';
                $res = HostServ::requestVhost($senderNick, $vh);
            } elseif ($sub === 'ON') {
                $res = HostServ::setVhostStatus($senderNick, true);
            } elseif ($sub === 'OFF') {
                $res = HostServ::setVhostStatus($senderNick, false);
            } else {
                $target = $parts[1] ?? $senderNick;
                $res = HostServ::getVhostInfo($target);
            }

            return [
                'is_service_command' => true,
                'service' => HostServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        if ($first === '/motd') {
            $newMotd = trim(substr($text, strlen($parts[0])));
            if ($newMotd === '') {
                $res = MotdServ::getInfo();
            } else {
                $res = MotdServ::setMotd($newMotd, $senderNick);
            }
            return [
                'is_service_command' => true,
                'service' => MotdServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        if ($first === '/op') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::op($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/deop') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::deop($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/voice') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::voice($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/devoice') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::devoice($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/admin') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::admin($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/deadmin') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::deadmin($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/netadmin') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::netadmin($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/denetadmin') {
            $target = $parts[1] ?? '';
            $chan = (!empty($parts[2]) ? $parts[2] : $channel);
            $res = ChanServ::denetadmin($chan, $target, $senderNick);
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $chan
            ];
        }

        if ($first === '/topic') {
            $newTopic = trim(substr($text, strlen($parts[0])));
            if ($newTopic === '') {
                $info = ChanServ::getInfo($channel);
                $resp = $info['success'] ? "Topic for {$channel}: " . ($info['data']['topic'] ?? 'None') : "No topic set for {$channel}.";
            } else {
                $res = ChanServ::setTopic($channel, $newTopic, $senderNick);
                $resp = $res['message'];
            }
            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => $resp,
                'channel' => $channel
            ];
        }

        if ($first === '/register') {
            $arg1 = $parts[1] ?? '';
            $arg2 = $parts[2] ?? '';

            if (str_starts_with($arg1, '#') || str_starts_with($arg1, '&')) {
                $res = ChanServ::register($arg1, $senderNick, $arg2 !== '' ? $arg2 : null);
                $serv = ChanServ::SERVICE_NAME;
            } else {
                $res = NameServ::register($senderNick, $arg1, $arg2 !== '' ? $arg2 : null);
                $serv = NameServ::SERVICE_NAME;
            }

            return [
                'is_service_command' => true,
                'service' => $serv,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        if ($first === '/ident' || $first === '/identify') {
            return self::handleIdentCommand($senderNick, $channel, $parts);
        }

        if ($first === '/whois') {
            return self::handleWhoisCommand($senderNick, $channel, $parts);
        }

        if ($first === '/who') {
            return self::handleWhoCommand($senderNick, $channel, $parts);
        }

        if ($first === '/set') {
            $argStr = trim(substr($text, strlen($parts[0])));
            if (preg_match('/^(?:§|\$)?domain\s*=\s*(.+)$/iu', $argStr, $m) || preg_match('/^(?:§|\$)?domain\s+(.+)$/iu', $argStr, $m)) {
                $domainVal = trim($m[1]);
                $res = NameServ::setDomain($senderNick, $domainVal);
                return [
                    'is_service_command' => true,
                    'service' => NameServ::SERVICE_NAME,
                    'response' => $res['message'],
                    'channel' => $channel
                ];
            }
        }

        if ($first === '/setting' || $first === '/settings') {
            $sub = strtoupper($parts[1] ?? 'LIST');
            $key = $parts[2] ?? '';
            $val = trim(implode(' ', array_slice($parts, 3)));

            if ($sub === 'SET' && !empty($key)) {
                SettingsManager::setSetting($key, $val);
                $resp = "SERVER SETTINGS: Updated '{$key}' to '{$val}' in MySQL database.";
            } elseif (!empty($key)) {
                $v = SettingsManager::getSetting($key, '(not set)');
                $resp = "SERVER SETTINGS: '{$key}' = '{$v}'";
            } else {
                $all = SettingsManager::getAllSettings();
                $lines = ["SERVERWIDE SETTINGS (MySQL Database):"];
                foreach ($all as $k => $item) {
                    $lines[] = "• {$k} = \"{$item['value']}\" ({$item['description']})";
                }
                $resp = implode("\n", $lines);
            }

            return [
                'is_service_command' => true,
                'service' => 'SERVSERV',
                'response' => $resp,
                'channel' => $channel
            ];
        }

        if ($first === '/supersilent') {
            $msgText = trim(substr($text, strlen($parts[0])));
            if ($msgText === '') {
                return [
                    'is_service_command' => true,
                    'service' => 'SERVICESERV',
                    'response' => 'Usage: /supersilent <message> — Post a message to super room only without propagating to subrooms.',
                    'channel' => $channel
                ];
            }

            \Fortress\Signaling\RoomManager::broadcastSignal($channel, $senderNick, [
                'type' => 'chat',
                'sender' => $senderNick,
                'message' => $msgText,
                'supersilent' => true
            ], false);

            return [
                'is_service_command' => true,
                'service' => 'SUPERSILENT',
                'response' => "[SUPERSILENT to {$channel}] {$msgText}",
                'channel' => $channel,
                'skip_bot_broadcast' => true
            ];
        }

        if ($first === '/help') {
            $helpMsg = "Available IRC Commands:\n" .
                       "• /subscribe [user|channel|server] [target] [plan] — Chat-based Stripe checkout subscription\n" .
                       "• /pay [user|channel|server] [target] — Generate instant Stripe payment link\n" .
                       "• /msg PAYSERV PLANS — View all Stripe subscription plans\n" .
                       "• /msg PAYSERV SUBSCRIBE <user|channel|server> [target] — Subscribe level from chat\n" .
                       "• /connect <URI> — Connect to server via URI (supports https://, ivc://, irc://)\n" .
                       "• /disconnect [server|URI] — Disconnect from active or specified server\n" .
                       "• /msg NAMESERV REGISTER <pass> [email] — Register your nickname\n" .
                       "• /msg NAMESERV IDENTIFY <pass> — Identify with your password\n" .
                       "• /msg NAMESERV SUBSCRIBE [tier] — Subscribe nickname to User Pro\n" .
                       "• /msg CHANSERV REGISTER <#channel> [passkey] — Register a channel\n" .
                       "• /msg CHANSERV SUBSCRIBE <#channel> [tier] — Subscribe channel to Channel Pro\n" .
                       "• /msg CHANSERV OP <#channel> <nick> — Grant channel OP status\n" .
                       "• /msg MEMOSERV SEND <nick> <msg> — Send memo to offline/online user\n" .
                       "• /msg MEMOSERV READ [num] / LIST — Read or list your memos\n" .
                       "• /msg HOSTSERV REQUEST <vhost> — Request or set virtual host\n" .
                       "• /msg SERVSERV LIST — List core & registered foreign services\n" .
                       "• /msg SERVSERV REGISTER <name> <host> <endpoint> — Register foreign service\n" .
                       "• /memo [SEND|READ|DEL|LIST] — MemoServ shortcut\n" .
                       "• /vhost [REQUEST|ON|OFF|INFO] — HostServ shortcut\n" .
                       "• /motd [new_motd] — View/update Message of the Day\n" .
                       "• /topic <new_topic> — Change channel topic\n" .
                       "• /theme [list|dark|light|halloween|console|christmas|custom] — Switch or manage themes\n" .
                       "• /supersilent <message> — Post a message to super room only without propagating to subrooms\n" .
                       "• /settings [SET <key> <value>] — View or update serverwide settings in MySQL\n" .
                       "• /cabpfaserv <command> — Computer Aided Best Practice Favorite Algorithm Service";

            return [
                'is_service_command' => true,
                'service' => 'SERVSERV',
                'response' => $helpMsg,
                'channel' => $channel
            ];
        }

        // Enforce channel text restrictions (+v video-only / +m moderated)
        if (str_starts_with($channel, '#') || str_starts_with($channel, '&')) {
            $chanInfo = ChanServ::getInfo($channel);
            if ($chanInfo['success']) {
                $modes = $chanInfo['data']['modes'] ?? '';
                $flags = ChanServ::parseModeFlags($modes);
                if (($flags['v'] || $flags['m']) && !ChanServ::hasVoice($channel, $senderNick)) {
                    return [
                        'is_service_command' => true,
                        'service' => 'CHANSERV',
                        'response' => "CHANSERV: Cannot send message to {$channel} - Channel is video-only (+v) / moderated (+m). You need voice (+v) or operator (+o) status to send text messages.",
                        'channel' => $channel
                    ];
                }
            }
        }

        return null;
    }

    private static function handlePayServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'PLANS':
            case 'TIERS':
            case 'LIST':
                $res = PayServ::listPlans();
                break;

            case 'SUBSCRIBE':
            case 'PAY':
                $level = $args[0] ?? 'user';
                $target = $args[1] ?? '';
                $planId = $args[2] ?? null;
                $res = PayServ::subscribe($senderNick, $level, $target, $planId);
                break;

            case 'STATUS':
            case 'INFO':
                $level = $args[0] ?? 'user';
                $target = $args[1] ?? $senderNick;
                $res = PayServ::getStatus($level, $target);
                break;

            case 'CANCEL':
                $level = $args[0] ?? 'user';
                $target = $args[1] ?? $senderNick;
                $res = PayServ::cancel($senderNick, $level, $target);
                break;

            default:
                $res = PayServ::listPlans();
                break;
        }

        return [
            'is_service_command' => true,
            'service' => PayServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    public static function handleIdentCommand(string $senderNick, string $channel, array $parts): array
    {
        $arg = trim($parts[1] ?? '');

        // If no arguments, show current ident for sender
        if ($arg === '') {
            $std = \Fortress\Database\UserNickRepository::getStandardizedUsername($senderNick);
            $userNick = \Fortress\Database\UserNickRepository::findByNickname($senderNick);
            $dom = $userNick ? $userNick->getDomain() : (str_contains($senderNick, '@') ? explode('@', $senderNick, 2)[1] : '<anonymous>');
            $base = $userNick ? $userNick->getBaseUser() : (str_contains($senderNick, '@') ? explode('@', $senderNick, 2)[0] : $senderNick);
            $isIdent = ($userNick && $userNick->isIdentified()) || str_contains($senderNick, '@') ? 'Identified' : 'Unidentified';
            return [
                'is_service_command' => true,
                'service' => NameServ::SERVICE_NAME,
                'response' => "IDENT: User '{$base}' identified from domain '{$dom}' ({$std}). Status: {$isIdent}.",
                'channel' => $channel
            ];
        }

        // Check if argument is in user@domain format
        if (str_contains($arg, '@')) {
            $parsed = \Fortress\Models\UserNick::parseIdent($arg);
            $targetUser = $parsed['user'];
            $targetDomain = $parsed['domain'];
            $res = NameServ::setDomain($targetUser, $targetDomain);
            return [
                'is_service_command' => true,
                'service' => NameServ::SERVICE_NAME,
                'response' => "IDENT: User '{$targetUser}' identified from domain '{$targetDomain}' ({$parsed['standardized']}).",
                'channel' => $channel
            ];
        }

        // Check if argument is a domain format (e.g. custom.test.com)
        if (str_contains($arg, '.') && !str_starts_with($arg, '#')) {
            $base = str_contains($senderNick, '@') ? explode('@', $senderNick, 2)[0] : $senderNick;
            $res = NameServ::setDomain($base, $arg);
            $std = "{$base}@{$arg}";
            return [
                'is_service_command' => true,
                'service' => NameServ::SERVICE_NAME,
                'response' => "IDENT: User '{$base}' identified from domain '{$arg}' ({$std}).",
                'channel' => $channel
            ];
        }

        // Check if it's password identification
        $userNick = \Fortress\Database\UserNickRepository::findByNickname($senderNick);
        if ($userNick !== null && $userNick->verifyPassword($arg)) {
            $res = NameServ::identify($senderNick, $arg);
            return [
                'is_service_command' => true,
                'service' => NameServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
        }

        // Target lookup
        $targetUser = \Fortress\Database\UserNickRepository::findByNickname($arg);
        $targetStd = \Fortress\Database\UserNickRepository::getStandardizedUsername($arg);
        $targetDom = $targetUser ? $targetUser->getDomain() : (str_contains($arg, '@') ? explode('@', $arg, 2)[1] : '<anonymous>');
        $targetBase = $targetUser ? $targetUser->getBaseUser() : (str_contains($arg, '@') ? explode('@', $arg, 2)[0] : $arg);
        return [
            'is_service_command' => true,
            'service' => NameServ::SERVICE_NAME,
            'response' => "IDENT: User '{$targetBase}' is identified from domain '{$targetDom}' ({$targetStd}).",
            'channel' => $channel
        ];
    }

    public static function handleWhoisCommand(string $senderNick, string $channel, array $parts): array
    {
        $target = trim($parts[1] ?? '');
        if ($target === '') {
            $target = $senderNick;
        }

        $userNick = \Fortress\Database\UserNickRepository::findByNickname($target);
        $parsed = \Fortress\Models\UserNick::parseIdent($target);
        $baseUser = $userNick ? $userNick->getBaseUser() : $parsed['user'];
        $domain = $userNick ? $userNick->getDomain() : $parsed['domain'];
        $stdUser = $userNick ? $userNick->getStandardizedUsername() : $parsed['standardized'];
        $isIdent = ($userNick && $userNick->isIdentified()) || str_contains($target, '@') ? 'Yes' : 'No';

        $userChannels = \Fortress\Database\ChannelUserRepository::getUserChannels($baseUser);
        $chanList = !empty($userChannels)
            ? implode(', ', array_map(fn($c) => "{$c['channel_name']} (+{$c['role']})", $userChannels))
            : ($channel !== '' ? "{$channel}" : 'None');

        $vhostInfo = HostServ::getVhostInfo($baseUser);
        $vhostStr = $vhostInfo['success'] && !empty($vhostInfo['data']['vhost']) ? $vhostInfo['data']['vhost'] : 'None';

        $resp = "WHOIS for {$target}:\n" .
                "• User: {$baseUser}\n" .
                "• Domain: {$domain}\n" .
                "• §domain: {$domain}\n" .
                "• Standardized Username: {$stdUser}\n" .
                "• Currently Identified: {$isIdent}\n" .
                "• Virtual Host: {$vhostStr}\n" .
                "• Channels: {$chanList}\n" .
                "• Server: fortress.ivc.local (IVC-IRC Network)";

        return [
            'is_service_command' => true,
            'service' => NameServ::SERVICE_NAME,
            'response' => $resp,
            'channel' => $channel
        ];
    }

    public static function handleWhoCommand(string $senderNick, string $channel, array $parts): array
    {
        $target = trim($parts[1] ?? '');
        if ($target === '') {
            $target = $channel !== '' ? $channel : '#fortress';
        }

        if (str_starts_with($target, '#') || str_starts_with($target, '&')) {
            $members = \Fortress\Database\ChannelUserRepository::getMembers($target);
            if (empty($members)) {
                $chanInfo = ChanServ::getInfo($target);
                $owner = $chanInfo['success'] ? ($chanInfo['data']['owner_nick'] ?? $senderNick) : $senderNick;
                $members = [
                    ['nickname' => $owner, 'role' => 'OP'],
                ];
                if (strtolower($owner) !== strtolower($senderNick)) {
                    $members[] = ['nickname' => $senderNick, 'role' => 'MEMBER'];
                }
            }

            $lines = ["WHO list for {$target}:"];
            foreach ($members as $m) {
                $nick = $m['nickname'];
                $role = $m['role'] ?? 'MEMBER';
                $roleTag = $role === 'OP' || $role === 'OWNER' ? '+o' : ($role === 'VOICE' ? '+v' : 'user');
                $std = \Fortress\Database\UserNickRepository::getStandardizedUsername($nick);
                $uObj = \Fortress\Database\UserNickRepository::findByNickname($nick);
                $dom = $uObj ? $uObj->getDomain() : (str_contains($nick, '@') ? explode('@', $nick, 2)[1] : '<anonymous>');
                $lines[] = "• {$nick} ({$std}) [{$roleTag}] (Domain: {$dom})";
            }

            return [
                'is_service_command' => true,
                'service' => ChanServ::SERVICE_NAME,
                'response' => implode("\n", $lines),
                'channel' => $target
            ];
        }

        // Single user WHO
        $userNick = \Fortress\Database\UserNickRepository::findByNickname($target);
        $std = \Fortress\Database\UserNickRepository::getStandardizedUsername($target);
        $dom = $userNick ? $userNick->getDomain() : (str_contains($target, '@') ? explode('@', $target, 2)[1] : '<anonymous>');
        $base = $userNick ? $userNick->getBaseUser() : (str_contains($target, '@') ? explode('@', $target, 2)[0] : $target);

        return [
            'is_service_command' => true,
            'service' => NameServ::SERVICE_NAME,
            'response' => "WHO for {$target}: {$base} is {$std} (Domain: {$dom}, §domain: {$dom})",
            'channel' => $channel
        ];
    }

    private static function handleNameServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REGISTER':
                $pass = $args[0] ?? '';
                $email = $args[1] ?? null;
                $res = NameServ::register($senderNick, $pass, $email);
                break;

            case 'IDENT':
            case 'IDENTIFY':
                $arg0 = $args[0] ?? '';
                if (str_contains($arg0, '@') || (str_contains($arg0, '.') && !str_starts_with($arg0, '#'))) {
                    return self::handleIdentCommand($senderNick, $channel, ['/ident', $arg0]);
                }
                $res = NameServ::identify($senderNick, $arg0);
                break;

            case 'SET':
                $fullArg = implode(' ', $args);
                if (preg_match('/^(?:§|\$)?domain\s*=\s*(.+)$/iu', $fullArg, $m) ||
                    preg_match('/^(?:§|\$)?domain\s+(.+)$/iu', $fullArg, $m)) {
                    $domainVal = trim($m[1]);
                    $res = NameServ::setDomain($senderNick, $domainVal);
                } elseif (!empty($args[0]) && str_contains($args[0], '=')) {
                    $kv = explode('=', $args[0], 2);
                    $k = ltrim($kv[0], '§$');
                    $v = $kv[1];
                    if (strtolower($k) === 'domain') {
                        $res = NameServ::setDomain($senderNick, $v);
                    } else {
                        $res = ['message' => "NICKSERV: Property §{$k} set to '{$v}' for user '{$senderNick}'."];
                    }
                } else {
                    $res = ['message' => "NICKSERV: Usage: /msg NICKSERV SET §domain=<custom.domain.com>"];
                }
                break;

            case 'SUBSCRIBE':
                $tier = $args[0] ?? 'nick_pro';
                $res = NameServ::subscribe($senderNick, $tier);
                break;

            case 'INFO':
                $target = $args[0] ?? $senderNick;
                $res = NameServ::getInfo($target);
                break;

            case 'WHOIS':
                return self::handleWhoisCommand($senderNick, $channel, ['/whois', $args[0] ?? $senderNick]);

            case 'WHO':
                return self::handleWhoCommand($senderNick, $channel, ['/who', $args[0] ?? $channel]);

            default:
                $res = ['message' => "NAMESERV: Unknown command '{$cmd}'. Use REGISTER, IDENTIFY, SET, SUBSCRIBE, INFO, WHOIS, or WHO."];
                break;
        }

        return [
            'is_service_command' => true,
            'service' => NameServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleCabPfaServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        $text = trim(implode(' ', array_merge([$cmd], $args)));
        $res = CabPfaServ::process($senderNick, $text);

        return [
            'is_service_command' => true,
            'service' => CabPfaServ::SERVICE_NAME,
            'response' => $res['message'],
            'success' => $res['success'] ?? false,
            'channel' => $channel
        ];
    }

    private static function handleChanServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REGISTER':
                $chan = !empty($args[0]) ? $args[0] : $channel;
                $passkey = $args[1] ?? null;
                $res = ChanServ::register($chan, $senderNick, $passkey);
                break;

            case 'SUBSCRIBE':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $tier = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? 'channel_pro');
                $res = ChanServ::subscribe($chan, $senderNick, $tier);
                break;

            case 'OP':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::op($chan, $target, $senderNick);
                break;

            case 'DEOP':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::deop($chan, $target, $senderNick);
                break;

            case 'VOICE':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::voice($chan, $target, $senderNick);
                break;

            case 'DEVOICE':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::devoice($chan, $target, $senderNick);
                break;

            case 'ADMIN':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::admin($chan, $target, $senderNick);
                break;

            case 'DEADMIN':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::deadmin($chan, $target, $senderNick);
                break;

            case 'NETADMIN':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::netadmin($chan, $target, $senderNick);
                break;

            case 'DENETADMIN':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $target = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                $res = ChanServ::denetadmin($chan, $target, $senderNick);
                break;

            case 'MODE':
            case 'MODES':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $modes = !empty($args[0]) && !str_starts_with($args[0], '#') ? $args[0] : ($args[1] ?? '');
                if (empty($modes)) {
                    $info = ChanServ::getInfo($chan);
                    $res = ['message' => $info['message']]; // Info includes modes
                } else {
                    $res = ChanServ::setModes($chan, $modes, $senderNick);
                }
                break;

            case 'TOPIC':
                $chan = !empty($args[0]) && str_starts_with($args[0], '#') ? $args[0] : $channel;
                $topic = trim(implode(' ', !empty($args[0]) && str_starts_with($args[0], '#') ? array_slice($args, 1) : $args));
                $res = ChanServ::setTopic($chan, $topic, $senderNick);
                break;

            case 'INFO':
                $chan = !empty($args[0]) ? $args[0] : $channel;
                $res = ChanServ::getInfo($chan);
                break;

            default:
                $res = ['message' => "CHANSERV: Unknown command '{$cmd}'. Use REGISTER, SUBSCRIBE, OP, DEOP, VOICE, DEVOICE, ADMIN, DEADMIN, NETADMIN, DENETADMIN, MODE, TOPIC, or INFO."];
                break;
        }

        return [
            'is_service_command' => true,
            'service' => ChanServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleMotdServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'SET':
                $newMotd = trim(implode(' ', $args));
                $res = MotdServ::setMotd($newMotd, $senderNick);
                break;

            case 'GET':
            case 'INFO':
            default:
                $res = MotdServ::getInfo();
                break;
        }

        return [
            'is_service_command' => true,
            'service' => MotdServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleMemoServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'SEND':
                $target = $args[0] ?? '';
                $msg = trim(implode(' ', array_slice($args, 1)));
                $res = MemoServ::send($senderNick, $target, $msg);
                break;

            case 'READ':
                $num = (int)($args[0] ?? 1);
                $res = MemoServ::read($senderNick, $num);
                break;

            case 'DEL':
            case 'DELETE':
                $num = (int)($args[0] ?? 1);
                $res = MemoServ::delete($senderNick, $num);
                break;

            case 'LIST':
            default:
                $res = MemoServ::listMemos($senderNick);
                break;
        }

        return [
            'is_service_command' => true,
            'service' => MemoServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleHostServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REQUEST':
            case 'SET':
            case 'OFFER':
                $vh = $args[0] ?? '';
                $res = HostServ::requestVhost($senderNick, $vh);
                break;

            case 'ON':
                $res = HostServ::setVhostStatus($senderNick, true);
                break;

            case 'OFF':
                $res = HostServ::setVhostStatus($senderNick, false);
                break;

            case 'INFO':
            default:
                $target = $args[0] ?? $senderNick;
                $res = HostServ::getVhostInfo($target);
                break;
        }

        return [
            'is_service_command' => true,
            'service' => HostServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleHelpServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        $topic = $cmd ?: ($args[0] ?? '');
        $res = HelpServ::getHelp($topic);

        return [
            'is_service_command' => true,
            'service' => HelpServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleTextServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        $text = implode(' ', $args);
        $res = TextServ::process($cmd, $text);

        return [
            'is_service_command' => true,
            'service' => TextServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleBotServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'ASSIGN':
                $target = $args[0] ?? '';
                $botNick = $args[1] ?? '';
                $serviceName = $args[2] ?? '';
                $res = BotServ::assign($target, $botNick, $serviceName, $senderNick);
                break;

            case 'UNASSIGN':
                $target = $args[0] ?? '';
                $botNick = $args[1] ?? '';
                $res = BotServ::unassign($target, $botNick, $senderNick);
                break;

            default:
                $res = ['message' => "BOTSERV: Unknown command '{$cmd}'. Use ASSIGN or UNASSIGN."];
                break;
        }

        return [
            'is_service_command' => true,
            'service' => BotServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }

    private static function handleServServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REGISTER':
                $name = $args[0] ?? '';
                $host = $args[1] ?? '';
                $endpoint = $args[2] ?? '';
                $meta = isset($args[3]) ? implode(' ', array_slice($args, 3)) : null;
                $res = ServServ::registerForeignService($name, $host, $endpoint, $meta);
                break;

            case 'INFO':
                $target = $args[0] ?? '';
                $res = ServServ::getServiceInfo($target);
                break;

            case 'COMMAND':
                $sName = $args[0] ?? '';
                $cText = implode(' ', array_slice($args, 1));
                $res = ServServ::dispatchForeignCommand($senderNick, $sName, $cText);
                break;

            case 'LIST':
            default:
                $res = ServServ::listAllServices();
                break;
        }

        return [
            'is_service_command' => true,
            'service' => ServServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }
}
