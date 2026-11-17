<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * Period state of the payment's plan, sent with every paid plan that has a
 * period (recurring or one-off prepaid); null on the event only for lifetime
 * plans and never-paid charges. `currentPeriodEnd` equals
 * `payment->accessUntil`.
 */
final readonly class PaymentEventSubscription
{
    public function __construct(
        public string $status,
        public ?string $currentPeriodEnd = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $o = StrictWire::object($data, 'subscription');

        return new self(
            StrictWire::string($o, 'status', 'subscription.'),
            StrictWire::stringOrNull($o, 'current_period_end', 'subscription.'),
        );
    }

    public function toArray(): array
    {
        return ['status' => $this->status, 'current_period_end' => $this->currentPeriodEnd];
    }
}
