<?php
declare(strict_types=1);
namespace Fortress\Database;
use Fortress\Models\Subscription;

class SubscriptionRepository
{
    public static function findById(string $id): ?Subscription
    {
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['id' => trim($id)]);
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeCustomerId(string $stripeCustomerId): ?Subscription
    {
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['stripe_customer_id' => trim($stripeCustomerId)]);
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['stripe_subscription_id' => trim($stripeSubscriptionId)]);
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByCheckoutSessionId(string $checkoutSessionId): ?Subscription
    {
        $coll = Database::getCollection('subscriptions');
        $row = $coll->findOne(['stripe_checkout_session_id' => trim($checkoutSessionId)]);
        return $row !== null ? Subscription::fromArray($row) : null;
    }

    public static function findByTarget(string $targetType, string $targetName): array
    {
        $coll = Database::getCollection('subscriptions');
        $rows = $coll->find([
            'target_type' => trim($targetType),
            'target_name' => ['$regex' => '^' . preg_quote(trim($targetName), '/') . '$', '$options' => 'i']
        ], ['sort' => ['created_at' => -1]]);
        
        $subs = [];
        foreach ($rows as $row) {
            $subs[] = Subscription::fromArray($row);
        }
        return $subs;
    }

    public static function save(Subscription $sub): bool
    {
        $coll = Database::getCollection('subscriptions');
        $exists = self::findById($sub->getId()) !== null;
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
        
        if ($exists) {
            $coll->updateOne(['id' => $sub->getId()], ['$set' => $doc]);
        } else {
            $doc['updated_at'] = $sub->getUpdatedAt();
            $coll->insertOne($doc);
        }
        return true;
    }

    public static function updateStatusBySubscriptionId(string $stripeSubscriptionId, string $status, int $expiresAt): bool
    {
        $coll = Database::getCollection('subscriptions');
        $coll->updateOne(
            ['stripe_subscription_id' => trim($stripeSubscriptionId)],
            ['$set' => ['status' => trim($status), 'expires_at' => $expiresAt, 'updated_at' => time()]]
        );
        return true;
    }

    public static function updateCheckoutSession(string $id, string $checkoutSessionId): bool
    {
        $coll = Database::getCollection('subscriptions');
        $coll->updateOne(
            ['id' => trim($id)],
            ['$set' => ['stripe_checkout_session_id' => trim($checkoutSessionId), 'updated_at' => time()]]
        );
        return true;
    }

    public static function updateStripeIds(string $id, string $customerId, string $subscriptionId): bool
    {
        $coll = Database::getCollection('subscriptions');
        $coll->updateOne(
            ['id' => trim($id)],
            ['$set' => ['stripe_customer_id' => trim($customerId), 'stripe_subscription_id' => trim($subscriptionId), 'updated_at' => time()]]
        );
        return true;
    }

    public static function delete(string $id): bool
    {
        $coll = Database::getCollection('subscriptions');
        $coll->deleteOne(['id' => trim($id)]);
        return true;
    }
}
