<?php

declare(strict_types=1);

namespace Fortress\IRC;

use Fortress\Services\StripeService;
use Fortress\Database\SubscriptionRepository;
use Fortress\Database\UserNickRepository;
use Fortress\Database\ChannelRepository;

/**
 * PAYSERV IRC System Bot & Payment Service
 * Handles chat-based payments and subscriptions enterable from chat on User, Channel, and Server levels.
 */
class PayServ
{
    public const SERVICE_NAME = 'PAYSERV';

    /**
     * List available subscription plans and tiers.
     */
    public static function listPlans(): array
    {
        $plans = StripeService::getPlans();
        $lines = ["PAYSERV Subscription Plans & Tiers (Stripe Powered):"];

        foreach ($plans as $id => $p) {
            $priceFormatted = sprintf('$%.2f', $p['price_cents'] / 100);
            $lines[] = "• [{$p['type']}] {$p['name']} ({$id}): {$priceFormatted}/month — {$p['description']}";
        }

        $lines[] = "\nCommands:";
        $lines[] = "• /subscribe [user|channel|server] [target] [plan_id] — Create Stripe Checkout session";
        $lines[] = "• /pay [user|channel|server] [target] — Instant payment checkout link";
        $lines[] = "• /msg PAYSERV STATUS [user|channel|server] [target] — Check active subscription status";
        $lines[] = "• /msg PAYSERV CANCEL [user|channel|server] [target] — Cancel active subscription";

        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    /**
     * Create a subscription checkout session for User, Channel, or Server level from chat.
     */
    public static function subscribe(string $senderNick, string $level = 'user', string $targetName = '', ?string $planId = null): array
    {
        $senderNick = trim($senderNick);
        $level = strtolower(trim($level));

        if ($level === 'nick' || $level === 'username') {
            $level = 'user';
        }

        if (empty($targetName)) {
<<<<<<< HEAD
            $targetName = ($level === 'user') ? $senderNick : '#lobby';
=======
            $targetName = ($level === 'user') ? $senderNick : '#';
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        }

        if ($level === 'channel' && !str_starts_with($targetName, '#') && !str_starts_with($targetName, '&')) {
            $targetName = '#' . $targetName;
        }

        if ($planId === null || $planId === '') {
            $planId = match ($level) {
                'channel' => 'channel_pro',
                'server' => 'server_vip',
                default => 'nick_pro'
            };
        }

        $res = StripeService::createCheckoutSession($level, $targetName, $planId, $senderNick);

        if ($res['success']) {
            $priceFormatted = sprintf('$%.2f', $res['plan']['price_cents'] / 100);
            $msg = "PAYSERV Stripe Checkout Generated for {$level} level ('{$targetName}'):\n" .
                   "• Plan: {$res['plan']['name']} ({$priceFormatted}/mo)\n" .
                   "• Complete payment at: {$res['checkout_url']}\n" .
                   "• Session ID: {$res['session_id']}";
            return ['success' => true, 'message' => $msg, 'data' => $res];
        }

        return ['success' => false, 'message' => "PAYSERV: Failed to create Stripe Checkout session."];
    }

    /**
     * Get active subscription status for a User, Channel, or Server.
     */
    public static function getStatus(string $level = 'user', string $targetName = ''): array
    {
        $level = strtolower(trim($level));
        if ($level === 'nick') {
            $level = 'user';
        }

        $sub = SubscriptionRepository::findActiveByTarget($level, $targetName);

        if ($sub === null) {
            // Check direct user/channel models
            if ($level === 'user') {
                $user = UserNickRepository::findByNickname($targetName);
                if ($user !== null && $user->isPremium()) {
                    $expStr = date('Y-m-d H:i:s', $user->getSubscriptionExpiresAt());
                    return ['success' => true, 'message' => "PAYSERV Status for User '{$targetName}': Active ({$user->getSubscriptionTier()}) until {$expStr}."];
                }
            } elseif ($level === 'channel') {
                $chan = ChannelRepository::findByChannelName($targetName);
                if ($chan !== null && $chan->isPremium()) {
                    $expStr = date('Y-m-d H:i:s', $chan->getSubscriptionExpiresAt());
                    return ['success' => true, 'message' => "PAYSERV Status for Channel '{$targetName}': Active ({$chan->getSubscriptionTier()}) until {$expStr}."];
                }
            }

            return ['success' => true, 'message' => "PAYSERV Status for {$level} '{$targetName}': No active subscription."];
        }

        $expStr = date('Y-m-d H:i:s', $sub->getExpiresAt());
        $msg = "PAYSERV Subscription Information:\n" .
               "• Level: {$sub->getTargetType()}\n" .
               "• Target: {$sub->getTargetName()}\n" .
               "• Subscriber: {$sub->getSubscriberNick()}\n" .
               "• Plan Tier: {$sub->getPlanId()}\n" .
               "• Status: " . strtoupper($sub->getStatus()) . "\n" .
               "• Expiration: {$expStr}";

        return ['success' => true, 'message' => $msg, 'subscription' => $sub->toArray()];
    }

    /**
     * Cancel subscription from chat.
     */
    public static function cancel(string $senderNick, string $level = 'user', string $targetName = ''): array
    {
        $level = strtolower(trim($level));
        if ($level === 'nick') {
            $level = 'user';
        }

        if (empty($targetName)) {
            $targetName = $senderNick;
        }

        $sub = SubscriptionRepository::findActiveByTarget($level, $targetName);
        if ($sub === null) {
            return ['success' => false, 'message' => "PAYSERV: No active subscription found for {$level} '{$targetName}'."];
        }

        return StripeService::cancelSubscription($sub->getId());
    }
}
