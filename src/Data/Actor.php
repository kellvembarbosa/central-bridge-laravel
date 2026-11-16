<?php

namespace Applu\CentralBridge\Data;

/**
 * Who asked for the action in the Central (X-Central-Actor).
 */
final readonly class Actor
{
    public function __construct(
        public string $email,
        public ?string $requestId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((string) $data['email'], $data['request_id'] ?? null);
    }

    public function toArray(): array
    {
        return ['email' => $this->email, 'request_id' => $this->requestId];
    }
}
