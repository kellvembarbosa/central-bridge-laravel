<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class PaymentOwner
{
    public const KIND_SUBSCRIPTION = 'subscription';

    public const KIND_ORDER = 'order';

    /**
     * @param  string|null  $kind  "subscription", "order" or null
     */
    public function __construct(
        public bool $owned,
        public ?string $userRef = null,
        public ?string $kind = null,
        public ?string $localStatus = null,
    ) {}

    public static function notOwned(): self
    {
        return new self(false);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['owned'] ?? false),
            Wire::nullableString($data['user_ref'] ?? null),
            Wire::nullableString($data['kind'] ?? null),
            Wire::nullableString($data['local_status'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'owned' => $this->owned,
            'user_ref' => $this->userRef,
            'kind' => $this->kind,
            'local_status' => $this->localStatus,
        ];
    }
}
