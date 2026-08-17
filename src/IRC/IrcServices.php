<?php

declare(strict_types=1);

namespace Fortress\IRC;

/**
 * IRC Service Command Dispatcher & Parser
 * Handles interaction with NAMESERV, CHANSERV, MOTDSERV, and Serverwide Settings via IRC-style slash commands or direct messages.
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

            if ($targetService === QuoteServ::SERVICE_NAME) {
                return self::handleQuoteServCommand($senderNick, $channel, $cmd, $args);
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

        if ($first === '/quoteserv') {
            $cmd = strtoupper($parts[1] ?? '');
            $args = array_slice($parts, 2);
            return self::handleQuoteServCommand($senderNick, $channel, $cmd, $args);
        }

        // 2. Convenience Slash Commands
        if ($first === '/quote') {
            $quoteText = trim(substr($text, strlen($parts[0])));
            if ($quoteText === '') {
                $q = QuoteServ::getRandomQuote();
                if ($q) {
                    $resp = "QUOTESERV Quote #{$q['id']}: \"{$q['quote_text']}\" (by {$q['created_by']})";
                } else {
                    $resp = "QUOTESERV: No quotes available. Use /quote <text> to add one!";
                }
            } else {
                $res = QuoteServ::addQuote($quoteText, $senderNick);
                $resp = $res['message'];
            }
            return [
                'is_service_command' => true,
                'service' => QuoteServ::SERVICE_NAME,
                'response' => $resp,
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
                       "• /msg NAMESERV REGISTER <pass> [email] — Register your current nickname\n" .
                       "• /msg NAMESERV IDENTIFY <pass> — Identify with your password\n" .
                       "• /msg NAMESERV INFO [nick] — View nickname registration info\n" .
                       "• /msg CHANSERV REGISTER <#channel> [passkey] — Register a new channel\n" .
                       "• /msg CHANSERV OP <#channel> <nick> — Grant channel operator status\n" .
                       "• /msg CHANSERV DEOP <#channel> <nick> — Remove channel operator status\n" .
                       "• /msg MOTDSERV SET <new_motd> — Update serverwide Message of the Day\n" .
                       "• /msg QUOTESERV SUB / UNSUB — Subscribe or unsubscribe to periodic quotes\n" .
                       "• /msg QUOTESERV ADD <text> | EDIT <id> <text> | DEL <id> — Quote management\n" .
                       "• /quote [text] — Add a new quote or view a random quote\n" .
                       "• /motd [new_motd] — View or update server Message of the Day\n" .
                       "• /topic <new_topic> — Change channel topic\n" .
                       "• /settings [SET <key> <value>] — View or update serverwide settings in MySQL";

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

    private static function handleQuoteServCommand(string $senderNick, string $channel, string $cmd, array $args): array
    {
        switch ($cmd) {
            case 'SUB':
            case 'SUBSCRIBE':
                $res = QuoteServ::subscribe($senderNick);
                break;

            case 'UNSUB':
            case 'UNSUBSCRIBE':
                $res = QuoteServ::unsubscribe($senderNick);
                break;

            case 'ADD':
            case 'CREATE':
                $quoteText = trim(implode(' ', $args));
                $res = QuoteServ::addQuote($quoteText, $senderNick);
                break;

            case 'EDIT':
                $id = isset($args[0]) ? (int)$args[0] : 0;
                $newText = trim(implode(' ', array_slice($args, 1)));
                $res = QuoteServ::editQuote($id, $newText, $senderNick);
                break;

            case 'DEL':
            case 'DELETE':
            case 'REMOVE':
                $id = isset($args[0]) ? (int)$args[0] : 0;
                $res = QuoteServ::deleteQuote($id, $senderNick);
                break;

            case 'GET':
            case 'SHOW':
                $id = isset($args[0]) ? (int)$args[0] : 0;
                $q = QuoteServ::getQuote($id);
                if ($q) {
                    $res = ['message' => "QUOTESERV Quote #{$q['id']}: \"{$q['quote_text']}\" (by {$q['created_by']})"];
                } else {
                    $res = ['message' => "QUOTESERV: Quote #{$id} not found."];
                }
                break;

            case 'RANDOM':
            case 'RAND':
                $q = QuoteServ::getRandomQuote();
                if ($q) {
                    $res = ['message' => "QUOTESERV Quote #{$q['id']}: \"{$q['quote_text']}\" (by {$q['created_by']})"];
                } else {
                    $res = ['message' => "QUOTESERV: No quotes available in database."];
                }
                break;

            default:
                $res = ['message' => "QUOTESERV: Unknown command '{$cmd}'. Use SUB, UNSUB, ADD <text>, EDIT <id> <text>, DEL <id>, or RANDOM."];
                break;
        }

        return [
            'is_service_command' => true,
            'service' => QuoteServ::SERVICE_NAME,
            'response' => $res['message'],
            'channel' => $channel
        ];
    }
}
