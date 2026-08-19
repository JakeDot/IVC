<?php

declare(strict_types=1);

namespace Fortress\Database;

use Fortress\Models\Subscription;

/**
 * Data Access Repository for paid subscriptions (subscriptions table).
 */
class SubscriptionRepository
{
    public static function findById(string $id): ?Subscription
    {
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE id = :id",
            [':id' => trim($id)]
        );

        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeSubscriptionId(string $stripeSubId): ?Subscription
    {
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE stripe_subscription_id = :sid",
            [':sid' => trim($stripeSubId)]
        );

        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeCheckoutSessionId(string $sessionId): ?Subscription
    {
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE stripe_checkout_session_id = :csid",
            [':csid' => trim($sessionId)]
        );

        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findActiveByTarget(string $targetType, string $targetName): ?Subscription
    {
        $now = time();
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at
             FROM subscriptions
             WHERE LOWER(target_type) = LOWER(:type) AND LOWER(target_name) = LOWER(:name) AND status IN ('active', 'trialing') AND expires_at > :now
             ORDER BY expires_at DESC LIMIT 1",
            [
                ':type' => trim($targetType),
                ':name' => trim($targetName),
                ':now' => $now
            ]
        );

        return $row !== null ? Subscription::fromArray($row) : null;
    }

    /**
     * Get all active and inactive subscriptions for a subscriber nickname.
     *
     * @return Subscription[]
     */
    public static function findAllBySubscriber(string $subscriberNick): array
    {
        $rows = Database::fetchAll(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at
             FROM subscriptions
             WHERE LOWER(subscriber_nick) = LOWER(:nick)
             ORDER BY created_at DESC",
            [':nick' => trim($subscriberNick)]
        );

        $subs = [];
        foreach ($rows as $row) {
            $subs[] = Subscription::fromArray($row);
        }

        return $subs;
    }

    public static function save(Subscription $sub): bool
    {
        $existing = self::findById($sub->getId());

        if ($existing !== null) {
            $stmt = Database::execute(
                "UPDATE subscriptions SET
                    target_type = :ttype,
                    target_name = :tname,
                    subscriber_nick = :subnick,
                    plan_id = :plan,
                    stripe_customer_id = :cid,
                    stripe_subscription_id = :sid,
                    stripe_checkout_session_id = :csid,
                    status = :status,
                    price_cents = :price,
                    currency = :curr,
                    expires_at = :exp,
                    updated_at = :upd
                 WHERE id = :id",
                [
                    ':ttype' => $sub->getTargetType(),
                    ':tname' => $sub->getTargetName(),
                    ':subnick' => $sub->getSubscriberNick(),
                    ':plan' => $sub->getPlanId(),
                    ':cid' => $sub->getStripeCustomerId(),
                    ':sid' => $sub->getStripeSubscriptionId(),
                    ':csid' => $sub->getStripeCheckoutSessionId(),
                    ':status' => $sub->getStatus(),
                    ':price' => $sub->getPriceCents(),
                    ':curr' => $sub->getCurrency(),
                    ':exp' => $sub->getExpiresAt(),
                    ':upd' => time(),
                    ':id' => $sub->getId()
                ]
            );
        } else {
            $stmt = Database::execute(
                "INSERT INTO subscriptions (id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at)
                 VALUES (:id, :ttype, :tname, :subnick, :plan, :cid, :sid, :csid, :status, :price, :curr, :exp, :created, :upd)",
                [
                    ':id' => $sub->getId(),
                    ':ttype' => $sub->getTargetType(),
                    ':tname' => $sub->getTargetName(),
                    ':subnick' => $sub->getSubscriberNick(),
                    ':plan' => $sub->getPlanId(),
                    ':cid' => $sub->getStripeCustomerId(),
                    ':sid' => $sub->getStripeSubscriptionId(),
                    ':csid' => $sub->getStripeCheckoutSessionId(),
                    ':status' => $sub->getStatus(),
                    ':price' => $sub->getPriceCents(),
                    ':curr' => $sub->getCurrency(),
                    ':exp' => $sub->getExpiresAt(),
                    ':created' => $sub->getCreatedAt(),
                    ':upd' => $sub->getUpdatedAt()
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    public static function updateStatus(string $id, string $status, ?int $expiresAt = null): bool
    {
        $now = time();
        if ($expiresAt !== null) {
            $stmt = Database::execute(
                "UPDATE subscriptions SET status = :status, expires_at = :exp, updated_at = :upd WHERE id = :id",
                [
                    ':status' => strtolower(trim($status)),
                    ':exp' => $expiresAt,
                    ':upd' => $now,
                    ':id' => trim($id)
                ]
            );
        } else {
            $stmt = Database::execute(
                "UPDATE subscriptions SET status = :status, updated_at = :upd WHERE id = :id",
                [
                    ':status' => strtolower(trim($status)),
                    ':upd' => $now,
                    ':id' => trim($id)
                ]
            );
        }

        return $stmt->rowCount() > 0;
    }

    public static function delete(string $id): bool
    {
        $stmt = Database::execute("DELETE FROM subscriptions WHERE id = :id", [':id' => trim($id)]);
        return $stmt->rowCount() > 0;
    }
}
