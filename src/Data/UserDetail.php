<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

/**
 * UserSummary + limits, usage, subscriptions and recent events.
 */
final readonly class UserDetail
{
    /**
     * @param  array<string, int|string|null>  $limits
     * @param  array<string, int>  $usage
     * @param  list<SubscriptionRecord>  $subscriptions
     * @param  list<UserEvent>  $recentEvents
     */
    public function __construct(
        public UserSummary $user,
        public array $limits = [],
        public array $usage = [],
        public array $subscriptions = [],
        public array $recentEvents = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            UserSummary::fromArray($data),
            $data['limits'] ?? [],
            array_map('intval', $data['usage'] ?? []),
            array_map(
                fn ($item) => $item instanceof SubscriptionRecord ? $item : SubscriptionRecord::fromArray($item),
                array_values($data['subscriptions'] ?? []),
            ),
            array_map(
                fn ($item) => $item instanceof UserEvent ? $item : UserEvent::fromArray($item),
                array_values($data['recent_events'] ?? []),
            ),
        );
    }

    public function toArray(): array
    {
        return $this->user->toArray() + [
            'limits' => Wire::map($this->limits),
            'usage' => Wire::map(array_map('intval', $this->usage)),
            'subscriptions' => array_map(fn (SubscriptionRecord $s) => $s->toArray(), $this->subscriptions),
            'recent_events' => array_map(fn (UserEvent $e) => $e->toArray(), $this->recentEvents),
        ];
    }
}
