<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class UserSummary
{
    public function __construct(
        public string $ref,
        public string $email,
        public ?string $name = null,
        public ?string $plan = null,
        public bool $suspended = false,
        public ?string $createdAt = null,
        public ?string $lastSeenAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['ref'],
            (string) $data['email'],
            Wire::nullableString($data['name'] ?? null),
            Wire::nullableString($data['plan'] ?? null),
            (bool) ($data['suspended'] ?? false),
            Wire::dateTime($data['created_at'] ?? null),
            Wire::dateTime($data['last_seen_at'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'email' => $this->email,
            'name' => $this->name,
            'plan' => $this->plan,
            'suspended' => $this->suspended,
            'created_at' => $this->createdAt,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}
