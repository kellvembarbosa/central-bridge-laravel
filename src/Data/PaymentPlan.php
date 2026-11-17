<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * The plan a payment bought (snapshot inside PaymentRecord).
 */
final readonly class PaymentPlan
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $interval,
        public int $intervalCount,
        public bool $recurring,
    ) {}

    public function isLifetime(): bool
    {
        return $this->interval === 'lifetime';
    }

    public static function fromArray(array $data, string $path = 'payment.plan.'): self
    {
        $o = StrictWire::object($data, rtrim($path, '.'));

        return new self(
            StrictWire::string($o, 'slug', $path),
            StrictWire::string($o, 'name', $path),
            StrictWire::oneOf($o, 'interval', Plan::INTERVALS, $path),
            StrictWire::int($o, 'interval_count', $path),
            StrictWire::bool($o, 'recurring', $path),
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'interval' => $this->interval,
            'interval_count' => $this->intervalCount,
            'recurring' => $this->recurring,
        ];
    }
}
