<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\ChannelRepository;
use Fortress\Database\ChannelUserRepository;
use Fortress\Models\Channel;
use Fortress\Models\ChannelUser;

/**
 * CHANSERV (Channel Service) IRC System Bot
 * Handles channel registration, operator management, topic control, passkeys, modes, and paid channel subscriptions.
 */
class ChanServ
{
    public const SERVICE_NAME = 'CHANSERV';

    /**
     * Normalize channel name (ensure leading #)
     */
    public static function normalizeChannelName(string $channel): string
    {
        $channel = trim($channel);
        if ($channel === '') {
            return '';
        }
        if (!str_starts_with($channel, '#') && !str_starts_with($channel, '&')) {
            $channel = '#' . $channel;
        }
        return $channel;
    }

    /**
     * Register a channel
     */
    public static function register(string $channel, string $ownerNick, ?string $passkey = null): array
    {
        $channel = self::normalizeChannelName($channel);
        $ownerNick = trim($ownerNick);

        if (empty($channel) || empty($ownerNick)) {
            return ['success' => false, 'message' => 'CHANSERV: Valid channel name and owner nickname are required.'];
        }

        if (ChannelRepository::exists($channel)) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is already registered."];
        }

        $hashedPasskey = ($passkey !== null && $passkey !== '') ? password_hash($passkey, PASSWORD_DEFAULT) : null;
        $chanModel = new Channel($channel, $ownerNick, null, $hashedPasskey, '+t', time());
        $success = ChannelRepository::save($chanModel);

        if ($success) {
            // Assign OP role to channel owner
            self::setRole($channel, $ownerNick, 'OP');
            return ['success' => true, 'message' => "CHANSERV: Channel '{$channel}' successfully registered to owner '{$ownerNick}'."];
        }

