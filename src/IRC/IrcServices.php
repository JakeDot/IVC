<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * IRC Service Command Dispatcher & Parser
 * Handles interaction with NAMESERV, CHANSERV, MOTDSERV, MEMOSERV, HOSTSERV, SERVICESERV,
 * and Foreign Services operating under different hosts via IRC-style slash commands or direct messages.
 */
class IrcServices
{
    /**
     * Check if a message is an IRC service command and execute it.
     *
     * @param string $senderNick
     * @param string $channel
     * @param string $text
     * @return array{is_service_command: true, service: string, response: string, channel: string}|null
     */
    public static function processCommand(string $senderNick, string $channel, string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $text);
        $first = strtolower($parts[0] ?? '');

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

            if ($targetService === MotdServ::SERVICE_NAME) {
                return self::handleMotdServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === MemoServ::SERVICE_NAME) {
                return self::handleMemoServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === HostServ::SERVICE_NAME) {
                return self::handleHostServCommand($senderNick, $channel, $cmd, $args);
            }

            if ($targetService === ServiceServ::SERVICE_NAME) {
                return self::handleServiceServCommand($senderNick, $channel, $cmd, $args);
            }

            // Check if $targetService is a registered foreign service operating under a different host
            $foreignService = ServiceRegistry::getService($targetService);
            if ($foreignService) {
                $fullCommand = implode(' ', array_slice($parts, 2));
                $res = ServiceServ::dispatchForeignCommand($senderNick, $targetService, $fullCommand);
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

        if ($first === '/serviceserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleServiceServCommand($senderNick, $channel, $cmd, $args);
        }

        // 2. Convenience Slash Commands
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

        if ($first === '/identify') {
            $pass = $parts[1] ?? '';
            $res = NameServ::identify($senderNick, $pass);
            return [
                'is_service_command' => true,
                'service' => NameServ::SERVICE_NAME,
                'response' => $res['message'],
                'channel' => $channel
            ];
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
                'service' => 'SERVICESERV',
                'response' => $resp,
                'channel' => $channel
            ];
        }

        if ($first === '/help') {
            $helpMsg = "Available IRC Commands:\n" .
                       "• /msg NAMESERV REGISTER <pass> [email] — Register your nickname\n" .
                       "• /msg NAMESERV IDENTIFY <pass> — Identify with your password\n" .
                       "• /msg CHANSERV REGISTER <#channel> [passkey] — Register a channel\n" .
                       "• /msg CHANSERV OP <#channel> <nick> — Grant channel OP status\n" .
                       "• /msg MEMOSERV SEND <nick> <msg> — Send memo to offline/online user\n" .
                       "• /msg MEMOSERV READ [num] / LIST — Read or list your memos\n" .
                       "• /msg HOSTSERV REQUEST <vhost> — Request or set virtual host\n" .
                       "• /msg SERVICESERV LIST — List core & registered foreign services\n" .
                       "• /msg SERVICESERV REGISTER <name> <host> <endpoint> — Register foreign service\n" .
                       "• /memo [SEND|READ|DEL|LIST] — MemoServ shortcut\n" .
                       "• /vhost [REQUEST|ON|OFF|INFO] — HostServ shortcut\n" .
                       "• /motd [new_motd] — View/update Message of the Day\n" .
                       "• /topic <new_topic> — Change channel topic\n" .
                       "• /settings [SET <key> <value>] — Manage server settings";

            return [
                'is_service_command' => true,
                'service' => 'SERVICESERV',
                'response' => $helpMsg,
                'channel' => $channel
            ];
        }

        return null;
    }

    private static function handleNameServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REGISTER':
                $pass = $args[0] ?? '';
                $email = $args[1] ?? null;
                $res = NameServ::register($senderNick, $pass, $email);
                break;

            case 'IDENTIFY':
                $pass = $args[0] ?? '';
                $res = NameServ::identify($senderNick, $pass);
                break;

            case 'INFO':
                $target = $args[0] ?? $senderNick;
                $res = NameServ::getInfo($target);
                break;

            default:
                $res = ['message' => "NAMESERV: Unknown command '{$cmd}'. Use REGISTER, IDENTIFY, or INFO."];
                break;
        }

        return [
            'is_service_command' => true,
            'service' => NameServ::SERVICE_NAME,
            'response' => $res['message'],
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
                $res = ['message' => "CHANSERV: Unknown command '{$cmd}'. Use REGISTER, OP, DEOP, TOPIC, or INFO."];
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

    private static function handleServiceServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'REGISTER':
                $name = $args[0] ?? '';
                $host = $args[1] ?? '';
                $endpoint = $args[2] ?? '';
                $meta = isset($args[3]) ? implode(' ', array_slice($args, 3)) : null;
                $res = ServiceServ::registerForeignService($name, $host, $endpoint, $meta);
                break;

            case 'INFO':
                $target = $args[0] ?? '';
                $res = ServiceServ::getServiceInfo($target);
                break;

            case 'COMMAND':
                $sName = $args[0] ?? '';
                $cText = implode(' ', array_slice($args, 1));
                $res = ServiceServ::dispatchForeignCommand($senderNick, $sName, $cText);
                break;

            case 'LIST':
            default:
                $res = ServiceServ::listAllServices();
                break;
        }

        return [
            'is_service_command' => true,
            'service' => ServiceServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }
}
