<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * What the Central sends to `POST payment-events` (contract §10). It may
 * arrive more than once: handle it idempotently by `id` (and, to grant,
 * by `payment->id`).
 *
 * - `payment.paid`: set the access of `payment->productUserRef` to
 *   `payment->plan->slug` until `payment->accessUntil` (null = forever), once
 *   per payment id.
 * - `payment.refunded`: full refund or chargeback, revoke.
 * - `payment.expired`: the pending charge expired unpaid.
 * - `subscription.renewed`: same as `payment.paid`, access now runs until the
 *   new `payment->accessUntil`.
 * - `subscription.ended`: the gateway will not charge again; access still
 *   runs until `payment->accessUntil`.
 *
 * `subscription` is sent for every paid plan that has a period (recurring or
 * one-off prepaid) and is null only for lifetime plans and never-paid
 * charges. `subscription->currentPeriodEnd` equals `payment->accessUntil`.
 */
final readonly class PaymentEvent
{
    public const PAYMENT_PAID = 'payment.paid';

    public const PAYMENT_REFUNDED = 'payment.refunded';

    public const PAYMENT_EXPIRED = 'payment.expired';

    public const SUBSCRIPTION_RENEWED = 'subscription.renewed';

    public const SUBSCRIPTION_ENDED = 'subscription.ended';

    public const TYPES = [
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
        self::PAYMENT_EXPIRED,
        self::SUBSCRIPTION_RENEWED,
        self::SUBSCRIPTION_ENDED,
    ];

    public function __construct(
        public string $id,
        public string $type,
        public string $occurredAt,
        public PaymentRecord $payment,
        public ?PaymentEventSubscription $subscription = null,
    ) {}

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Parses the event body. Throws BridgeException `validation_failed` (422).
     */
    public static function fromArray(array $data): self
    {
        $type = StrictWire::oneOf($data, 'type', self::TYPES);
        $subscription = StrictWire::objectOrNull($data['subscription'] ?? null, 'subscription');

        return new self(
            StrictWire::string($data, 'id'),
            $type,
            StrictWire::string($data, 'occurred_at'),
            PaymentRecord::fromArray($data['payment'] ?? null),
            $subscription === null ? null : PaymentEventSubscription::fromArray($subscription),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'occurred_at' => $this->occurredAt,
            'payment' => $this->payment->toArray(),
            'subscription' => $this->subscription?->toArray(),
        ];
    }
}
