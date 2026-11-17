<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

final readonly class PaymentCustomer
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromArray(array $data, string $path = 'payment.customer.'): self
    {
        $o = StrictWire::object($data, rtrim($path, '.'));

        return new self(StrictWire::string($o, 'name', $path), StrictWire::string($o, 'email', $path));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email];
    }
}
