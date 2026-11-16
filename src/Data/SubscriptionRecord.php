<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class SubscriptionRecord
{
    public const STATUSES = ['trialing', 'active', 'past_due', 'canceled', 'expired', 'paused'];

    public function __construct(
        public string $ref,
        public string $userRef,
        public ?string $provider,
        public ?string $providerSubscriptionId,
        public string $status,
        public ?string $plan,
        public ?int $amountCents,
        public ?string $currency,
        public ?string $interval,
        public ?string $currentPeriodEnd,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['ref'],
            (string) $data['user_ref'],
            Wire::nullableString($data['provider'] ?? null),
            Wire::nullableString($data['provider_subscription_id'] ?? null),
            (string) $data['status'],
            Wire::nullableString($data['plan'] ?? null),
            isset($data['amount_cents']) ? (int) $data['amount_cents'] : null,
            Wire::nullableString($data['currency'] ?? null),
            Wire::nullableString($data['interval'] ?? null),
            Wire::dateTime($data['current_period_end'] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'user_ref' => $this->userRef,
            'provider' => $this->provider,
            'provider_subscription_id' => $this->providerSubscriptionId,
            'status' => $this->status,
            'plan' => $this->plan,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'current_period_end' => $this->currentPeriodEnd,
        ];
    }
}
