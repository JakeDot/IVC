<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Database\ObjectReactionRepository;
use Fortress\Models\ObjectReaction;

/**
 * REACTSERV / REACTIONSERV (Object Reaction Service)
 * Handles applying and querying emoji reactions on addressable IVC objects (chat comments, etc.).
 */
class ReactionServ
{
    public const SERVICE_NAME = 'REACTSERV';
    public const ALIAS_SERVICE_NAME = 'REACTIONSERV';
    public const DELTA_SYMBOL = 'Δ';
    public const DELTA_SYMBOL_ALT = '∆';
    public const SECTION_SYMBOL = '§';
    public const DELTA_SYMBOLS = ['Δ', '∆'];

    /**
     * Map of common text alias keywords to emojis.
     */
    private const EMOJI_MAP = [
        'HEART' => '❤️',
        ':HEART:' => '❤️',
        '<3' => '❤️',
        'LIKE' => '👍',
        ':LIKE:' => '👍',
        '+1' => '👍',
        'DISLIKE' => '👎',
        ':DISLIKE:' => '👎',
        '-1' => '👎',
        'FIRE' => '🔥',
        ':FIRE:' => '🔥',
        'PARTY' => '🎉',
        'TADA' => '🎉',
        'ROCKET' => '🚀',
        'CLAP' => '👏',
        'LAUGH' => '😂',
        'SMILE' => '😊',
        'STAR' => '⭐',
        'EYES' => '👀'
    ];

    /**
     * Normalize an emoji string or text alias to standard Unicode emoji.
     */
    public static function normalizeEmoji(string $emoji): string
    {
        $trimmed = trim($emoji);
        $upper = strtoupper($trimmed);

        if (isset(self::EMOJI_MAP[$upper])) {
            return self::EMOJI_MAP[$upper];
        }

        return $trimmed;
    }

    /**
     * Check if a string is a recognized emoji or emoji alias keyword.
     */
    public static function isEmoji(string $str): bool
    {
        $trimmed = trim($str);
        if ($trimmed === '') {
            return false;
        }
        if (isset(self::EMOJI_MAP[strtoupper($trimmed)])) {
            return true;
        }
        return (bool)preg_match('/^[\p{Extended_Pictographic}\p{Emoji_Presentation}\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}]+$/u', $trimmed);
    }

    /**
     * Apply a reaction to an addressable IVC object.
     *
     * @param string $objectUri
     * @param string $emoji
     * @param string $senderNick
     * @return array
     */
    public static function react(string $objectUri, string $emoji, string $senderNick): array
    {
        $objectUri = trim($objectUri);
        $senderNick = trim($senderNick);
        $emoji = self::normalizeEmoji($emoji);

        if (empty($objectUri)) {
            return [
                'success' => false,
                'message' => 'REACTSERV: Target object URI is required. Usage: HEART|<emoji> ivc://object/:id'
            ];
        }

        if (empty($emoji)) {
            $emoji = '❤️';
        }

        if (empty($senderNick)) {
            $senderNick = 'anonymous';
        }

        $reactionModel = new ObjectReaction($objectUri, $emoji, $senderNick, time());
        ObjectReactionRepository::save($reactionModel);

        $summary = ObjectReactionRepository::getAggregatedReactions($objectUri);
        $emojiCount = $summary['reactions'][$emoji]['count'] ?? 1;
        $totalCount = $summary['total_count'];

        $reactionsUri = self::formatReactionsUri($objectUri, $summary['reactions']);

        return [
            'success' => true,
            'service' => self::SERVICE_NAME,
            'message' => "REACTSERV: Added {$emoji} reaction to {$objectUri} (Total: {$totalCount})",
            'reaction' => $emoji,
            'object' => $objectUri,
            'reactions_uri' => $reactionsUri,
            'summary' => $summary,
            'data' => [
                'object' => $objectUri,
                'reaction' => $emoji,
                'sender' => $senderNick,
                'emoji_count' => $emojiCount,
                'total_count' => $totalCount,
                'reactions' => $summary['reactions'],
                'reactions_uri' => $reactionsUri
            ]
        ];
    }

    /**
     * Get aggregated reaction statistics for an object URI.
     */
    public static function getReactions(string $objectUri): array
    {
        $objectUri = trim($objectUri);
        $summary = ObjectReactionRepository::getAggregatedReactions($objectUri);
        $summary['reactions_uri'] = self::formatReactionsUri($objectUri, $summary['reactions']);
        return $summary;
    }

    /**
     * Format the addressable metadata URI for reactions (e.g. ivc://object/:idΔreactions).
     */
    public static function formatReactionsUri(string $objectUri, ?array $reactions = null): string
    {
        $objectUri = trim($objectUri);
        
        // If already containing delta reactions, strip it
        if (str_contains($objectUri, 'Δreactions') || str_contains($objectUri, '∆reactions')) {
            return $objectUri;
        }

        // Handle stripping trailing slashes before appending delta
        $base = rtrim($objectUri, '/');
        
        if ($reactions !== null && !empty($reactions)) {
            // Encode compact reactions summary if provided e.g. ivc://object/:idΔreactions={"❤️":2}
            $counts = [];
            foreach ($reactions as $em => $info) {
                $counts[$em] = is_array($info) ? ($info['count'] ?? 1) : (int)$info;
            }
            $payload = json_encode($counts, JSON_UNESCAPED_UNICODE);
            return "{$base}" . self::DELTA_SYMBOL . "reactions={$payload}";
        }

        return "{$base}" . self::DELTA_SYMBOL . "reactions";
    }

    /**
     * Get extended comment redirect URI with compact reaction representation:
     * e.g. ivc://object/:idΔreactions={"❤️":2,"🔥":1} or ivc://:comment-idΔreactions={"❤️":2}
     */
    public static function getRedirectUri(string $objectUri, ?array $reactions = null): string
    {
        $objectUri = trim($objectUri);
        if ($reactions === null) {
            $summary = ObjectReactionRepository::getAggregatedReactions($objectUri);
            $reactions = $summary['reactions'];
        }
        return self::formatReactionsUri($objectUri, $reactions);
    }

    /**
     * Handle HTTP PUT reaction request to ivc://objectΔreactions/<emoji>.
     *
     * @param string $uri
     * @param string $senderNick
     * @return array{count: int, redirect: string, redirect_uri: string, reactions_uri: string}
     */
    public static function handleHttpReaction(string $uri, string $senderNick = 'anonymous'): array
    {
        $uri = trim(urldecode($uri));
        $uri = ltrim($uri, '/');

        if (preg_match('/^(.*?)[Δ∆]reactions\/(.+)$/u', $uri, $m)) {
            $objectUri = trim($m[1]);
            $emojiRaw = trim($m[2]);
            if (str_contains($emojiRaw, '?')) {
                $emojiRaw = explode('?', $emojiRaw)[0];
            }
            $emoji = self::normalizeEmoji($emojiRaw);
            $res = self::react($objectUri, $emoji, $senderNick);
            $count = $res['data']['emoji_count'] ?? 1;
            $redirectUri = self::getRedirectUri($objectUri, $res['data']['reactions'] ?? null);
            return [
                'count' => $count,
                'redirect' => $redirectUri,
                'redirect_uri' => $redirectUri,
                'reactions_uri' => $redirectUri
            ];
        }

        return ['count' => 0, 'redirect' => '', 'redirect_uri' => '', 'reactions_uri' => ''];
    }
}

