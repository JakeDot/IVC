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
        return Channel::register($channel, $ownerNick, $passkey);
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
        return Channel::op($channel, $targetNick, $requesterNick);
    }

    /**
     * Remove OP role from user in channel
     */
    public static function deop(string $channel, string $targetNick, string $requesterNick = ''): array
    {
        return Channel::deop($channel, $targetNick, $requesterNick);
    }

    /**
     * Set topic for channel
     */
    public static function setTopic(string $channel, string $topic, string $requesterNick = ''): array
    {
        return Channel::setTopicCommand($channel, $topic, $requesterNick);
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
                $hasDeltaModes = str_contains($modes, 'Δ');
                $cleanModes = str_replace(['Δmodes', '∆modes', 'deltamodes'], '', $modes);
                $add = true;
                $mbChars = preg_split('//u', $cleanModes, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                for ($i = 0; $i < count($mbChars); $i++) {
                    $char = $mbChars[$i];

                // Allow specific multibyte/string modes
                $specialModes = ['Δmodes', 'deltamodes', 'raw'];

                $add = true;

                // Handle special modes first
                foreach ($specialModes as $sm) {
                    if (str_contains($modes, "+$sm")) {
                        if (!str_contains($currentModes, $sm)) {
                            $currentModes .= $sm;
                        }
                        $modes = str_replace("+$sm", "", $modes);
                    }
                    if (str_contains($modes, "-$sm")) {
                        $currentModes = str_replace($sm, '', $currentModes);
                        $modes = str_replace("-$sm", "", $modes);
                    }
                }

                // Handle single character modes
                for ($i = 0; $i < mb_strlen($modes); $i++) {
                    $char = mb_substr($modes, $i, 1);
                    if ($char === '+') {
                        $add = true;
                    } elseif ($char === '-') {
                        $add = false;
                    } elseif (preg_match('/[a-zA-ZΔ]/u', $char)) {
                        if ($add && !str_contains($currentModes, $char)) {
                            $currentModes .= $char;
                        } elseif (!$add && str_contains($currentModes, $char)) {
                            $currentModes = str_replace($char, '', $currentModes);
                        }
                    }
                }
                if ($hasDeltaModes && !str_contains($currentModes, 'Δmodes')) {
                    $currentModes .= 'Δmodes';
                }
                // Ensure there is a + at the start if not empty, otherwise default to +t
                if (!empty($currentModes) && !str_starts_with($currentModes, '+')) {
                    $currentModes = '+' . $currentModes;
                }

                $currentModes = str_replace('+', '', $currentModes);
                $currentModes = '+' . $currentModes;

                if ($currentModes === '+') $currentModes = '';

                ChannelRepository::updateModes($channel, $currentModes);
                $modes = $currentModes;
            }
        }

        return ['success' => true, 'message' => "CHANSERV: Modes for {$channel} updated to {$modes}.", 'modes' => $modes];
    }

    /**
     * Parse mode string into structured mode flags array
     *
     * @param string $modeStr
     * @return array{n: bool, N: bool, S: bool, s: bool, k: bool, v: bool, o: bool, a: bool, m: bool, t: bool, no_t: bool, raw: bool, delta_modes: bool}
     */
    public static function parseModeFlags(string $modeStr): array
    {
        $flags = [
            'n' => str_contains($modeStr, 'n'),
            'N' => str_contains($modeStr, 'N'),
            'S' => str_contains($modeStr, 'S'),
            's' => str_contains($modeStr, 's'),
            'k' => str_contains($modeStr, 'k'),
            'v' => str_contains($modeStr, 'v'),
            'o' => str_contains($modeStr, 'o'),
            'a' => str_contains($modeStr, 'a'),
            'm' => str_contains($modeStr, 'm'),
            'e' => str_contains($modeStr, 'e'),
            'd' => str_contains($modeStr, 'd'),
            't' => str_contains($modeStr, 't') && !str_contains($modeStr, '-t'),
            'no_t' => str_contains($modeStr, '-t'),
            'raw' => str_contains($modeStr, 'raw'),
            'delta_modes' => str_contains($modeStr, 'Δmodes') || str_contains($modeStr, 'deltamodes') || str_contains($modeStr, 'Δ'),
        ];

        return $flags;
    }

    /**
     * Parse target and attached mode suffixes (e.g. #channel+Δmodes, @object+bookmarks, #room+v+t)
     *
     * @param string $target
     * @return array{base_target: string, raw_target: string, modes: string, mode_flags: array}
     */
    public static function parseTargetAndModes(string $target): array
    {
        $target = trim($target);
        $subParsed = IrcServices::parseSubobjects($target);

        $baseTarget = $subParsed['base_target'];
        $modes = '';

        if (($pos = strpos($baseTarget, '+')) !== false) {
            $modes = substr($baseTarget, $pos);
            $baseTarget = substr($baseTarget, 0, $pos);
        } elseif (($pos = strpos($baseTarget, '-')) !== false) {
            $modes = substr($baseTarget, $pos);
            $baseTarget = substr($baseTarget, 0, $pos);
        }

        $flags = self::parseModeFlags($modes);

        return [
            'base_target' => $baseTarget,
            'raw_target' => $target,
            'modes' => $modes,
            'mode_flags' => $flags,
            'subobjects' => $subParsed['subobjects'],
            'props' => $subParsed['props'],
            'events' => $subParsed['events']
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
        return Channel::isRegistered($channel);
    }

    /**
     * Set user role in channel
     */
    public static function setRole(string $channel, string $nickname, string $role): void
    {
        Channel::setRole($channel, $nickname, $role);
    }

    /**
     * Check if user is OP in channel
     */
    public static function isOp(string $channel, string $nickname): bool
    {
        return Channel::isOp($channel, $nickname);
    }

    /**
     * Get list of operators in a channel
     *
     * @return array<int, string>
     */
    public static function getOperators(string $channel): array
    {
        return Channel::getOperators($channel);
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