        return ['success' => false, 'message' => 'CHANSERV: Channel registration failed.'];
    }

    /**
     * Subscribe channel to Channel Pro tier from chat
     */
    public static function subscribe(string $channel, string $ownerNick, string $planTier = 'channel_pro'): array
    {
        $channel = self::normalizeChannelName($channel);
        if (!self::isRegistered($channel)) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' must be registered before subscribing."];
        }

        return PayServ::subscribe($ownerNick, 'channel', $channel, $planTier);
    }

    /**
     * Assign OP role to user in channel
     */
    public static function op(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant OP status."];
        }

        self::setRole($channel, $targetNick, 'OP');
        return ['success' => true, 'message' => "CHANSERV: Granted OP status (+o) to '{$targetNick}' in {$channel}."];
    }

    /**
     * Remove OP role from user in channel
     */
    public static function deop(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove OP status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed OP status (-o) from '{$targetNick}' in {$channel}."];
    }

    /**
     * Set topic for channel
     */
    public static function setTopic(string $channel, string $topic, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);

        if (!empty($requesterNick) && self::isRegistered($channel) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. Only channel operators can set the topic for {$channel}."];
        }

        if (self::isRegistered($channel)) {
            ChannelRepository::updateTopic($channel, $topic);
        }

        return ['success' => true, 'message' => "CHANSERV: Topic for {$channel} updated to: \"{$topic}\"", 'topic' => $topic];
    }

    /**
     * Set modes for a channel
     */
    public static function setModes(string $channel, string $modes, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);

        if (!empty($requesterNick) && self::isRegistered($channel) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. Only channel operators can set modes for {$channel}."];
        }

        if (self::isRegistered($channel)) {
            $chanModel = ChannelRepository::findByChannelName($channel);
            if ($chanModel) {
                $currentModes = $chanModel->getModes();

                // Check for +Δmodes / +deltamodes / +raw special modes
                if (str_contains($modes, 'Δmodes') || str_contains($modes, 'deltamodes')) {
                    if (!str_contains($currentModes, 'Δmodes')) {
                        $currentModes .= 'Δmodes';
                    }
                }
                if (str_contains($modes, '-Δmodes') || str_contains($modes, '-deltamodes')) {
                    $currentModes = str_replace('Δmodes', '', $currentModes);
                }

                if (str_contains($modes, '+raw')) {
                    if (!str_contains($currentModes, 'raw')) {
                        $currentModes .= 'raw';
                    }
                }
                if (str_contains($modes, '-raw')) {
                    $currentModes = str_replace('raw', '', $currentModes);
                }

                $add = true;
                $chars = mb_str_split($modes);
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

                // Clean up + prefix formatting
                $cleanModes = str_replace('+', '', $currentModes);
                if (!empty($cleanModes)) {
                    $currentModes = '+' . $cleanModes;
                } else {
                    $currentModes = '';
                }

                ChannelRepository::updateModes($channel, $currentModes);
                $modes = $currentModes;
            }
        }

        return ['success' => true, 'message' => "CHANSERV: Modes for {$channel} updated to {$modes}.", 'modes' => $modes];
    }

    /**
     * Parse mode string into structured mode flags array supporting additions and removals (e.g. +mo-des)
     *
     * @param string $modeStr
     * @return array{n: bool, N: bool, S: bool, s: bool, k: bool, v: bool, o: bool, a: bool, m: bool, d: bool, e: bool, t: bool, no_t: bool, raw: bool, delta_modes: bool}
     */
    public static function parseModeFlags(string $modeStr): array
    {
        $add = true;
        $activeModes = [];
        $chars = mb_str_split($modeStr);

        for ($i = 0; $i < count($chars); $i++) {
            $c = $chars[$i];
            if ($c === '+') {
                $add = true;
            } elseif ($c === '-') {
                $add = false;
            } else {
                if ($add) {
                    $activeModes[$c] = true;
                } else {
                    unset($activeModes[$c]);
                }
            }
        }

        $flags = [
            'n' => isset($activeModes['n']),
            'N' => isset($activeModes['N']),
            'S' => isset($activeModes['S']),
            's' => isset($activeModes['s']),
            'k' => isset($activeModes['k']),
            'v' => isset($activeModes['v']),
            'o' => isset($activeModes['o']),
            'a' => isset($activeModes['a']),
            'm' => isset($activeModes['m']),
            'd' => isset($activeModes['d']),
            'e' => isset($activeModes['e']),
            't' => isset($activeModes['t']),
            'no_t' => str_contains($modeStr, '-t'),
            'raw' => str_contains($modeStr, 'raw'),
            'delta_modes' => str_contains($modeStr, 'Δmodes') || str_contains($modeStr, 'deltamodes') || str_contains($modeStr, 'Δ'),
        ];

        return $flags;
    }

    /**
     * Parse target, §prop section subobjects, and attached mode suffixes (e.g. #channel+Δmodes, @object§prop=val+mo-des)
     *
     * @param string $target
     * @return array{base_target: string, sub_object: string|null, prop: string|null, prop_value: string|null, raw_target: string, modes: string, mode_flags: array, object_notation: string|null, ivc_uri: string|null}
     */
    public static function parseTargetAndModes(string $target): array
    {
        $target = trim($target);
        $baseTarget = $target;
        $modes = '';

        if (($pos = strpos($target, '+')) !== false) {
            $baseTarget = substr($target, 0, $pos);
            $modes = substr($target, $pos);
        } elseif (($pos = strpos($target, '-')) !== false) {
            $baseTarget = substr($target, 0, $pos);
            $modes = substr($target, $pos);
        }

        $prop = null;
        $propValue = null;
        $subObject = null;
        $objectNotation = null;
        $ivcUri = null;

        // Check for §prop or §prop=val property subobjects
        if (($secPos = strpos($baseTarget, '§')) !== false) {
            $subObject = substr($baseTarget, $secPos);
            $propRaw = substr($baseTarget, $secPos + strlen('§'));
            $baseTarget = substr($baseTarget, 0, $secPos);

            if (($eqPos = strpos($propRaw, '=')) !== false) {
                $prop = substr($propRaw, 0, $eqPos);
                $propValue = substr($propRaw, $eqPos + 1);
            } else {
                $prop = $propRaw;
                $propValue = 'true';
            }

            $cleanObj = ltrim($baseTarget, '#@&£$');
            $objectNotation = "{" . $cleanObj . " " . $prop . ":" . $propValue . "}";
            $ivcUri = "ivc://\$me/" . $cleanObj . "§" . $prop . "=" . $propValue;
        }

        $flags = self::parseModeFlags($modes);

        return [
            'base_target' => $baseTarget,
            'sub_object' => $subObject,
            'prop' => $prop,
            'prop_value' => $propValue,
            'raw_target' => $target,
            'modes' => $modes,
            'mode_flags' => $flags,
            'object_notation' => $objectNotation,
            'ivc_uri' => $ivcUri
        ];
    }

    /**
     * Assign VOICE role to user in channel
     */
    public static function voice(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to grant VOICE status."];
        }

        if (self::isOp($channel, $targetNick)) {
            return ['success' => false, 'message' => "CHANSERV: Target user is already an OP."];
        }

        self::setRole($channel, $targetNick, 'VOICE');
        return ['success' => true, 'message' => "CHANSERV: Granted VOICE status (+v) to '{$targetNick}' in {$channel}."];
    }

    /**
     * Remove VOICE role from user in channel
     */
    public static function devoice(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        $channel = self::normalizeChannelName($channel);
        $targetNick = trim($targetNick);

        if (!empty($requesterNick) && !self::isOp($channel, $requesterNick)) {
            return ['success' => false, 'message' => "CHANSERV: Permission denied. You must be an OP on {$channel} to remove VOICE status."];
        }

        self::setRole($channel, $targetNick, 'MEMBER');
        return ['success' => true, 'message' => "CHANSERV: Removed VOICE status (-v) from '{$targetNick}' in {$channel}."];
    }

    public static function getInfo(string $channel): array
    {
        $channel = self::normalizeChannelName($channel);
        $chanModel = ChannelRepository::findByChannelName($channel);

        if ($chanModel === null) {
            return ['success' => false, 'message' => "CHANSERV: Channel '{$channel}' is not registered."];
        }

        $ops = self::getOperators($channel);
        $opsList = !empty($ops) ? implode(', ', $ops) : 'None';
        $topicStr = $chanModel->getTopic() ?? '(No topic set)';
        $regDate = date('Y-m-d H:i:s', $chanModel->getRegisteredAt());
        $subStr = $chanModel->isPremium() ? "🚀 Active ({$chanModel->getSubscriptionTier()})" : 'None (Standard)';

        $msg = "CHANSERV Info for {$chanModel->getChannelName()}:\n" .
               "• Owner: {$chanModel->getOwnerNick()}\n" .
               "• Registered: {$regDate}\n" .
               "• Modes: {$chanModel->getModes()}\n" .
               "• Topic: {$topicStr}\n" .
               "• Operators: {$opsList}\n" .
               "• Subscription Status: {$subStr}";

        return ['success' => true, 'message' => $msg, 'data' => $chanModel->toArray()];
    }

    /**
     * Check if channel is registered
     */
    public static function isRegistered(string $channel): bool
    {
        $channel = self::normalizeChannelName($channel);
        return ChannelRepository::exists($channel);
    }

    /**
     * Set user role in channel
     */
    public static function setRole(string $channel, string $nickname, string $role): void
    {
        $channel = self::normalizeChannelName($channel);
        $channelUser = new ChannelUser($channel, $nickname, $role);
        ChannelUserRepository::saveRole($channelUser);
    }

    /**
     * Check if user is OP in channel
     */
    public static function isOp(string $channel, string $nickname): bool
    {
        $channel = self::normalizeChannelName($channel);
        return ChannelUserRepository::isOp($channel, $nickname);
    }

    /**
     * Get list of operators in a channel
     *
     * @return array<int, string>
     */
    public static function getOperators(string $channel): array
    {
        $channel = self::normalizeChannelName($channel);
        return ChannelUserRepository::getOperators($channel);
    }

    /**
     * List registered channels
     *
     * @return array<int, array{channel_name: string, owner_nick: string, topic: string|null, registered_at: int, subscription_tier: string|null, is_premium: int}>
     */
    public static function listChannels(): array
    {
        $channels = ChannelRepository::findAll();
        $list = [];

        foreach ($channels as $c) {
            if (str_contains($c->getModes(), 's')) {
                continue;
            }
            $list[] = [
                'channel_name' => $c->getChannelName(),
                'owner_nick' => $c->getOwnerNick(),
                'topic' => $c->getTopic(),
                'registered_at' => $c->getRegisteredAt(),
                'subscription_tier' => $c->getSubscriptionTier(),
                'is_premium' => $c->isPremium() ? 1 : 0
            ];
        }

        return $list;
    }
}
