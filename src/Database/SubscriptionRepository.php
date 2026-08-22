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
<<<<<<< HEAD
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE id = :id",
            [':id' => trim($id)]
        );

=======
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['id' => trim($id)]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeSubscriptionId(string $stripeSubId): ?Subscription
    {
<<<<<<< HEAD
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE stripe_subscription_id = :sid",
            [':sid' => trim($stripeSubId)]
        );

=======
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['stripe_subscription_id' => trim($stripeSubId)]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeCheckoutSessionId(string $sessionId): ?Subscription
    {
<<<<<<< HEAD
        $row = Database::fetchOne(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at FROM subscriptions WHERE stripe_checkout_session_id = :csid",
            [':csid' => trim($sessionId)]
        );

=======
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['stripe_checkout_session_id' => trim($sessionId)]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findActiveByTarget(string $targetType, string $targetName): ?Subscription
    {
        $now = time();
<<<<<<< HEAD
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

=======
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne([
            'target_type' => ['$regex' => '^' . preg_quote(trim($targetType), '/') . '$', '$options' => 'i'],
            'target_name' => ['$regex' => '^' . preg_quote(trim($targetName), '/') . '$', '$options' => 'i'],
            'status' => ['$in' => ['active', 'trialing']],
            'expires_at' => ['$gt' => $now]
        ]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    /**
     * Get all active and inactive subscriptions for a subscriber nickname.
     *
     * @return Subscription[]
     */
    public static function findAllBySubscriber(string $subscriberNick): array
    {
<<<<<<< HEAD
        $rows = Database::fetchAll(
            "SELECT id, target_type, target_name, subscriber_nick, plan_id, stripe_customer_id, stripe_subscription_id, stripe_checkout_session_id, status, price_cents, currency, expires_at, created_at, updated_at
             FROM subscriptions
             WHERE LOWER(subscriber_nick) = LOWER(:nick)
             ORDER BY created_at DESC",
            [':nick' => trim($subscriberNick)]
        );

=======
        $coll = Database::getCollection('subscriptions');
        $rows = $coll->find(['subscriber_nick' => ['$regex' => '^' . preg_quote(trim($subscriberNick), '/') . '$', '$options' => 'i']]);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        $subs = [];
        foreach ($rows as $row) {
            $subs[] = Subscription::fromArray($row);
        }

        return $subs;
    }

    public static function save(Subscription $sub): bool
    {
<<<<<<< HEAD
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
=======
        $coll = Database::getCollection('subscriptions');
        $existing = self::findById($sub->getId());

        $doc = [
            'id' => $sub->getId(),
            'target_type' => $sub->getTargetType(),
            'target_name' => $sub->getTargetName(),
            'subscriber_nick' => $sub->getSubscriberNick(),
            'plan_id' => $sub->getPlanId(),
            'stripe_customer_id' => $sub->getStripeCustomerId(),
            'stripe_subscription_id' => $sub->getStripeSubscriptionId(),
            'stripe_checkout_session_id' => $sub->getStripeCheckoutSessionId(),
            'status' => $sub->getStatus(),
            'price_cents' => $sub->getPriceCents(),
            'currency' => $sub->getCurrency(),
            'expires_at' => $sub->getExpiresAt(),
            'created_at' => $sub->getCreatedAt(),
            'updated_at' => time()
        ];

        if ($existing !== null) {
            $coll->updateOne(['id' => $sub->getId()], ['$set' => $doc]);
        } else {
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateStatusAndExpiryBySubscriptionId(string $stripeSubId, string $status, int $expiresAt): bool
    {
        $coll = Database::getCollection('subscriptions');
        $coll->updateOne(
            ['stripe_subscription_id' => trim($stripeSubId)],
            ['$set' => ['status' => $status, 'expires_at' => $expiresAt, 'updated_at' => time()]]
        );
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }

    public static function delete(string $id): bool
    {
<<<<<<< HEAD
        $stmt = Database::execute("DELETE FROM subscriptions WHERE id = :id", [':id' => trim($id)]);
        return $stmt->rowCount() > 0;
=======
        $coll = Database::getCollection('subscriptions');
        $coll->deleteOne(['id' => trim($id)]);
        return true;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    }
}
