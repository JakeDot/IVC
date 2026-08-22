<?php

declare(strict_types=1);

namespace Fortress\Services;

use Fortress\Database\SubscriptionRepository;
use Fortress\Database\UserNickRepository;
use Fortress\Database\ChannelRepository;
use Fortress\IRC\SettingsManager;
use Fortress\IRC\MemoServ;
use Fortress\Models\Subscription;

/**
 * Service wrapper for Stripe Payment Gateway integration.
 * Communicates with Stripe REST API via standard PHP cURL with fallback for testing/mocking.
 */
class StripeService
{
    /**
     * Get defined subscription plan tiers for users, channels, and servers.
     *
     * @return array<string, array{name: string, type: string, price_cents: int, currency: string, description: string}>
     */
    public static function getPlans(): array
    {
        return [
            'nick_pro' => [
                'name' => 'User Nick Pro',
                'type' => 'user',
                'price_cents' => 499,
                'currency' => 'usd',
                'description' => 'User Level: Nickname protection immunity, VHost access & ⭐ Premium badge'
            ],
            'channel_pro' => [
                'name' => 'Channel Pro',
                'type' => 'channel',
                'price_cents' => 999,
                'currency' => 'usd',
                'description' => 'Channel Level: Channel ownership reservation, subrooms & custom BotServ assignment'
            ],
            'server_vip' => [
                'name' => 'Server VIP Sponsor',
                'type' => 'server',
                'price_cents' => 2999,
                'currency' => 'usd',
                'description' => 'Server Level: Serverwide sponsor recognition, global privileges & MOTD priority'
            ]
        ];
    }

    public static function getSecretKey(): string
    {
        return $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?: SettingsManager::getSetting('stripe_secret_key', 'sk_test_sample');
    }

    public static function getWebhookSecret(): string
    {
        return $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: SettingsManager::getSetting('stripe_webhook_secret', 'whsec_sample');
    }

    public static function getPublishableKey(): string
    {
        return $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? getenv('STRIPE_PUBLISHABLE_KEY') ?: SettingsManager::getSetting('stripe_publishable_key', 'pk_test_sample');
    }

