<?php

namespace Applu\CentralBridge\Data;

use Applu\CentralBridge\Support\Wire;

final readonly class MetricsDay
{
    /**
     * @param  string  $date  YYYY-MM-DD
     * @param  array<string, int>  $metrics
     */
    public function __construct(
        public string $date,
        public array $metrics,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((string) Wire::date($data['date']), array_map('intval', $data['metrics'] ?? []));
    }

    public function toArray(): array
    {
        return ['date' => $this->date, 'metrics' => Wire::map(array_map('intval', $this->metrics))];
    }
}
