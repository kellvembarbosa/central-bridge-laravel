<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class InvoiceRequest
{
    public function __construct(
        public bool $requested,
        public ?string $requestRef = null,
        public ?string $email = null,
        public ?string $serviceDescription = null,
        public ?InvoiceTaker $taker = null,
    ) {}

    public static function notRequested(): self
    {
        return new self(false);
    }

    public static function fromArray(array $data): self
    {
        $taker = $data['taker'] ?? null;

        return new self(
            (bool) ($data['requested'] ?? false),
            Wire::nullableString($data['request_ref'] ?? null),
            Wire::nullableString($data['email'] ?? null),
            Wire::nullableString($data['service_description'] ?? null),
            match (true) {
                $taker instanceof InvoiceTaker => $taker,
                is_array($taker) => InvoiceTaker::fromArray($taker),
                default => null,
            },
        );
    }

    public function toArray(): array
    {
        return [
            'requested' => $this->requested,
            'request_ref' => $this->requestRef,
            'email' => $this->email,
            'service_description' => $this->serviceDescription,
            'taker' => $this->taker?->toArray(),
        ];
    }
}
