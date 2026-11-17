<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\StrictWire;

/**
 * Plan on sale in the Central checkout (GET /api/v1/plans, contract §10).
 * The price always comes from the Central; the product only picks the slug.
 */
final readonly class Plan
{
    public const INTERVALS = ['day', 'week', 'month', 'year', 'lifetime'];

    /**
     * @param  string  $interval  how long one payment grants access (`lifetime` = forever)
     * @param  bool  $recurring  the gateway charges again every period
     * @param  list<string>  $features
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public string $interval,
        public int $intervalCount,
        public bool $recurring,
        public int $amountCents,
        public ?int $originalAmountCents,
        public string $currency,
        public ?string $badge = null,
        public array $features = [],
        public int $sortOrder = 0,
    ) {}

    public function isLifetime(): bool
    {
        return $this->interval === 'lifetime';
    }

    public static function fromArray(array $data): self
    {
        $o = StrictWire::object($data, 'plan');
        $p = 'plan.';

        return new self(
            StrictWire::string($o, 'slug', $p),
            StrictWire::string($o, 'name', $p),
            StrictWire::stringOrNull($o, 'description', $p),
            StrictWire::oneOf($o, 'interval', self::INTERVALS, $p),
            StrictWire::int($o, 'interval_count', $p),
            StrictWire::bool($o, 'recurring', $p),
            StrictWire::int($o, 'amount_cents', $p),
            StrictWire::intOrNull($o, 'original_amount_cents', $p),
            StrictWire::string($o, 'currency', $p),
            StrictWire::stringOrNull($o, 'badge', $p),
            StrictWire::stringList($o['features'] ?? null, $p.'features'),
            StrictWire::intOrNull($o, 'sort_order', $p) ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'interval' => $this->interval,
            'interval_count' => $this->intervalCount,
            'recurring' => $this->recurring,
            'amount_cents' => $this->amountCents,
            'original_amount_cents' => $this->originalAmountCents,
            'currency' => $this->currency,
            'badge' => $this->badge,
            'features' => $this->features,
            'sort_order' => $this->sortOrder,
        ];
    }
}
