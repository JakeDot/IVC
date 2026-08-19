<?php

declare(strict_types=1);

namespace Fortress\Models;

/**
 * Domain model representing a paid subscription for a nickname or channel.
 */
class Subscription
{
    private ?string $id;
    private string $targetType;
    private string $targetName;
    private string $subscriberNick;
    private string $planId;
    private ?string $stripeCustomerId;
    private ?string $stripeSubscriptionId;
    private ?string $stripeCheckoutSessionId;
    private string $status;
    private int $priceCents;
    private string $currency;
    private int $expiresAt;
    private int $createdAt;
    private int $updatedAt;

    public function __construct(
        string $targetType,
        string $targetName,
        string $subscriberNick,
        string $planId,
        ?string $stripeCustomerId = null,
        ?string $stripeSubscriptionId = null,
        ?string $stripeCheckoutSessionId = null,
        string $status = 'active',
        int $priceCents = 499,
        string $currency = 'usd',
        ?int $expiresAt = null,
        ?int $createdAt = null,
        ?int $updatedAt = null,
        ?string $id = null
    ) {
        $this->id = $id ?? ('sub_' . bin2hex(random_bytes(8)));
        $this->targetType = strtolower(trim($targetType));
        $this->targetName = trim($targetName);
        $this->subscriberNick = trim($subscriberNick);
        $this->planId = trim($planId);
        $this->stripeCustomerId = $stripeCustomerId;
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        $this->stripeCheckoutSessionId = $stripeCheckoutSessionId;
        $this->status = strtolower(trim($status));
        $this->priceCents = $priceCents;
        $this->currency = strtolower(trim($currency));
        $this->expiresAt = $expiresAt ?? (time() + 30 * 86400);
        $this->createdAt = $createdAt ?? time();
        $this->updatedAt = $updatedAt ?? time();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetName(): string
    {
        return $this->targetName;
    }

    public function getSubscriberNick(): string
    {
        return $this->subscriberNick;
    }

    public function getPlanId(): string
    {
        return $this->planId;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): void
    {
        $this->stripeCustomerId = $stripeCustomerId;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): void
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
    }

    public function getStripeCheckoutSessionId(): ?string
    {
        return $this->stripeCheckoutSessionId;
    }

    public function setStripeCheckoutSessionId(?string $stripeCheckoutSessionId): void
    {
        $this->stripeCheckoutSessionId = $stripeCheckoutSessionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = strtolower(trim($status));
        $this->updatedAt = time();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true) && $this->expiresAt > time();
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(int $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
        $this->updatedAt = time();
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType,
            'target_name' => $this->targetName,
            'subscriber_nick' => $this->subscriberNick,
            'plan_id' => $this->planId,
            'stripe_customer_id' => $this->stripeCustomerId,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
            'stripe_checkout_session_id' => $this->stripeCheckoutSessionId,
            'status' => $this->status,
            'price_cents' => $this->priceCents,
            'currency' => $this->currency,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_active' => $this->isActive() ? 1 : 0
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['target_type'] ?? 'nick'),
            (string)($data['target_name'] ?? ''),
            (string)($data['subscriber_nick'] ?? ''),
            (string)($data['plan_id'] ?? 'nick_pro'),
            isset($data['stripe_customer_id']) ? (string)$data['stripe_customer_id'] : null,
            isset($data['stripe_subscription_id']) ? (string)$data['stripe_subscription_id'] : null,
            isset($data['stripe_checkout_session_id']) ? (string)$data['stripe_checkout_session_id'] : null,
            (string)($data['status'] ?? 'active'),
            isset($data['price_cents']) ? (int)$data['price_cents'] : 499,
            (string)($data['currency'] ?? 'usd'),
            isset($data['expires_at']) ? (int)$data['expires_at'] : null,
            isset($data['created_at']) ? (int)$data['created_at'] : null,
            isset($data['updated_at']) ? (int)$data['updated_at'] : null,
            isset($data['id']) ? (string)$data['id'] : null
        );
    }
}