    /**
     * Create a Stripe Checkout Session for subscription at User, Channel, or Server level.
     *
     * @return array{success: bool, session_id: string, checkout_url: string, subscription_id: string, message?: string}
     */
    public static function createCheckoutSession(
        string $targetType,
        string $targetName,
        string $planId,
        string $subscriberNick,
        string $successUrl = 'http://localhost/api/stripe.php?action=success',
        string $cancelUrl = 'http://localhost/api/stripe.php?action=cancel'
    ): array {
        $plans = self::getPlans();
        $targetType = strtolower(trim($targetType));
        if ($targetType === 'nick') {
            $targetType = 'user';
        }

        if (!isset($plans[$planId])) {
            // Find plan matching targetType if invalid planId passed
            foreach ($plans as $pKey => $pData) {
                if ($pData['type'] === $targetType) {
                    $planId = $pKey;
                    break;
                }
            }
        }

        $plan = $plans[$planId] ?? $plans['nick_pro'];
        $secretKey = self::getSecretKey();
        $sessionId = 'cs_' . bin2hex(random_bytes(12));
        $checkoutUrl = "https://checkout.stripe.com/pay/{$sessionId}";

        // Attempt live Stripe API call if valid non-mock API key is provided
        if (str_starts_with($secretKey, 'sk_live_') || str_starts_with($secretKey, 'sk_test_') && $secretKey !== 'sk_test_sample') {
            $postData = http_build_query([
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $plan['currency'],
                            'product_data' => [
                                'name' => "IVC Sub: {$plan['name']} ({$targetName})",
                                'description' => $plan['description']
                            ],
                            'unit_amount' => $plan['price_cents'],
                            'recurring' => ['interval' => 'month']
                        ],
                        'quantity' => 1
                    ]
                ],
                'client_reference_id' => $subscriberNick,
                'metadata' => [
                    'target_type' => $targetType,
                    'target_name' => $targetName,
                    'subscriber_nick' => $subscriberNick,
                    'plan_id' => $planId
                ],
                'success_url' => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl
            ]);

            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_USERPWD => $secretKey . ':',
                    CURLOPT_TIMEOUT => 5
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response !== false && $httpCode === 200) {
                    $resData = json_decode((string)$response, true);
                    if (isset($resData['id'], $resData['url'])) {
                        $sessionId = $resData['id'];
                        $checkoutUrl = $resData['url'];
                    }
                }
            }
        }

        // Create pending subscription record in local database
        $subModel = new Subscription(
            $targetType,
            $targetName,
            $subscriberNick,
            $planId,
            'cus_pending',
            'sub_pending_' . bin2hex(random_bytes(6)),
            $sessionId,
            'incomplete',
            $plan['price_cents'],
            $plan['currency'],
            time() + 30 * 86400
        );
        SubscriptionRepository::save($subModel);

        return [
            'success' => true,
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'subscription_id' => $subModel->getId(),
            'plan' => $plan
        ];
    }

    /**
     * Cancel an active subscription in Stripe and local DB.
     */
    public static function cancelSubscription(string $subscriptionId): array
    {
        $sub = SubscriptionRepository::findById($subscriptionId);
        if ($sub === null) {
            $sub = SubscriptionRepository::findByStripeSubscriptionId($subscriptionId);
        }

        if ($sub === null) {
            return ['success' => false, 'message' => "Subscription '{$subscriptionId}' not found."];
        }

        $stripeSubId = $sub->getStripeSubscriptionId();
        $secretKey = self::getSecretKey();

        if ($stripeSubId && (str_starts_with($secretKey, 'sk_live_') || str_starts_with($secretKey, 'sk_test_') && $secretKey !== 'sk_test_sample')) {
            $ch = curl_init("https://api.stripe.com/v1/subscriptions/{$stripeSubId}");
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'DELETE',
                    CURLOPT_USERPWD => $secretKey . ':',
                    CURLOPT_TIMEOUT => 5
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        }

        SubscriptionRepository::updateStatus($sub->getId(), 'canceled', time());

        // Reset status in user/channel repository
        if ($sub->getTargetType() === 'user' || $sub->getTargetType() === 'nick') {
            UserNickRepository::updateSubscription($sub->getTargetName(), null, 'canceled', time());
        } elseif ($sub->getTargetType() === 'channel') {
            ChannelRepository::updateSubscription($sub->getTargetName(), null, 'canceled', time());
        }

        MemoServ::send(
            'PAYSERV',
            $sub->getSubscriberNick(),
            "Your {$sub->getPlanId()} subscription for {$sub->getTargetName()} has been canceled."
        );

        return ['success' => true, 'message' => "Subscription for '{$sub->getTargetName()}' successfully canceled."];
    }

    /**
     * Verify Stripe Webhook HMAC SHA256 Signature.
     */
    public static function verifyWebhookSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if (empty($sigHeader) || empty($secret)) {
            return false;
        }

        $items = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                if ($pair[0] === 't') {
                    $timestamp = $pair[1];
                } elseif ($pair[0] === 'v1') {
                    $signatures[] = $pair[1];
                }
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle incoming Stripe webhook event.
     */
    public static function handleWebhookEvent(array $event): array
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                $csId = $data['id'] ?? '';
                $customerId = $data['customer'] ?? 'cus_active';
                $subId = $data['subscription'] ?? ('sub_stripe_' . bin2hex(random_bytes(6)));
                $metadata = $data['metadata'] ?? [];

                $sub = SubscriptionRepository::findByStripeCheckoutSessionId($csId);
                if ($sub === null && !empty($metadata['subscriber_nick'])) {
                    $sub = new Subscription(
                        $metadata['target_type'] ?? 'user',
                        $metadata['target_name'] ?? $metadata['subscriber_nick'],
                        $metadata['subscriber_nick'],
                        $metadata['plan_id'] ?? 'nick_pro',
                        $customerId,
                        $subId,
                        $csId,
                        'active',
                        499,
                        'usd',
                        time() + 30 * 86400
                    );
                }

                if ($sub !== null) {
                    $sub->setStatus('active');
                    $sub->setStripeCustomerId($customerId);
                    $sub->setStripeSubscriptionId($subId);
                    $sub->setExpiresAt(time() + 30 * 86400);
                    SubscriptionRepository::save($sub);

                    self::activateSubscriptionPerks($sub);
                    return ['success' => true, 'message' => 'Subscription activated via Checkout completed webhook.'];
                }
                break;

            case 'customer.subscription.updated':
            case 'invoice.payment_succeeded':
                $subId = $data['subscription'] ?? ($data['id'] ?? '');
                $status = $data['status'] ?? 'active';

                $sub = SubscriptionRepository::findByStripeSubscriptionId($subId);
                if ($sub !== null) {
                    $sub->setStatus($status);
                    $sub->setExpiresAt(time() + 30 * 86400);
                    SubscriptionRepository::save($sub);

                    if ($status === 'active') {
                        self::activateSubscriptionPerks($sub);
                    }
                    return ['success' => true, 'message' => "Subscription {$subId} updated to status {$status}."];
                }
                break;

            case 'customer.subscription.deleted':
                $subId = $data['id'] ?? '';
                $sub = SubscriptionRepository::findByStripeSubscriptionId($subId);
                if ($sub !== null) {
                    SubscriptionRepository::updateStatus($sub->getId(), 'canceled', time());
                    if ($sub->getTargetType() === 'user' || $sub->getTargetType() === 'nick') {
                        UserNickRepository::updateSubscription($sub->getTargetName(), null, 'canceled', time());
                    } elseif ($sub->getTargetType() === 'channel') {
                        ChannelRepository::updateSubscription($sub->getTargetName(), null, 'canceled', time());
                    }
                    return ['success' => true, 'message' => "Subscription {$subId} canceled via webhook."];
                }
                break;
        }

        return ['success' => true, 'message' => "Webhook event {$type} acknowledged."];
    }

    /**
     * Activate perks on target user, channel, or server.
     */
    private static function activateSubscriptionPerks(Subscription $sub): void
    {
        $targetType = $sub->getTargetType();
        $targetName = $sub->getTargetName();
        $planId = $sub->getPlanId();
        $exp = $sub->getExpiresAt();

        if ($targetType === 'user' || $targetType === 'nick') {
            UserNickRepository::updateSubscription($targetName, $planId, 'active', $exp);
            MemoServ::send(
                'PAYSERV',
                $sub->getSubscriberNick(),
                "🎉 Your User Nick Pro subscription is active for '{$targetName}'! Enjoy Nick protection and ⭐ Premium perks."
            );
        } elseif ($targetType === 'channel') {
            ChannelRepository::updateSubscription($targetName, $planId, 'active', $exp);
            MemoServ::send(
                'PAYSERV',
                $sub->getSubscriberNick(),
                "🚀 Channel Pro subscription activated for '{$targetName}'! Subrooms & BotServ privileges granted."
            );
        } elseif ($targetType === 'server') {
            SettingsManager::setSetting('server_vip_sponsor', $sub->getSubscriberNick());
            MemoServ::send(
                'PAYSERV',
                $sub->getSubscriberNick(),
                "👑 Server VIP Sponsor subscription activated! Thank you for supporting the IVC Network."
            );
        }
    }
}
