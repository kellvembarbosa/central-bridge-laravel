<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class PlanChange
{
    /**
     * @param  string|null  $until  YYYY-MM-DD, or null for no end date
     */
    public function __construct(
        public string $plan,
        public ?string $until = null,
        public ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['plan'],
            Wire::date($data['until'] ?? null),
            Wire::nullableString($data['reason'] ?? null),
        );
    }

    public function toArray(): array
    {
        return ['plan' => $this->plan, 'until' => $this->until, 'reason' => $this->reason];
    }
}
