<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;
use Applu\CentralBridge\Support\Wire;

/**
 * A Central checkout payment (contract §10). Grant access idempotently by `id`.
 */
final readonly class PaymentRecord
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = ['pending', 'paid', 'expired', 'refunded', 'failed'];

    /**
     * @param  string  $id  Central payment id (uuid)
     * @param  string  $method  pix|card|boleto|other
     * @param  string  $productUserRef  the buyer's id in the product (`user_ref`)
     * @param  PaymentPix|null  $pix  only while pending
     * @param  string|null  $checkoutUrl  hosted payment page, only while pending
     * @param  array<string, string>  $tracking
     */
    public function __construct(
        public string $id,
        public string $status,
        public PaymentPlan $plan,
        public int $amountCents,
        public int $refundedCents,
        public string $currency,
        public string $method,
        public string $productUserRef,
        public PaymentCustomer $customer,
        public ?PaymentPix $pix,
        public ?string $checkoutUrl,
        public array $tracking,
        public string $createdAt,
        public ?string $paidAt = null,
        public ?string $refundedAt = null,
        public ?string $expiresAt = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Parses the wire shape. Throws BridgeException `validation_failed` (422).
     */
    public static function fromArray(mixed $data): self
    {
        $o = StrictWire::object($data, 'payment');
        $p = 'payment.';

        $status = StrictWire::oneOf($o, 'status', self::STATUSES, $p);
        $plan = StrictWire::object($o['plan'] ?? null, $p.'plan');
        $customer = StrictWire::object($o['customer'] ?? null, $p.'customer');
        $pix = StrictWire::objectOrNull($o['pix'] ?? null, $p.'pix');

        return new self(
            StrictWire::string($o, 'id', $p),
            $status,
            PaymentPlan::fromArray($plan, $p.'plan.'),
            StrictWire::int($o, 'amount_cents', $p),
            StrictWire::intOrNull($o, 'refunded_cents', $p) ?? 0,
            StrictWire::string($o, 'currency', $p),
            StrictWire::stringOrNull($o, 'method', $p) ?? 'other',
            StrictWire::string($o, 'product_user_ref', $p),
            PaymentCustomer::fromArray($customer, $p.'customer.'),
            $pix === null ? null : PaymentPix::fromArray($pix, $p.'pix.'),
            StrictWire::stringOrNull($o, 'checkout_url', $p),
            StrictWire::stringMap($o['tracking'] ?? null, $p.'tracking'),
            StrictWire::string($o, 'created_at', $p),
            StrictWire::stringOrNull($o, 'paid_at', $p),
            StrictWire::stringOrNull($o, 'refunded_at', $p),
            StrictWire::stringOrNull($o, 'expires_at', $p),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'plan' => $this->plan->toArray(),
            'amount_cents' => $this->amountCents,
            'refunded_cents' => $this->refundedCents,
            'currency' => $this->currency,
            'method' => $this->method,
            'product_user_ref' => $this->productUserRef,
            'customer' => $this->customer->toArray(),
            'pix' => $this->pix?->toArray(),
            'checkout_url' => $this->checkoutUrl,
            'tracking' => Wire::map($this->tracking),
            'created_at' => $this->createdAt,
            'paid_at' => $this->paidAt,
            'refunded_at' => $this->refundedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
