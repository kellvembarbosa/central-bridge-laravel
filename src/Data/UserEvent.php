<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class UserEvent
{
    public function __construct(
        public string $type,
        public string $at,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['type'],
            (string) Wire::dateTime($data['at']),
            Wire::nullableString($data['description'] ?? null),
        );
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'at' => $this->at, 'description' => $this->description];
    }
}
