<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * Subscription state carried by `subscription.*` events (recurring plans).
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
